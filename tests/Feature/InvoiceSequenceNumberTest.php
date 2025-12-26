<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSequence;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\InvoiceNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceSequenceNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time for deterministic date-based sequencing
        Carbon::setTestNow('2025-01-15 12:00:00');
    }

    protected function tearDown(): void
    {
        // Unfreeze time after each test
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_assigns_number_using_sequence_table(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'invoice_prefix' => 'INV-',
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
            'registration_number' => 'RO123456',
            'name' => 'Test Company',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'RO', // Same country = DOMESTIC
            'vat_id' => null, // No VAT ID for DOMESTIC
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        // Create bank account for issuance requirement (must match invoice currency)
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        // Check for session errors if issuance failed
        if ($response->getSession()->has('errors')) {
            $errors = $response->getSession()->get('errors');
            $this->fail('Invoice issuance failed with errors: ' . json_encode($errors->toArray()));
        }

        $invoice->refresh();
        $this->assertEquals('issued', $invoice->status);
        $this->assertNotNull($invoice->number);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', $invoice->number);

        // Verify sequence row was created
        // The prefix is normalized from 'INV-' to 'INV' by InvoiceNumberService
        // Use the year from the invoice's issue_date (or current year if null)
        $invoiceYear = $invoice->issue_date ? \Carbon\Carbon::parse($invoice->issue_date)->year : now()->year;
        $sequence = InvoiceSequence::where('company_id', $company->id)
            ->where('year', $invoiceYear)
            ->where('prefix', 'INV')
            ->first();

        // If not found with 'INV', try 'INV-' (in case normalization didn't happen)
        if (!$sequence) {
            $sequence = InvoiceSequence::where('company_id', $company->id)
                ->where('year', $invoiceYear)
                ->where('prefix', 'INV-')
                ->first();
        }

        // Debug: If sequence not found, list all sequences for this company
        if (!$sequence) {
            $allSequences = InvoiceSequence::where('company_id', $company->id)->get();
            $this->fail("Invoice sequence not found. Company ID: {$company->id}, Year: {$invoiceYear}, Prefix: INV. Invoice number: {$invoice->number}. All sequences for company: " . $allSequences->toJson());
        }
        $this->assertNotNull($sequence);
        $this->assertEquals(1, $sequence->last_number);
    }

    public function test_uses_issue_date_year(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'invoice_prefix' => 'INV-',
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
            'registration_number' => 'RO123456',
            'name' => 'Test Company',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'RO', // Same country = DOMESTIC
            'vat_id' => null, // No VAT ID for DOMESTIC
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'issue_date' => \Carbon\Carbon::parse('2024-12-31')->toDateString(), // Past year
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        // Create bank account for issuance requirement (must match invoice currency)
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        // Check for session errors if issuance failed
        if ($response->getSession()->has('errors')) {
            $errors = $response->getSession()->get('errors');
            $this->fail('Invoice issuance failed with errors: ' . json_encode($errors->toArray()));
        }

        $invoice->refresh();
        $this->assertEquals('issued', $invoice->status);
        $this->assertNotNull($invoice->number);
        $this->assertStringContainsString('2024', $invoice->number);
        $this->assertMatchesRegularExpression('/^INV-2024-\d{6}$/', $invoice->number);

        // Verify sequence row was created for year 2024, not current year
        // Use the year from the invoice's issue_date
        $invoiceYear = $invoice->issue_date ? \Carbon\Carbon::parse($invoice->issue_date)->year : now()->year;
        $sequence = InvoiceSequence::where('company_id', $company->id)
            ->where('year', $invoiceYear)
            ->where('prefix', 'INV')
            ->first();

        // Debug: If sequence not found, list all sequences for this company
        if (!$sequence) {
            $allSequences = InvoiceSequence::where('company_id', $company->id)->get();
            $this->fail("Invoice sequence not found. Company ID: {$company->id}, Year: {$invoiceYear}, Prefix: INV. Invoice number: {$invoice->number}. All sequences for company: " . $allSequences->toJson());
        }

        $this->assertNotNull($sequence);
        $this->assertEquals(1, $sequence->last_number);

        // Verify no sequence exists for current year
        $currentYearSequence = InvoiceSequence::where('company_id', $company->id)
            ->where('year', now()->year)
            ->where('prefix', 'INV')
            ->first();

        $this->assertNull($currentYearSequence);
    }

    public function test_consecutive_issues_increment_sequence(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'invoice_prefix' => 'INV-',
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
            'registration_number' => 'RO123456',
            'name' => 'Test Company',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'RO', // Same country = DOMESTIC
            'vat_id' => null, // No VAT ID for DOMESTIC
        ]);

        $invoice1 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice1->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $invoice2 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR',
            'issue_date' => null, // Will be set to today when issued
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice2->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        // Create bank account for issuance requirement (must match invoice currency)
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        // Issue first invoice
        $response1 = $this->actingAs($user)->post(route('invoices.issue', $invoice1));
        $response1->assertRedirect();

        $invoice1->refresh();
        $this->assertEquals('issued', $invoice1->status);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-000001$/', $invoice1->number);

        // Issue second invoice
        $response2 = $this->actingAs($user)->post(route('invoices.issue', $invoice2));
        $response2->assertRedirect();

        $invoice2->refresh();
        $this->assertEquals('issued', $invoice2->status);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-000002$/', $invoice2->number);

        // Verify sequence was incremented
        // Use the year from invoice2's issue_date
        $invoiceYear = $invoice2->issue_date ? \Carbon\Carbon::parse($invoice2->issue_date)->year : now()->year;
        $sequence = InvoiceSequence::where('company_id', $company->id)
            ->where('year', $invoiceYear)
            ->where('prefix', 'INV')
            ->first();

        // Debug: If sequence not found, list all sequences for this company
        if (!$sequence) {
            $allSequences = InvoiceSequence::where('company_id', $company->id)->get();
            $this->fail("Invoice sequence not found. Company ID: {$company->id}, Year: {$invoiceYear}, Prefix: INV. Invoice2 number: {$invoice2->number}. All sequences for company: " . $allSequences->toJson());
        }

        $this->assertNotNull($sequence);
        $this->assertEquals(2, $sequence->last_number);
    }

    public function test_no_gaps_on_retry_idempotent_issue(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'invoice_prefix' => 'INV-',
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
            'registration_number' => 'RO123456',
            'name' => 'Test Company',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'RO', // Same country = DOMESTIC
            'vat_id' => null, // No VAT ID for DOMESTIC
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        // Create bank account for issuance requirement (must match invoice currency)
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        // Issue invoice first time
        $response1 = $this->actingAs($user)->post(route('invoices.issue', $invoice));
        $response1->assertRedirect();

        $invoice->refresh();
        $firstNumber = $invoice->number;
        $this->assertEquals('issued', $invoice->status);
        $this->assertNotNull($firstNumber);

        // Get sequence after first issue
        // Use the year from the invoice's issue_date
        $invoiceYear = $invoice->issue_date ? \Carbon\Carbon::parse($invoice->issue_date)->year : now()->year;
        $sequenceAfterFirst = InvoiceSequence::where('company_id', $company->id)
            ->where('year', $invoiceYear)
            ->where('prefix', 'INV')
            ->first();

        // Debug: If sequence not found, list all sequences for this company
        if (!$sequenceAfterFirst) {
            $allSequences = InvoiceSequence::where('company_id', $company->id)->get();
            $this->fail("Invoice sequence not found after first issue. Company ID: {$company->id}, Year: {$invoiceYear}, Prefix: INV. Invoice number: {$firstNumber}. All sequences for company: " . $allSequences->toJson());
        }
        $this->assertNotNull($sequenceAfterFirst);
        $this->assertEquals(1, $sequenceAfterFirst->last_number);

        // Try to issue again (idempotent - should not change number or sequence)
        $response2 = $this->actingAs($user)->post(route('invoices.issue', $invoice));
        $response2->assertRedirect();

        $invoice->refresh();
        $secondNumber = $invoice->number;

        // Number should remain the same
        $this->assertEquals($firstNumber, $secondNumber);

        // Sequence should not be incremented
        // Use the year from the invoice's issue_date (same as above)
        $sequenceAfterSecond = InvoiceSequence::where('company_id', $company->id)
            ->where('year', $invoiceYear)
            ->where('prefix', 'INV')
            ->first();
        $this->assertNotNull($sequenceAfterSecond, "Sequence should still exist. Company ID: {$company->id}, Year: {$invoiceYear}");
        $this->assertEquals(1, $sequenceAfterSecond->last_number);
    }

    public function test_invoice_number_service_uses_issue_date_year(): void
    {
        $company = Company::factory()->create([
            'invoice_prefix' => 'INV-',
            'registration_number' => 'RO123456',
        ]);

        $service = new InvoiceNumberService();

        // Test with past date
        $pastDate = '2023-06-15';
        $number = $service->nextNumber($company, $pastDate);
        $this->assertStringContainsString('2023', $number);
        $this->assertMatchesRegularExpression('/^INV-2023-000001$/', $number);

        // Test with future date
        $futureDate = '2026-12-31';
        $number2 = $service->nextNumber($company, $futureDate);
        $this->assertStringContainsString('2026', $number2);
        $this->assertMatchesRegularExpression('/^INV-2026-000001$/', $number2);

        // Verify separate sequences for different years
        $sequence2023 = InvoiceSequence::where('company_id', $company->id)
            ->where('year', 2023)
            ->where('prefix', 'INV')
            ->first();
        $this->assertNotNull($sequence2023);
        $this->assertEquals(1, $sequence2023->last_number);

        $sequence2026 = InvoiceSequence::where('company_id', $company->id)
            ->where('year', 2026)
            ->where('prefix', 'INV')
            ->first();
        $this->assertNotNull($sequence2026);
        $this->assertEquals(1, $sequence2026->last_number);
    }
}
