<?php

namespace App\Data;

use Carbon\Carbon;

final readonly class ViesResult
{
    public function __construct(
        public string $status, // valid|invalid|unknown
        public ?string $name = null,
        public ?string $address = null,
        public ?Carbon $validatedAt = null,
    ) {
    }
}
