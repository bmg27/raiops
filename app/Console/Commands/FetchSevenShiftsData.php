<?php

namespace App\Console\Commands;

use App\Models\TenantMaster;
use App\Services\IntegrationSettingsService;
use App\Services\RdsConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchSevenShiftsData extends Command
{
    protected $signature = 'seven:fetch-data
                            {--tenant= : TenantMaster ID (required)}';
    protected $description = 'Fetch departments and roles from 7shifts API';

    public function handle()
    {
        $tenantMasterId = $this->option('tenant');
        
        if (!$tenantMasterId) {
            $this->error('--tenant parameter is required (TenantMaster ID)');
            return Command::FAILURE;
        }

        $tenantMaster = TenantMaster::with('rdsInstance')->find($tenantMasterId);
        if (!$tenantMaster || !$tenantMaster->rdsInstance) {
            $this->error("TenantMaster ID {$tenantMasterId} not found or has no RDS instance.");
            return Command::FAILURE;
        }

        $rdsInstance = $tenantMaster->rdsInstance;
        $rdsConnectionService = app(RdsConnectionService::class);
        $raiConn = $rdsConnectionService->getConnection($rdsInstance);

        $this->info('Fetching data from 7shifts API...');

        try {
            $this->fetchDepartments($tenantMasterId, $raiConn);
            $this->fetchRoles($tenantMasterId, $raiConn);

            $this->info('Data successfully fetched and stored.');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error fetching data: ' . $e->getMessage());
            Log::error('Error fetching Seven Shifts data', [
                'tenant_master_id' => $tenantMasterId,
                'error' => $e->getMessage(),
            ]);
            return Command::FAILURE;
        }
    }

    protected function fetchDepartments(int $tenantMasterId, string $connection)
    {
        $this->info('Fetching departments...');
        
        $baseUrl = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_API_BASE_URL');
        $token = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_API_TOKEN');
        $companyId = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_COMPANY_ID');

        if (!$baseUrl || !$token || !$companyId) {
            throw new \Exception('Missing Seven Shifts API credentials');
        }

        $sevenProviderId = IntegrationSettingsService::getProviderId('SEVEN_SHIFTS_API', $connection);
        if (!$sevenProviderId) {
            throw new \Exception('SevenShifts provider not found');
        }

        $url = "{$baseUrl}/company/{$companyId}/departments";

        $this->fetchWithCursorPagination($url, $token, function ($data) use ($sevenProviderId, $connection) {
            foreach ($data as $department) {
                // Look up location via LocationMap
                $locationMap = DB::connection($connection)
                    ->table('location_maps')
                    ->where('provider_id', $sevenProviderId)
                    ->where('external_id', $department['location_id'])
                    ->first();

                if (!$locationMap) {
                    $this->warn("Location mapping not found for department: {$department['name']} (external_id: {$department['location_id']})");
                    continue;
                }

                DB::connection($connection)->table('seven_departments')->updateOrInsert(
                    ['department_id' => $department['id']],
                    [
                        'location_id' => $locationMap->location_id,
                        'name' => $department['name'],
                        'updated_at' => now(),
                    ]
                );
            }
        });

        $this->info('Departments fetched successfully.');
    }

    protected function fetchRoles(int $tenantMasterId, string $connection)
    {
        $this->info('Fetching roles...');
        
        $baseUrl = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_API_BASE_URL');
        $token = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_API_TOKEN');
        $companyId = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_COMPANY_ID');

        if (!$baseUrl || !$token || !$companyId) {
            throw new \Exception('Missing Seven Shifts API credentials');
        }

        $sevenProviderId = IntegrationSettingsService::getProviderId('SEVEN_SHIFTS_API', $connection);
        if (!$sevenProviderId) {
            throw new \Exception('SevenShifts provider not found');
        }

        $url = "{$baseUrl}/company/{$companyId}/roles";

        $this->fetchWithCursorPagination($url, $token, function ($data) use ($sevenProviderId, $connection) {
            foreach ($data as $role) {
                // Look up location via LocationMap
                $locationMap = DB::connection($connection)
                    ->table('location_maps')
                    ->where('provider_id', $sevenProviderId)
                    ->where('external_id', $role['location_id'])
                    ->first();

                if (!$locationMap) {
                    $this->warn("Location mapping not found for role: {$role['name']} (external_id: {$role['location_id']})");
                    continue;
                }

                // Map department_id from API to local table
                $departmentId = null;
                if (isset($role['department_id'])) {
                    $department = DB::connection($connection)
                        ->table('seven_departments')
                        ->where('department_id', $role['department_id'])
                        ->first();
                    $departmentId = $department ? $department->id : null;
                }

                // Insert or update the role
                DB::connection($connection)->table('seven_roles')->updateOrInsert(
                    ['role_id' => $role['id']],
                    [
                        'location_id' => $locationMap->location_id,
                        'department_id' => $departmentId,
                        'name' => $role['name'],
                        'updated_at' => now(),
                    ]
                );
            }
        });

        $this->info('Roles fetched successfully.');
    }

    protected function fetchWithCursorPagination($url, $token, $callback)
    {
        $nextCursor = null;

        do {
            $response = Http::withToken($token)
                ->withHeaders(['x-api-version' => '2022-05-01'])
                ->get($url, $nextCursor ? ['cursor' => $nextCursor] : []);

            if ($response->failed()) {
                throw new \Exception('API call failed: ' . $response->body());
            }

            $data = $response->json()['data'] ?? [];
            $callback($data);

            $nextCursor = $response->json()['meta']['cursor']['next'] ?? null;
            if (!$nextCursor) {
                $nextCursor = $response->json()['next_cursor'] ?? null;
            }
        } while ($nextCursor);
    }
}

