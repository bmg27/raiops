<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledCommand extends Model
{
    protected $fillable = [
        'command_name',
        'display_name',
        'description',
        'category',
        'required_integration',
        'default_params',
        'requires_tenant',
        'is_active',
        'sort_order',
        'default_enabled',
    ];

    protected $casts = [
        'default_params' => 'array',
        'requires_tenant' => 'boolean',
        'is_active' => 'boolean',
        'default_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope to get only active commands
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get commands for default schedule
     */
    public function scopeDefaultEnabled($query)
    {
        return $query->where('default_enabled', true);
    }

    /**
     * Scope to filter by required integration
     */
    public function scopeForIntegration($query, ?string $integration)
    {
        if ($integration === null) {
            return $query->whereNull('required_integration');
        }
        return $query->where('required_integration', $integration);
    }

    /**
     * Scope to filter by category
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Build the full command string with parameters
     * 
     * @param int|null $tenantId RAI tenant_id (on RDS), not tenant_master_id
     * @param array $overrideParams Parameters to override defaults
     * @param bool $resolveDynamicDates Whether to resolve date placeholders (e.g., {date:-3days})
     * @return string
     */
    public function buildCommand(?int $tenantId = null, array $overrideParams = [], bool $resolveDynamicDates = true): string
    {
        $command = $this->command_name;
        
        // Get default params and resolve dynamic dates if needed
        $defaultParams = $this->default_params ?? [];
        if ($resolveDynamicDates) {
            $defaultParams = $this->resolveDynamicDates($defaultParams);
        }
        
        // Merge default params with overrides (overrides take precedence)
        $params = array_merge($defaultParams, $overrideParams);
        
        // Add parameters to command
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $command .= " --{$key}";
                }
            } else {
                $command .= " --{$key}={$value}";
            }
        }
        
        // Add tenant flag if required and tenant ID provided
        // Note: tenantId here is the RAI tenant_id, not tenant_master_id
        if ($this->requires_tenant && $tenantId && strpos($command, '--tenant=') === false) {
            $command .= " --tenant={$tenantId}";
        }
        
        return $command;
    }

    /**
     * Get all active commands for display in RAIOPS UI
     * Note: RAI will filter commands based on tenant integrations at execution time
     * 
     * @param int|null $tenantMasterId The RAIOPS tenant_master_id (unused, kept for API compatibility)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getForTenantMaster(?int $tenantMasterId = null): \Illuminate\Database\Eloquent\Collection
    {
        // Return all active default-enabled commands
        // RAI's custom:schedule will filter based on actual tenant integrations
        return self::active()
            ->defaultEnabled()
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();
    }
    
    /**
     * Get all active commands (not just default-enabled)
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllActive(): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();
    }

    /**
     * Resolve dynamic date placeholders in parameters
     * Supports formats like: {date:-3days}, {date:today}, {date:Ymd:-2days}
     * 
     * @param array $params
     * @return array
     */
    protected function resolveDynamicDates(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_string($value) && preg_match('/\{date:([^}]+)\}/', $value, $matches)) {
                $dateExpr = $matches[1];
                
                // Parse format like "Ymd:-3days" or just "-3days"
                if (strpos($dateExpr, ':') !== false) {
                    [$format, $expr] = explode(':', $dateExpr, 2);
                } else {
                    $format = 'Ymd';
                    $expr = $dateExpr;
                }
                
                // Handle special cases
                if ($expr === 'today') {
                    $date = now();
                } elseif ($expr === 'yesterday') {
                    $date = now()->subDay();
                } else {
                    // Parse expressions like "-3days", "+1week", etc.
                    $date = now()->modify($expr);
                }
                
                $params[$key] = $date->format($format);
            }
        }
        
        return $params;
    }
}
