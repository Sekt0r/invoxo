<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\VatIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Test Client',
            'country_code' => 'GB',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Clients');
        $response->assertSee('Test Client');
    }

    public function test_shows_total_client_count(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        Client::factory()->count(3)->create([
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('3 clients');
        $response->assertViewHas('clientsCount', 3);
    }

    public function test_shows_singular_client_count(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        Client::factory()->create([
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('1 client');
        $response->assertViewHas('clientsCount', 1);
    }

    public function test_correct_number_of_clients_shown(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        Client::factory()->count(7)->create([
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('recentClients', function ($clients) {
            return $clients->count() === 5;
        });
        $response->assertViewHas('clientsCount', 7);
    }

    public function test_soft_deleted_clients_excluded(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $activeClient = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Active Client',
        ]);

        $deletedClient = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Deleted Client',
        ]);

        $deletedClient->delete();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Active Client');
        $response->assertDontSee('Deleted Client');
        $response->assertViewHas('clientsCount', 1);
        $response->assertViewHas('recentClients', function ($clients) use ($activeClient) {
            return $clients->count() === 1
                && $clients->first()->id === $activeClient->id;
        });
    }

    public function test_tenant_isolation_enforced(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $client1 = Client::factory()->create([
            'company_id' => $user1->company->id,
            'name' => 'User1 Client',
        ]);

        $client2 = Client::factory()->create([
            'company_id' => $user2->company->id,
            'name' => 'User2 Client',
        ]);

        $response = $this->actingAs($user1)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('User1 Client');
        $response->assertDontSee('User2 Client');
        $response->assertViewHas('clientsCount', 1);
        $response->assertViewHas('recentClients', function ($clients) use ($client1) {
            return $clients->count() === 1
                && $clients->first()->id === $client1->id;
        });
    }

    public function test_clients_ordered_by_updated_at_desc(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $oldClient = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Old Client',
            'updated_at' => now()->subDays(5),
        ]);

        $newClient = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'New Client',
            'updated_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('recentClients', function ($clients) use ($newClient, $oldClient) {
            // New client should come first (most recently updated)
            return $clients->first()->id === $newClient->id
                && $clients->last()->id === $oldClient->id;
        });
    }

    public function test_displays_vat_status_valid_badge(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $vatIdentity = VatIdentity::factory()->create([
            'status' => 'valid',
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'vat_identity_id' => $vatIdentity->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Valid', false);
        $response->assertSee('bg-green-100 text-green-800');
    }

    public function test_displays_vat_status_invalid_badge(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $vatIdentity = VatIdentity::factory()->create([
            'status' => 'invalid',
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'vat_identity_id' => $vatIdentity->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Invalid', false);
        $response->assertSee('bg-red-100 text-red-800');
    }

    public function test_displays_vat_status_pending_badge(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $vatIdentity = VatIdentity::factory()->create([
            'status' => 'pending',
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'vat_identity_id' => $vatIdentity->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Pending', false);
        $response->assertSee('bg-yellow-100 text-yellow-800');
    }

    public function test_displays_vat_status_none_when_no_vat_identity(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'vat_identity_id' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('None', false);
        $response->assertSee('bg-gray-100 text-gray-800');
    }

    public function test_each_row_links_to_client_show_page(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Test Client',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('clients.show', $client));
        $response->assertSee('Test Client');
    }

    public function test_quick_actions_displayed(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        Client::factory()->create([
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('clients.create'));
        $response->assertSee(route('clients.index'));
        $response->assertSee('Add Client', false);
        $response->assertSee('View All Clients', false);
    }

    public function test_vat_status_displays_for_all_statuses(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $validVat = VatIdentity::factory()->create(['status' => 'valid']);
        $invalidVat = VatIdentity::factory()->create(['status' => 'invalid']);
        $pendingVat = VatIdentity::factory()->create(['status' => 'pending']);
        $unknownVat = VatIdentity::factory()->create(['status' => 'unknown']);

        Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Valid Client',
            'vat_identity_id' => $validVat->id,
        ]);

        Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Invalid Client',
            'vat_identity_id' => $invalidVat->id,
        ]);

        Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Pending Client',
            'vat_identity_id' => $pendingVat->id,
        ]);

        Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Unknown Client',
            'vat_identity_id' => $unknownVat->id,
        ]);

        Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'No VAT Client',
            'vat_identity_id' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Valid', false);
        $response->assertSee('Invalid', false);
        $response->assertSee('Pending', false);
        $response->assertSee('None', false);
    }
}






