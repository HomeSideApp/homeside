<?php

declare(strict_types=1);

namespace App\Images\Strategies;

use App\Images\Contracts\ImageTypeStrategy;
use App\Models\ListItem;
use App\Models\User;

/**
 * Strategy for list item images.
 */
final class ListItemImage implements ImageTypeStrategy
{
    /**
     * {@inheritdoc}
     */
    public function resolvePath(string $uuid): ?string
    {
        return ListItem::where('id', $uuid)->value('image_url');
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(User $user, string $uuid): bool
    {
        $item = ListItem::with('list')->find($uuid);

        return $item && $item->list->created_by === $user->id;
    }

    /**
     * {@inheritdoc}
     */
    public function disk(): string
    {
        return 'local';
    }
}
