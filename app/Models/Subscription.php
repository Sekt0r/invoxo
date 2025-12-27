<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'plan',
        'starts_at',
        'ends_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'starts_at' => 'timestamp',
            'ends_at' => 'timestamp',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Check if this subscription is currently active.
     *
     * A subscription is active if:
     * - starts_at <= now
     * - ends_at is null OR ends_at > now
     *
     * @param \DateTimeInterface|null $when Optional time to check against (defaults to now)
     * @return bool
     */
    public function isActive(?\DateTimeInterface $when = null): bool
    {
        $now = $when ? Carbon::instance($when) : Carbon::now();

        // Ensure starts_at and ends_at are Carbon instances for comparison
        $startsAt = $this->starts_at instanceof Carbon ? $this->starts_at : Carbon::parse($this->starts_at);
        $endsAt = $this->ends_at ? ($this->ends_at instanceof Carbon ? $this->ends_at : Carbon::parse($this->ends_at)) : null;

        if ($startsAt->gt($now)) {
            return false;
        }

        if ($endsAt !== null && $endsAt->lte($now)) {
            return false;
        }

        return true;
    }
}
