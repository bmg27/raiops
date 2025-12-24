<?php

namespace App\Models;

use App\Models\TenantMaster;
use App\Models\RdsInstance;
use Illuminate\Database\Eloquent\Model;

class CommandExecution extends Model
{
    /**
     * Always use the RAIOPS database connection (not RDS)
     */
    protected $connection = 'mysql';

    protected $fillable = [
        'command_name',
        'raiops_user_id',
        'tenant_master_id',
        'rds_instance_id',
        'triggered_by',
        'status',
        'process_id',
        'current_step',
        'total_steps',
        'completed_steps',
        'output',
        'error',
        'started_at',
        'completed_at',
        'retry_enabled',
        'is_retry_attempt',
        'retry_count',
        'original_execution_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'retry_enabled' => 'boolean',
        'is_retry_attempt' => 'boolean',
    ];

    public function getProgressPercentageAttribute()
    {
        if ($this->total_steps == 0) {
            return 0;
        }
        return round(($this->completed_steps / $this->total_steps) * 100);
    }

    public function isRunning()
    {
        return $this->status === 'running';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'raiops_user_id');
    }
    
    public function tenantMaster()
    {
        return $this->belongsTo(TenantMaster::class);
    }
    
    public function rdsInstance()
    {
        return $this->belongsTo(RdsInstance::class);
    }
    
    public function getTriggeredByLabelAttribute()
    {
        return match($this->triggered_by) {
            'cron' => 'Scheduled (Cron)',
            'api' => 'API',
            'manual' => 'Manual',
            default => ucfirst($this->triggered_by),
        };
    }
}
