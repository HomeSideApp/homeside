<?php

namespace App\Services\Contacts\Providers;

use App\Data\Contacts\ContactValueData;
use App\Data\Contacts\ExternalContactData;
use RuntimeException;
use Sabre\VObject\Parser\MimeDir;

final class VCardParser
{
    public function parse(string $vcard, string $remoteId, ?string $etag = null, ?string $href = null): ExternalContactData
    {
        if (strlen($vcard) > 1024 * 1024 || ! str_contains($vcard, 'BEGIN:VCARD')) {
            throw new RuntimeException('Invalid or oversized vCard.');
        }

        $lines = preg_split('/\r\n|\n|\r/', $vcard) ?: [];
        $unfolded = [];
        foreach ($lines as $line) {
            if (preg_match('/^[ \t]/', $line) === 1 && $unfolded !== []) {
                $unfolded[count($unfolded) - 1] .= substr($line, 1);
            } else {
                $unfolded[] = $line;
            }
        }

        $fields = [];
        foreach ($unfolded as $line) {
            $position = strpos($line, ':');
            if ($position === false) {
                continue;
            }

            $head = substr($line, 0, $position);
            $name = strtoupper(explode(';', $head)[0]);
            $fields[$name][] = ['head' => $head, 'value' => substr($line, $position + 1)];
        }

        $first = static fn (string $key): ?string => isset($fields[$key][0]) ? $fields[$key][0]['value'] : null;
        $nameParts = explode(';', (string) $first('N'));
        $decode = static fn (?string $value): ?string => $value === null ? null : str_replace(['\\n', '\\N', '\\,', '\\;'], ["\n", "\n", ',', ';'], $value);
        $fullName = $decode($first('FN')) ?: trim(($nameParts[1] ?? '').' '.($nameParts[0] ?? ''));

        if ($fullName === '') {
            throw new RuntimeException('vCard has no display name.');
        }

        $values = function (string $key) use ($fields, $decode): array {
            return array_map(static function (array $entry) use ($decode): ContactValueData {
                $type = preg_match('/(?:^|;)TYPE=([^;]+)/i', $entry['head'], $matches) ? strtolower($matches[1]) : null;

                return new ContactValueData((string) $decode($entry['value']), $type, str_contains(strtoupper($entry['head']), 'PREF'));
            }, $fields[$key] ?? []);
        };

        $normalizeDate = static function (?string $value): ?string {
            if ($value === null) {
                return null;
            }
            if (preg_match('/^\d{8}$/', $value) === 1) {
                return substr($value, 0, 4).'-'.substr($value, 4, 2).'-'.substr($value, 6, 2);
            }

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
        };
        $birthday = $first('BDAY');
        $dates = [];
        if ($birthday !== null && $normalizeDate($birthday) === null) {
            $dates[] = ['kind' => 'birthday', 'label' => null, 'value' => mb_substr($birthday, 0, 64), 'value_type' => 'text'];
        }
        $anniversary = $first('ANNIVERSARY');
        if ($anniversary !== null) {
            $dates[] = [
                'kind' => 'anniversary',
                'label' => null,
                'value' => mb_substr($normalizeDate($anniversary) ?? $anniversary, 0, 64),
                'value_type' => $normalizeDate($anniversary) === null ? 'text' : 'date',
            ];
        }
        $relations = [];
        foreach ($fields['RELATED'] ?? [] as $entry) {
            $type = preg_match('/(?:^|;)TYPE=([^;]+)/i', $entry['head'], $matches)
                ? strtolower(explode(',', $matches[1])[0]) : 'other';
            $value = (string) $decode($entry['value']);
            $isText = preg_match('/(?:^|;)VALUE=text(?:;|$)/i', $entry['head']) === 1;
            $relations[] = [
                'type' => mb_substr($type, 0, 32),
                'name' => $isText ? mb_substr($value, 0, 255) : null,
                'external_value' => $isText ? null : $value,
            ];
        }

        $categories = [];
        foreach ($fields['CATEGORIES'] ?? [] as $entry) {
            foreach (MimeDir::unescapeValue($entry['value'], ',') as $category) {
                $name = mb_substr(trim($category), 0, 80);
                if ($name !== '' && ! in_array($name, $categories, true)) {
                    $categories[] = $name;
                }
            }
        }

        $photo = $fields['PHOTO'][0] ?? null;
        $photoBytes = null;
        if ($photo !== null) {
            $encoded = preg_match('/ENCODING=(?:B|BASE64)/i', $photo['head']) === 1
                ? $photo['value']
                : (preg_match('/^data:image\/(?:jpeg|png|webp);base64,(.*)$/is', $photo['value'], $matches) === 1 ? $matches[1] : null);
            $photoBytes = $encoded === null ? null : (base64_decode($encoded, true) ?: null);
            if ($photoBytes !== null && strlen($photoBytes) > 3 * 1024 * 1024) {
                $photoBytes = null;
            }
        }

        return new ExternalContactData(
            remoteId: $remoteId,
            formattedName: $fullName,
            givenName: $decode($nameParts[1] ?? null),
            familyName: $decode($nameParts[0] ?? null),
            organization: $decode($first('ORG')),
            jobTitle: $decode($first('TITLE')),
            birthday: $normalizeDate($birthday),
            notes: $decode($first('NOTE')),
            emails: $values('EMAIL'),
            phones: $values('TEL'),
            addresses: $values('ADR'),
            urls: $values('URL'),
            photoBytes: $photoBytes,
            etag: $etag,
            href: $href,
            uid: $first('UID'),
            additionalName: $decode($nameParts[2] ?? null),
            nickname: $decode($first('NICKNAME')),
            dates: $dates,
            relations: $relations,
            photoUri: $photoBytes === null && $photo !== null && ! str_starts_with($photo['value'], 'data:') ? $photo['value'] : null,
            categories: $categories,
        );
    }
}
