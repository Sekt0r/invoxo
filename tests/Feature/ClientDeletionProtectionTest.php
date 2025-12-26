<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientDeletionProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_can_soft_delete_client_with_invoices(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        // Soft delete should be allowed
        $response = $this->actingAs($user)->delete(route('clients.destroy', $client));
        $response->assertRedirect(route('clients.index'));

        // Client should be soft deleted
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_can_delete_client_without_invoices(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        // No invoices for this client

        // Soft delete should work
        $response = $this->actingAs($user)->delete(route('clients.destroy', $client));
        $response->assertRedirect(route('clients.index'));

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_cannot_force_delete_client_with_invoices(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'number' => 'INV-2025-001',
        ]);

        // Force delete should throw
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot permanently delete client');

        $client->forceDelete();
    }

    public function test_can_force_delete_client_without_invoices(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        // No invoices

        // Force delete should work
        $client->forceDelete();

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_soft_deleted_client_with_invoices_can_be_restored(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        // Soft delete
        $client->delete();
        $this->assertSoftDeleted('clients', ['id' => $client->id]);

        // Restore
        $client->restore();
        $client->refresh();

        $this->assertNull($client->deleted_at);
    }

    public function test_invoices_still_accessible_after_client_soft_deleted(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Test Client',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        // Soft delete client
        $client->delete();

        // Invoice should still be viewable
        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));
        $response->assertOk();

        // Invoice should still have client relationship (soft deleted FK)
        $invoice->refresh();
        $this->assertEquals($client->id, $invoice->client_id);

        // Client relationship should still be loadable (using withTrashed since client is soft-deleted)
        $loadedClient = Client::withTrashed()->find($invoice->client_id);
        $this->assertNotNull($loadedClient);
        $this->assertEquals('Test Client', $loadedClient->name);
    }
}
