<?php

namespace App\Console\Commands;

use App\Models\TenantMaster;
use App\Services\IntegrationSettingsService;
use App\Services\RdsConnectionService;
use DateTime;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchSevenShiftsEmployees extends Command
{
    protected $signature = 'seven:fetch-employees 
                            {--tenant= : TenantMaster ID (required)}
                            {--modified-since= : Fetch employees modified since this date (YYYY-MM-DD format, defaults to 3 days ago)}';
    protected $description = 'Fetch employees from the 7shifts API and populate the database.';

    public function handle()
    {
        $this->info('Fetching employees from 7shifts...');

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

        try {
            // Get settings from integration
            $baseUrl = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_API_BASE_URL');
            $token = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_API_TOKEN');
            $companyId = IntegrationSettingsService::get($tenantMasterId, 'SEVEN_SHIFTS_API', 'SEVENSHIFTS_COMPANY_ID');

            // Validate required settings
            if (!$baseUrl || !$token || !$companyId) {
                $missing = [];
                if (!$baseUrl) $missing[] = 'SEVENSHIFTS_API_BASE_URL';
                if (!$token) $missing[] = 'SEVENSHIFTS_API_TOKEN';
                if (!$companyId) $missing[] = 'SEVENSHIFTS_COMPANY_ID';
                
                $this->error('Seven Shifts API credentials not found. Missing: ' . implode(', ', $missing) . 
                    " Configure provider settings for SEVEN_SHIFTS_API for tenant: {$tenantMaster->name}.");
                return Command::FAILURE;
            }

            // Get modified_since parameter, default to 3 days ago
            $modifiedSince = $this->option('modified-since') 
                ? $this->option('modified-since') 
                : Carbon::now()->subDays(3)->toDateString();

            $this->info("Fetching employees modified since: {$modifiedSince}");

            $limit = 100;
            $cursor = null;

            do {
                $queryParams = [
                    'limit' => $limit,
                    'modified_since' => $modifiedSince,
                ];
                if ($cursor) {
                    $queryParams['cursor'] = $cursor;
                }

                $response = Http::withToken($token)
                    ->withHeaders(['x-api-version' => '2022-05-01'])
                    ->get("{$baseUrl}/company/{$companyId}/users", $queryParams);

                if ($response->failed()) {
                    $this->error("Failed to fetch employees: " . $response->body());
                    return Command::FAILURE;
                }

                $employees = $response->json();

                foreach ($employees['data'] ?? [] as $employee) {
                    if (!isset($employee['id'])) {
                        $this->warn("Missing user_id for employee. Skipping.");
                        continue;
                    }

                    // Update or create using DB facade on RDS
                    $employeeData = [
                        'identity_id' => $employee['identity_id'] ?? null,
                        'company_id' => $employee['company_id'] ?? null,
                        'first_name' => $employee['first_name'] ?? null,
                        'last_name' => $employee['last_name'] ?? null,
                        'preferred_first_name' => $employee['preferred_first_name'] ?? null,
                        'preferred_last_name' => $employee['preferred_last_name'] ?? null,
                        'pronouns' => $employee['pronouns'] ?? null,
                        'email' => $employee['email'] ?? null,
                        'mobile_number' => $employee['mobile_number'] ?? null,
                        'home_number' => $employee['home_number'] ?? null,
                        'address' => $employee['address'] ?? null,
                        'postal_zip' => $employee['postal_zip'] ?? null,
                        'city' => $employee['city'] ?? null,
                        'prov_state' => $employee['prov_state'] ?? null,
                        'invite_status' => $employee['invite_status'] ?? null,
                        'last_login' => $this->fixDateTime($employee['last_login'] ?? null),
                        'active' => $employee['active'] ?? 0,
                        'photo' => $employee['photo'] ?? null,
                        'notes' => $employee['notes'] ?? null,
                        'hire_date' => $this->fixDateTime($employee['hire_date'] ?? null),
                        'timezone' => $employee['timezone'] ?? null,
                        'type' => $employee['type'] ?? null,
                        'punch_id' => $employee['punch_id'] ?? null,
                        'employee_id' => $employee['employee_id'] ?? null,
                        'max_weekly_hours' => $employee['max_weekly_hours'] ?? null,
                        'invited' => $this->fixDateTime($employee['invited'] ?? null),
                        'invite_accepted' => $this->fixDateTime($employee['invite_accepted'] ?? null),
                        'is_new' => $employee['is_new'] ?? 0,
                        'birth_date' => $this->fixDateTime($employee['birth_date'] ?? null),
                        'language' => $employee['language'] ?? null,
                        'appear_as_employee' => $employee['appear_as_employee'] ?? 0,
                        'subscribe_to_updates' => $employee['subscribe_to_updates'] ?? 0,
                        'skill_level' => $employee['skill_level'] ?? null,
                        'hourly_wage' => $employee['hourly_wage'] ?? null,
                        'wage_type' => $employee['wage_type'] ?? 'hourly',
                        'sms_me_schedules' => $employee['sms_me_schedules'] ?? '0',
                        'notify_ot_risk' => $employee['notify_ot_risk'] ?? '0',
                        'push' => $employee['push'] ?? '0',
                        'updated_at' => now(),
                    ];

                    DB::connection($raiConn)->table('seven_employees')->updateOrInsert(
                        ['user_id' => $employee['id']],
                        $employeeData
                    );
                }

                $this->info("Processed " . count($employees['data'] ?? []) . " employees.");

                $cursor = $employees['meta']['cursor']['next'] ?? null;

            } while ($cursor);

            $this->info('Employees successfully fetched and updated.');
            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Error fetching employees: ' . $e->getMessage());
            Log::error('Error fetching Seven Shifts employees', [
                'tenant_master_id' => $tenantMasterId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function fixDateTime($dateTime)
    {
        if (empty($dateTime)) {
            return null;
        }

        try {
            $date = new DateTime($dateTime);
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }
}

