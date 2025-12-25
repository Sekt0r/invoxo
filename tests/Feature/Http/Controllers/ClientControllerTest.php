<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\ClientController
 */
final class ClientControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_displays_view(): void
    {
        $clients = Client::factory()->count(3)->create();

        $response = $this->get(route('clients.index'));

        $response->assertOk();
        $response->assertViewIs('client.index');
        $response->assertViewHas('clients', $clients);
    }


    #[Test]
    public function create_displays_view(): void
    {
        $response = $this->get(route('clients.create'));

        $response->assertOk();
        $response->assertViewIs('client.create');
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ClientController::class,
            'store',
            \App\Http\Requests\ClientControllerStoreRequest::class
        );
    }

    #[Test]
    public function store_saves_and_redirects(): void
    {
        $company = Company::factory()->create();
        $name = fake()->name();
        $country_code = fake()->randomLetter();

        $response = $this->post(route('clients.store'), [
            'company_id' => $company->id,
            'name' => $name,
            'country_code' => $country_code,
        ]);

        $clients = Client::query()
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->where('country_code', $country_code)
            ->get();
        $this->assertCount(1, $clients);
        $client = $clients->first();

        $response->assertRedirect(route('clients.index'));
        $response->assertSessionHas('client.id', $client->id);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $client = Client::factory()->create();

        $response = $this->get(route('clients.show', $client));

        $response->assertOk();
        $response->assertViewIs('client.show');
        $response->assertViewHas('client', $client);
    }


    #[Test]
    public function edit_displays_view(): void
    {
        $client = Client::factory()->create();

        $response = $this->get(route('clients.edit', $client));

        $response->assertOk();
        $response->assertViewIs('client.edit');
        $response->assertViewHas('client', $client);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ClientController::class,
            'update',
            \App\Http\Requests\ClientControllerUpdateRequest::class
        );
    }

    #[Test]
    public function update_redirects(): void
    {
        $client = Client::factory()->create();
        $company = Company::factory()->create();
        $name = fake()->name();
        $country_code = fake()->randomLetter();

        $response = $this->put(route('clients.update', $client), [
            'company_id' => $company->id,
            'name' => $name,
            'country_code' => $country_code,
        ]);

        $client->refresh();

        $response->assertRedirect(route('clients.index'));
        $response->assertSessionHas('client.id', $client->id);

        $this->assertEquals($company->id, $client->company_id);
        $this->assertEquals($name, $client->name);
        $this->assertEquals($country_code, $client->country_code);
    }


    #[Test]
    public function destroy_deletes_and_redirects(): void
    {
        $client = Client::factory()->create();

        $response = $this->delete(route('clients.destroy', $client));

        $response->assertRedirect(route('clients.index'));

        $this->assertSoftDeleted($client);
    }
}
