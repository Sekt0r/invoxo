<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('vat:sync-rates')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('fx:sync-ecb')
    ->dailyAt('16:30')
    ->timezone('Europe/Bucharest')
    ->withoutOverlapping()
    ->onOneServer();
