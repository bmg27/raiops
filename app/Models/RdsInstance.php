<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class RdsInstance extends Model
{
    protected $table = 'rds_instances';

    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'rai_database',
        'providers_database',
        'app_url',
        'is_active',
        'is_master',
        'health_status',
        'last_health_check_at',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_master' => 'boolean',
        'port' => 'integer',
        'last_health_check_at' => 'datetime',
    ];

    /**
     * Hidden attributes (never expose password in JSON/arrays)
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Accessor: Decrypt password when retrieving
     */
    public function getPasswordAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // If decryption fails, return null (password may be corrupted)
            return null;
        }
    }

    /**
     * Mutator: Encrypt password when setting
     */
    public function setPasswordAttribute($value): void
    {
        if ($value === '' || $value === null) {
            // Store encrypted empty string for databases with no password
            $this->attributes['password'] = Crypt::encryptString('');
        } else {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Relationship: Tenants on this RDS instance
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(TenantMaster::class, 'rds_instance_id');
    }

    /**
     * Get connection configuration array for this RDS instance
     */
    public function getConnectionConfig(): array
    {
        return [
            'driver' => 'mysql',
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password, // Uses decrypted accessor
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                \PDO::ATTR_EMULATE_PREPARES => true,
            ]) : [],
        ];
    }

    /**
     * Get RAI database connection config
     */
    public function getRaiConnectionConfig(): array
    {
        return array_merge($this->getConnectionConfig(), [
            'database' => $this->rai_database,
        ]);
    }

    /**
     * Get providers database connection config
     */
    public function getProvidersConnectionConfig(): array
    {
        return array_merge($this->getConnectionConfig(), [
            'database' => $this->providers_database,
        ]);
    }

    /**
     * Test connection to this RDS instance
     * 
     * @return array{success: bool, message: string, latency_ms: int|null}
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);

        try {
            $connectionName = 'rds_test_' . $this->id;

            // Configure temporary connection
            config(["database.connections.{$connectionName}" => $this->getRaiConnectionConfig()]);

            // Try to connect and run a simple query
            DB::connection($connectionName)->getPdo();
            DB::connection($connectionName)->select('SELECT 1');

            $latency = round((microtime(true) - $startTime) * 1000);

            // Clean up
            DB::purge($connectionName);

            return [
                'success' => true,
                'message' => 'Connection successful',
                'latency_ms' => $latency,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'latency_ms' => null,
            ];
        }
    }

    /**
     * Update health status based on connection test
     */
    public function updateHealthStatus(): void
    {
        $result = $this->testConnection();

        $this->update([
            'health_status' => $result['success'] ? 'healthy' : 'down',
            'last_health_check_at' => now(),
        ]);
    }

    /**
     * Scope: Only active instances
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get the master RDS instance
     */
    public function scopeMaster($query)
    {
        return $query->where('is_master', true);
    }

    /**
     * Get the master RDS instance
     */
    public static function getMaster(): ?self
    {
        return static::master()->first();
    }

    /**
     * Get health status badge class for UI
     */
    public function getHealthBadgeClass(): string
    {
        return match ($this->health_status) {
            'healthy' => 'bg-success',
            'degraded' => 'bg-warning',
            'down' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    /**
     * Get health status icon for UI
     */
    public function getHealthIcon(): string
    {
        return match ($this->health_status) {
            'healthy' => 'bi-check-circle-fill',
            'degraded' => 'bi-exclamation-triangle-fill',
            'down' => 'bi-x-circle-fill',
            default => 'bi-question-circle-fill',
        };
    }
}

