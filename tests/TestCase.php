<?php

namespace Tests;

use App\Contracts\VatRatesProviderInterface;
use App\Contracts\VatValidationProviderInterface;
use App\Services\Vat\FakeVatRatesProvider;
use App\Services\Vat\FakeVatValidationProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure fake providers are always bound in tests
        $this->app->bind(VatRatesProviderInterface::class, FakeVatRatesProvider::class);
        $this->app->bind(VatValidationProviderInterface::class, FakeVatValidationProvider::class);
    }
}
