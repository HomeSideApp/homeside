<?php

namespace App\Concerns;

use App\Models\EconomicDocument;
use App\Models\EconomicImport;
use App\Models\EconomicTransaction;
use App\Models\Household;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesEconomicRoute
{
    public function economicHousehold(): ?Household
    {
        $household = $this->route('household');
        if (is_string($household)) {
            $household = Household::query()->whereKey($household)->firstOrFail();
            $this->route()?->setParameter('household', $household);
        }

        return $household;
    }

    public function economicTransaction(): EconomicTransaction
    {
        return $this->resolveEconomicResource('transaction', EconomicTransaction::class);
    }

    public function economicDocument(): EconomicDocument
    {
        return $this->resolveEconomicResource('document', EconomicDocument::class);
    }

    public function economicImport(): EconomicImport
    {
        return $this->resolveEconomicResource('import', EconomicImport::class);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    private function resolveEconomicResource(string $parameter, string $model): Model
    {
        $resource = $this->route($parameter);
        if (! $resource instanceof $model) {
            $resource = $model::query()->whereKey($resource)->firstOrFail();
            $this->route()?->setParameter($parameter, $resource);
        }

        abort_unless($resource->getAttribute('household_id') === $this->economicHousehold()?->id, 403);

        /** @var TModel $resource */
        return $resource;
    }
}
