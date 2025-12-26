<?php

namespace App\Data;

use Carbon\Carbon;

final class VatValidationResult
{
    public function __construct(
        public readonly string $status, // 'valid' | 'invalid' | 'pending' | 'unknown'
        public readonly ?string $companyName = null,
        public readonly ?string $companyAddress = null,
        public readonly ?Carbon $checkedAt = null,
    ) {
    }
}

