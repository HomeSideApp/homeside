<?php

use App\Console\Commands\GenerateRecurringEconomicTransactions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('recipes:reconcile-references')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command(GenerateRecurringEconomicTransactions::class)
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(30);

Schedule::command('economy:prune-imports')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('economy:sync-crypto-prices')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();
