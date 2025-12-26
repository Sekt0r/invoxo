<?php

namespace App\Providers;

use App\Contracts\VatProvider;
use App\Contracts\VatRatesProviderInterface;
use App\Contracts\VatValidationProviderInterface;
use App\Services\Vat\ApilayerVatProvider;
use App\Services\Vat\ApilayerVatRatesProvider;
use App\Services\Vat\ApilayerVatValidationProvider;
use App\Services\Vat\FakeVatProvider;
use App\Services\Vat\FakeVatRatesProvider;
use App\Services\Vat\FakeVatValidationProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind legacy VatProvider for backwards compatibility (if still needed)
        $this->app->bind(VatProvider::class, function () {
            if (app()->environment('testing')) {
                return new FakeVatProvider();
            }

            return new ApilayerVatProvider();
        });

        // Bind new VAT provider interfaces based on environment
        if (app()->environment('testing')) {
            $this->app->bind(VatRatesProviderInterface::class, FakeVatRatesProvider::class);
            $this->app->bind(VatValidationProviderInterface::class, FakeVatValidationProvider::class);
        } else {
            $this->app->bind(VatRatesProviderInterface::class, ApilayerVatRatesProvider::class);
            $this->app->bind(VatValidationProviderInterface::class, ApilayerVatValidationProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Company::observe(\App\Observers\CompanyObserver::class);
        // BankAccount delete auditing is handled in BankAccount::booted() method
        // Only observe created/updated events for BankAccount
        \App\Models\BankAccount::observe(\App\Observers\BankAccountObserver::class);
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);
    }
}
