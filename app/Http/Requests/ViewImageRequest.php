<?php

namespace App\Http\Requests;

use App\Enums\ImageType;
use App\Models\Household;
use App\Models\ListItem;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ViewImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        $uuid = (string) $this->route('uuid');
        $type = ImageType::from($this->route('type'));

        return match ($type) {
            ImageType::ListItem => $this->canViewListItem($user, $uuid),
            ImageType::Household => Household::query()->whereKey($uuid)->firstOrFail()->isMember($user),
            ImageType::Recipe, ImageType::RecipeStep => $user->can('view', Recipe::query()->findOrFail($uuid)),
            ImageType::Generated => true,
        };
    }

    private function canViewListItem(User $user, string $uuid): bool
    {
        $item = ListItem::query()->findOrFail($uuid);

        return $item->list !== null && $item->list->created_by === $user->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
