<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Public;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\InvoiceController
 */
final class InvoiceControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_displays_view(): void
    {
        $invoices = Invoice::factory()->count(3)->create();

        $response = $this->get(route('invoices.index'));

        $response->assertOk();
        $response->assertViewIs('invoice.index');
        $response->assertViewHas('invoices', $invoices);
    }


    #[Test]
    public function create_displays_view(): void
    {
        $response = $this->get(route('invoices.create'));

        $response->assertOk();
        $response->assertViewIs('invoice.create');
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\InvoiceController::class,
            'store',
            \App\Http\Requests\InvoiceControllerStoreRequest::class
        );
    }

    #[Test]
    public function store_saves_and_redirects(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create();
        $public = Public::factory()->create();
        $status = fake()->word();
        $tax_treatment = fake()->word();
        $vat_rate = fake()->randomFloat(/** decimal_attributes **/);
        $subtotal_minor = fake()->numberBetween(-100000, 100000);
        $vat_minor = fake()->numberBetween(-100000, 100000);
        $total_minor = fake()->numberBetween(-100000, 100000);

        $response = $this->post(route('invoices.store'), [
            'company_id' => $company->id,
            'client_id' => $client->id,
            'public_id' => $public->id,
            'status' => $status,
            'tax_treatment' => $tax_treatment,
            'vat_rate' => $vat_rate,
            'subtotal_minor' => $subtotal_minor,
            'vat_minor' => $vat_minor,
            'total_minor' => $total_minor,
        ]);

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->where('client_id', $client->id)
            ->where('public_id', $public->id)
            ->where('status', $status)
            ->where('tax_treatment', $tax_treatment)
            ->where('vat_rate', $vat_rate)
            ->where('subtotal_minor', $subtotal_minor)
            ->where('vat_minor', $vat_minor)
            ->where('total_minor', $total_minor)
            ->get();
        $this->assertCount(1, $invoices);
        $invoice = $invoices->first();

        $response->assertRedirect(route('invoices.index'));
        $response->assertSessionHas('invoice.id', $invoice->id);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $invoice = Invoice::factory()->create();

        $response = $this->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertViewIs('invoice.show');
        $response->assertViewHas('invoice', $invoice);
    }


    #[Test]
    public function edit_displays_view(): void
    {
        $invoice = Invoice::factory()->create();

        $response = $this->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertViewIs('invoice.edit');
        $response->assertViewHas('invoice', $invoice);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\InvoiceController::class,
            'update',
            \App\Http\Requests\InvoiceControllerUpdateRequest::class
        );
    }

    #[Test]
    public function update_redirects(): void
    {
        $invoice = Invoice::factory()->create();
        $company = Company::factory()->create();
        $client = Client::factory()->create();
        $public = Public::factory()->create();
        $status = fake()->word();
        $tax_treatment = fake()->word();
        $vat_rate = fake()->randomFloat(/** decimal_attributes **/);
        $subtotal_minor = fake()->numberBetween(-100000, 100000);
        $vat_minor = fake()->numberBetween(-100000, 100000);
        $total_minor = fake()->numberBetween(-100000, 100000);

        $response = $this->put(route('invoices.update', $invoice), [
            'company_id' => $company->id,
            'client_id' => $client->id,
            'public_id' => $public->id,
            'status' => $status,
            'tax_treatment' => $tax_treatment,
            'vat_rate' => $vat_rate,
            'subtotal_minor' => $subtotal_minor,
            'vat_minor' => $vat_minor,
            'total_minor' => $total_minor,
        ]);

        $invoice->refresh();

        $response->assertRedirect(route('invoices.index'));
        $response->assertSessionHas('invoice.id', $invoice->id);

        $this->assertEquals($company->id, $invoice->company_id);
        $this->assertEquals($client->id, $invoice->client_id);
        $this->assertEquals($public->id, $invoice->public_id);
        $this->assertEquals($status, $invoice->status);
        $this->assertEquals($tax_treatment, $invoice->tax_treatment);
        $this->assertEquals($vat_rate, $invoice->vat_rate);
        $this->assertEquals($subtotal_minor, $invoice->subtotal_minor);
        $this->assertEquals($vat_minor, $invoice->vat_minor);
        $this->assertEquals($total_minor, $invoice->total_minor);
    }


    #[Test]
    public function destroy_deletes_and_redirects(): void
    {
        $invoice = Invoice::factory()->create();

        $response = $this->delete(route('invoices.destroy', $invoice));

        $response->assertRedirect(route('invoices.index'));

        $this->assertModelMissing($invoice);
    }
}
