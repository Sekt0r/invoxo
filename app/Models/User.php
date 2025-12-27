<?php

namespace App\Models;

use App\Plans\PlanPermissionChecker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the current request IP address for audit logging.
     * Returns null if not in HTTP context (CLI, tests, etc.)
     */
    public function currentIp(): ?string
    {
        try {
            return request()?->ip();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check if this user has a given plan permission.
     *
     * The user has a permission only if:
     * - They have an active subscription
     * - The subscription's plan grants the permission (based on config/plans.php)
     *
     * @param string $permissionKey Permission key (e.g., 'vies_validation')
     * @return bool True if the user has the permission, false otherwise
     */
    public function hasPlanPermission(string $permissionKey): bool
    {
        if (!$this->company) {
            return false;
        }

        $subscription = $this->company->activeSubscription();

        if (!$subscription) {
            return false;
        }

        $checker = app(PlanPermissionChecker::class);

        return $checker->planGrantsPermission($subscription->plan, $permissionKey);
    }
}
