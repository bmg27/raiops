<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantInvitation extends Model
{
    protected $fillable = [
        'tenant_id',
        'email',
        'invitation_token',
        'first_name',
        'last_name',
        'expires_at',
        'accepted_at',
        'response_data',
        'status',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'response_data' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->invitation_token)) {
                $invitation->invitation_token = Str::random(64);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function getInvitationUrl(): string
    {
        // If RAI URL is configured, use it; otherwise return a placeholder
        $raiUrl = config('app.rai_url');
        if ($raiUrl) {
            return rtrim($raiUrl, '/') . '/tenant/register/' . $this->invitation_token;
        }
        
        // Fallback: try to use route if it exists (for future implementation)
        try {
            return route('tenant.register', ['token' => $this->invitation_token]);
        } catch (\Exception $e) {
            // Route doesn't exist yet, return placeholder
            return '#tenant-registration-not-configured';
        }
    }
}

