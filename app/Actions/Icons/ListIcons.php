<?php

namespace App\Actions\Icons;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Lists the generated static product icons.
 */
final class ListIcons
{
    /**
     * @return Collection<int, array{name: string, url: string, type: string}>
     */
    public function execute(): Collection
    {
        $iconsPath = public_path('icons/webp');

        if (! File::isDirectory($iconsPath)) {
            return new Collection;
        }

        return collect(File::files($iconsPath))
            ->filter(fn ($file): bool => strtolower($file->getExtension()) === 'webp')
            ->sortBy(fn ($file): string => $file->getFilename())
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'url' => asset('icons/webp/'.$file->getFilename()),
                'type' => 'static',
            ])
            ->values();
    }
}
