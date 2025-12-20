<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stub model for locations
 * TODO: Copy full implementation from RAI when needed
 */
class SevenLocation extends Model
{
    use HasFactory;

    protected $table = 'seven_locations';

    protected $fillable = [
        'api_location_id',
        'location_id',
        'tenant_id',
        'name',
        'alias',
        'address',
        'city',
        'state',
        'country',
        'hasResy',
        'groupTips',
        'active',
        'resy_url',
        'resy_api_key',
        'toast_location',
        'toast_sftp_id',
    ];

    protected $casts = [
        'hasResy' => 'boolean',
        'groupTips' => 'boolean',
        'active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}

