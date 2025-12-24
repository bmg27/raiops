<?php

namespace App\Console\Commands;

use App\Models\TenantMaster;
use App\Services\IntegrationSettingsService;
use App\Services\RdsConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchSevenWagesData extends Command
{
    protected $signature = 'seven:fetch-wagesdata 
                            {--tenant= : TenantMaster ID (required)}';
    protected $description = 'Fetch wages from the 7shifts API and populate the database.';

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

        $this->info('Fetching wages from 7shifts...');

        $baseUrl = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_API_BASE_URL');
        $token = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_API_TOKEN');
        $companyId = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_COMPANY_ID');

        if (!$baseUrl || !$token || !$companyId) {
            $this->error('Seven Shifts API credentials not found. Configure provider settings for SEVEN_SHIFTS_API.');
            return Command::FAILURE;
        }

        $employees = DB::connection($raiConn)
            ->table('seven_employees')
            ->where('active', '1')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($employees as $employee) {
            $this->info("Fetching wages for employee ID: {$employee->user_id}");

            $response = Http::withToken($token)
                ->withHeaders(['x-api-version' => '2022-05-01'])
                ->get("{$baseUrl}/company/{$companyId}/users/{$employee->user_id}/wages");

            if ($response->failed()) {
                $this->error("Failed to fetch wages for employee ID: {$employee->user_id}. Error: " . $response->body());
                continue;
            }

            $data = $response->json()['data'] ?? [];
            $wageTypes = [
                'current' => $data['current_wages'] ?? [],
                'upcoming' => $data['upcoming_wages'] ?? [],
            ];

            foreach ($wageTypes as $status => $wages) {
                foreach ($wages as $wage) {
                    if (empty($wage['id']) || empty($wage['effective_date']) || empty($wage['wage_type'])) {
                        $this->warn("Skipping invalid wage record for employee ID: {$employee->user_id}");
                        continue;
                    }

                    $roleId = null;
                    if (isset($wage['role_id'])) {
                        $role = DB::connection($raiConn)
                            ->table('seven_roles')
                            ->where('role_id', $wage['role_id'])
                            ->first();
                        $roleId = $role ? $role->id : null;
                    }

                    DB::connection($raiConn)->table('seven_wages')->updateOrInsert(
                        [
                            'employee_id' => $employee->id,
                            'role_id' => $roleId,
                            'effective_date' => $wage['effective_date'],
                        ],
                        [
                            'wage_cents' => $wage['wage_cents'] ?? 0,
                            'wage_type' => $wage['wage_type'],
                            'status' => $status,
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        $this->info('Wages successfully fetched and updated.');
        return Command::SUCCESS;
    }
}

