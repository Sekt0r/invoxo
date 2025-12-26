<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceMarkPaidTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_mark_issued_invoice_as_paid(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.markPaid', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_cannot_mark_draft_as_paid(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.markPaid', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals('draft', $invoice->status);
    }

    public function test_other_company_forbidden(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create(['company_id' => $companyB->id]);
        $invoiceB = Invoice::factory()->create([
            'company_id' => $companyB->id,
            'client_id' => $clientB->id,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($userA)->post(route('invoices.markPaid', $invoiceB));

        $response->assertStatus(403);

        $invoiceB->refresh();
        $this->assertEquals('issued', $invoiceB->status);
    }
}





