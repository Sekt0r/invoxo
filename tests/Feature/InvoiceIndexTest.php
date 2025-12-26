<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_only_own_company_invoices(): void
    {
        $companyA = Company::factory()->create(['name' => 'Company A']);
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $clientA = Client::factory()->create([
            'company_id' => $companyA->id,
            'name' => 'Client A',
        ]);
        $invoiceA = Invoice::factory()->create([
            'company_id' => $companyA->id,
            'client_id' => $clientA->id,
            'number' => 'INV-A-001',
            'status' => 'issued',
        ]);

        $companyB = Company::factory()->create(['name' => 'Company B']);
        $clientB = Client::factory()->create([
            'company_id' => $companyB->id,
            'name' => 'Client B',
        ]);
        $invoiceB = Invoice::factory()->create([
            'company_id' => $companyB->id,
            'client_id' => $clientB->id,
            'number' => 'INV-B-001',
            'status' => 'issued',
        ]);

        $response = $this->actingAs($userA)->get(route('invoices.index'));

        $response->assertOk();
        $response->assertSee('INV-A-001');
        $response->assertSee('Client A');
        $response->assertDontSee('INV-B-001');
        $response->assertDontSee('Client B');
    }

    public function test_index_does_not_show_other_company_invoices(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $clientA = Client::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create([
            'company_id' => $companyB->id,
            'name' => 'Company B Client',
        ]);
        $invoiceB = Invoice::factory()->count(5)->create([
            'company_id' => $companyB->id,
            'client_id' => $clientB->id,
            'number' => 'INV-B-001',
        ]);

        $response = $this->actingAs($userA)->get(route('invoices.index'));

        $response->assertOk();
        // Should show empty state or only company A invoices
        // Verify no company B invoice data is present
        $response->assertDontSee('Company B Client');
        $response->assertDontSee('INV-B-001');
    }

}

