<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_index_only_shows_own_company(): void
    {
        $companyA = Company::factory()->create(['name' => 'Company A']);
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $clientA = Client::factory()->create([
            'company_id' => $companyA->id,
            'name' => 'Client A',
        ]);

        $companyB = Company::factory()->create(['name' => 'Company B']);
        $clientB = Client::factory()->create([
            'company_id' => $companyB->id,
            'name' => 'Client B',
        ]);

        $response = $this->actingAs($userA)->get(route('clients.index'));

        $response->assertOk();
        $response->assertViewIs('client.index');
        $response->assertSee('Client A');
        $response->assertDontSee('Client B');
    }

    public function test_client_create_works_for_owner(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post(route('clients.store'), [
            'name' => 'Test Client',
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        $response->assertRedirect(route('clients.index'));
        $response->assertSessionHas('client.id');

        $client = Client::where('company_id', $company->id)
            ->where('name', 'Test Client')
            ->first();

        $this->assertNotNull($client);
        $this->assertEquals($company->id, $client->company_id);
        $this->assertEquals('DE', $client->country_code);
        $this->assertEquals('DE123456789', $client->vat_id);
    }

    public function test_client_update_works_for_owner(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Old Name',
            'country_code' => 'DE',
        ]);

        $response = $this->actingAs($user)->put(route('clients.update', $client), [
            'name' => 'New Name',
            'country_code' => 'FR',
            'vat_id' => 'FR987654321',
        ]);

        $response->assertRedirect(route('clients.index'));
        $response->assertSessionHas('client.id');

        $client->refresh();
        $this->assertEquals('New Name', $client->name);
        $this->assertEquals('FR', $client->country_code);
        $this->assertEquals('FR987654321', $client->vat_id);
        $this->assertEquals($company->id, $client->company_id); // Should not change
    }

    public function test_client_show_other_company_forbidden(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create(['company_id' => $companyB->id]);

        $response = $this->actingAs($userA)->get(route('clients.show', $clientB));

        $response->assertStatus(403);
    }

    public function test_client_edit_other_company_forbidden(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create(['company_id' => $companyB->id]);

        $response = $this->actingAs($userA)->get(route('clients.edit', $clientB));

        $response->assertStatus(403);
    }

    public function test_client_update_other_company_forbidden(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create([
            'company_id' => $companyB->id,
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($userA)->put(route('clients.update', $clientB), [
            'name' => 'Hacked Name',
            'country_code' => 'GB',
            'vat_id' => null,
        ]);

        $response->assertStatus(403);

        $clientB->refresh();
        $this->assertEquals('Original Name', $clientB->name);
    }

    public function test_client_destroy_other_company_forbidden(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create(['company_id' => $companyB->id]);

        $response = $this->actingAs($userA)->delete(route('clients.destroy', $clientB));

        $response->assertStatus(403);

        $this->assertNotSoftDeleted($clientB);
    }

    public function test_client_store_rejects_invalid_country_code(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post(route('clients.store'), [
            'name' => 'Test Client',
            'country_code' => 'XX', // Invalid country code
            'vat_id' => null,
        ]);

        $response->assertSessionHasErrors('country_code');
        $response->assertRedirect();

        // Verify client was not created
        $this->assertDatabaseMissing('clients', [
            'company_id' => $company->id,
            'name' => 'Test Client',
        ]);
    }

    public function test_client_create_view_renders_datalist(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->get(route('clients.create'));

        $response->assertOk();
        $response->assertSee('id="country-options"', false);
        $response->assertSee('value="RO"', false);
        $response->assertSee('Romania', false);
        $response->assertSee('value="DE"', false);
        $response->assertSee('Germany', false);
    }
}


