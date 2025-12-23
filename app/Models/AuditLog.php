<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    /**
     * Disable updated_at since we only have created_at
     */
    public $timestamps = false;

    protected $fillable = [
        'raiops_user_id',
        'action',
        'model_type',
        'model_id',
        'rds_instance_id',
        'tenant_master_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'source',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Boot method to set created_at automatically
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? now();
        });
    }

    /**
     * Relationship: User who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raiops_user_id');
    }

    /**
     * Relationship: RDS Instance (if applicable)
     */
    public function rdsInstance(): BelongsTo
    {
        return $this->belongsTo(RdsInstance::class, 'rds_instance_id');
    }

    /**
     * Relationship: Tenant (if applicable)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantMaster::class, 'tenant_master_id');
    }

    /**
     * Get the model that was affected
     */
    public function getSubject(): ?Model
    {
        if (!$this->model_type || !$this->model_id) {
            return null;
        }

        $class = "App\\Models\\{$this->model_type}";

        if (!class_exists($class)) {
            return null;
        }

        return $class::find($this->model_id);
    }

    /**
     * Get action badge class for UI
     */
    public function getActionBadgeClass(): string
    {
        return match ($this->action) {
            'created' => 'bg-success',
            'updated' => 'bg-info',
            'deleted' => 'bg-danger',
            'impersonated' => 'bg-warning',
            'logged_in' => 'bg-primary',
            'logged_out' => 'bg-secondary',
            default => 'bg-secondary',
        };
    }

    /**
     * Get formatted changes (diff between old and new values)
     */
    public function getChanges(): array
    {
        $changes = [];
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        // Get all keys from both arrays
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Static: Log an action
     */
    public static function log(
        string $action,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $rdsInstanceId = null,
        ?int $tenantMasterId = null,
        string $source = 'raiops'
    ): self {
        return static::create([
            'raiops_user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'rds_instance_id' => $rdsInstanceId,
            'tenant_master_id' => $tenantMasterId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'source' => $source,
        ]);
    }

    /**
     * Scope: By user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('raiops_user_id', $userId);
    }

    /**
     * Scope: By action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: By model type
     */
    public function scopeByModelType($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Scope: By date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Recent (last N days)
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

