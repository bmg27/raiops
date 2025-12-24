<?php

namespace App\Models;

use App\Models\TenantMaster;
use Illuminate\Database\Eloquent\Model;

class CommandPreset extends Model
{
    protected $fillable = [
        'name',
        'description',
        'commands',
        'is_chain',
        'created_by',
        'tenant_master_id',
        'archived_at',
        'last_run_at',
        'run_count',
    ];

    protected $casts = [
        'commands' => 'array',
        'is_chain' => 'boolean',
        'archived_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
    
    public function tenantMaster()
    {
        return $this->belongsTo(TenantMaster::class);
    }
    
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }
    
    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }
    
    public function archive()
    {
        $this->update(['archived_at' => now()]);
    }
    
    public function unarchive()
    {
        $this->update(['archived_at' => null]);
    }
    
    public function isArchived()
    {
        return !is_null($this->archived_at);
    }
    
    public function recordRun()
    {
        $this->increment('run_count');
        $this->update(['last_run_at' => now()]);
    }
}
