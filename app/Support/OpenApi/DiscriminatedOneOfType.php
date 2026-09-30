<?php

namespace App\Support\OpenApi;

use Dedoc\Scramble\Support\Generator\Types\Type;

final class DiscriminatedOneOfType extends Type
{
    /**
     * @param  list<Type>  $items
     * @param  array<string, string>  $mapping
     */
    public function __construct(
        private readonly array $items,
        private readonly string $propertyName,
        private readonly array $mapping,
    ) {
        parent::__construct('oneOf');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $schema = parent::toArray();
        unset($schema['type']);

        return [
            ...$schema,
            'oneOf' => array_map(fn (Type $item): array => $item->toArray(), $this->items),
            'discriminator' => [
                'propertyName' => $this->propertyName,
                'mapping' => $this->mapping,
            ],
        ];
    }
}
