<?php

namespace App\Services\Contacts;

use App\Contracts\Contacts\ContactProvider;
use App\Models\ContactSource;
use App\Services\Contacts\Providers\CardDavContactProvider;
use App\Services\Contacts\Providers\GooglePeopleContactProvider;
use App\Services\Contacts\Providers\LocalContactProvider;
use InvalidArgumentException;

final class ContactProviderRegistry
{
    /** @var array<string, class-string<ContactProvider>> */
    private array $providers = [
        'local' => LocalContactProvider::class,
        'carddav' => CardDavContactProvider::class,
        'google' => GooglePeopleContactProvider::class,
    ];

    public function for(ContactSource $source): ContactProvider
    {
        $class = $this->providers[$source->provider] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException('Unsupported contact provider.');
        }

        return app($class);
    }

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        return [
            ['key' => 'google', 'name' => 'Google Contactos', 'description' => 'Importa tus contactos de Google con OAuth', 'authentication_type' => 'oauth2', 'capabilities' => $this->for(new ContactSource(['provider' => 'google']))->capabilities()],
            ['key' => 'carddav', 'name' => 'CardDAV', 'description' => 'Nextcloud, ownCloud, Radicale, Baïkal and compatible servers', 'authentication_type' => 'app_password', 'capabilities' => $this->for(new ContactSource(['provider' => 'carddav']))->capabilities()],
        ];
    }
}
