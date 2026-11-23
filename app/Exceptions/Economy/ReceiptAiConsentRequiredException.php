<?php

declare(strict_types=1);

namespace App\Exceptions\Economy;

use RuntimeException;

/**
 * El ticket necesita IA, el proveedor configurado no corre en este servidor y
 * el usuario todavía no dio permiso.
 *
 * No es un error: es una decisión pendiente. El flujo la convierte en un
 * `consent_required` para que la pantalla pregunte una sola vez y el usuario
 * pueda revocarla después desde su perfil.
 */
final class ReceiptAiConsentRequiredException extends RuntimeException
{
    public function __construct(
        public readonly string $providerName,
        public readonly string $privacyLevel,
    ) {
        parent::__construct(
            "Reading this receipt with AI would send its data to [{$providerName}], "
            ."which runs on [{$privacyLevel}]. The user has not allowed that yet.",
        );
    }

    public static function forProvider(?string $name, ?string $level): self
    {
        return new self($name ?? 'an external provider', $level ?? 'unknown');
    }
}
