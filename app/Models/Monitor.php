<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Monitor extends Model
{
    use HasFactory;
    protected $fillable = [
        'url',
        'check_interval',
        'threshold',
        'status',
        'last_checked_at',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'check_interval'  => 'integer',
        'threshold'       => 'integer',
    ];

    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    /**
     * Calculate uptime percentage from all recorded checks.
     * Returns null if no checks have been run yet.
     */
    public function getUptimePercentageAttribute(): ?float
    {
        $total = $this->checks()->count();

        if ($total === 0) {
            return null;
        }

        $up = $this->checks()->where('is_up', true)->count();

        return round(($up / $total) * 100, 2);
    }
}
