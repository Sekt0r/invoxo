<?php
use App\Models\Company;
use App\Models\VatIdentity;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ValidateVatIdentityJob;

Queue::fake();

$vat = VatIdentity::factory()->create([
    'country_code' => 'DE',
    'vat_id' => 'DE987654325',
    'last_checked_at' => now()->subDays(60),
]);

$company = Company::factory()->create([
    'country_code' => 'DE',
    'vat_id' => 'DE987654325',
    'vat_identity_id' => $vat->id,
]);

// Baseline: update vat_id without setting the transient flag
$company->update(['vat_id' => 'DE111111111']);
Queue::pushed(ValidateVatIdentityJob::class)->count(); // expect 0 if observer requires vatIntentChanged

// Now: set the transient flag + save through the event that your observer listens to
Queue::fake();
$company = $company->fresh();

$company->vatIntentChanged = true;
$company->vat_id = 'DE222222222';
$company->save();

Queue::pushed(ValidateVatIdentityJob::class)->count(); // expect 1 if this is the gating condition
