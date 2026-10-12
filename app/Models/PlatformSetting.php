<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Class PlatformSetting
 *
 * A single platform-wide configuration value, identified by a stable dotted
 * key. The store is intentionally generic: `value` is a nullable JSON payload,
 * so later phases can add policy keys without another schema change.
 *
 * Domain rules never live here. Registration semantics belong to
 * App\Services\Auth\RegistrationSettings, which owns the key namespace and the
 * secure defaults.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the setting (UUID).
 * @property string $key The stable settings key.
 * @property mixed $value The stored JSON value, or null when unset.
 * @property Carbon|null $created_at The timestamp when the setting was created.
 * @property Carbon|null $updated_at The timestamp when the setting was last updated.
 *
 * @mixin Model
 */
class PlatformSetting extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['key', 'value'];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Read a scalar string setting, or the default when the key is absent.
     *
     * @param  string  $key  The settings key.
     * @param  string|null  $default  The value returned when the key is missing or not scalar.
     * @return string|null The stored string value.
     */
    public static function getString(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Persist a scalar string setting.
     *
     * @param  string  $key  The settings key.
     * @param  string  $value  The value to store.
     */
    public static function setString(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
