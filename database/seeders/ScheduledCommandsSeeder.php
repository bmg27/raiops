<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ScheduledCommand;

class ScheduledCommandsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Seeds the scheduled_commands table with commands from RAI.
     * These commands should be available in RAIOPS for execution.
     */
    public function run(): void
    {
        $commands = [
            // Seven Shifts Commands
            [
                'command_name' => 'seven:fetch-employees',
                'display_name' => 'Fetch Employees',
                'description' => 'Fetch employee data from Seven Shifts API',
                'category' => 'Seven Shifts',
                'required_integration' => 'seven_shifts',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 10,
            ],
            [
                'command_name' => 'seven:fetch-data',
                'display_name' => 'Fetch General Data',
                'description' => 'Fetch general data from Seven Shifts API',
                'category' => 'Seven Shifts',
                'required_integration' => 'seven_shifts',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 20,
            ],
            [
                'command_name' => 'fetch:sevenshifts-reporting',
                'display_name' => 'Daily Sales & Labor Report',
                'description' => 'Fetch daily sales and labor report from Seven Shifts',
                'category' => 'Seven Shifts',
                'required_integration' => 'seven_shifts',
                'default_params' => ['daily-sales-labor' => true],
                'requires_tenant' => true,
                'sort_order' => 30,
            ],
            [
                'command_name' => 'fetch:sevenshifts-reporting',
                'display_name' => 'Daily Stats Report',
                'description' => 'Fetch daily stats report from Seven Shifts',
                'category' => 'Seven Shifts',
                'required_integration' => 'seven_shifts',
                'default_params' => ['daily-stats' => true],
                'requires_tenant' => true,
                'sort_order' => 40,
            ],
            [
                'command_name' => 'fetch:sevenshifts-reporting',
                'display_name' => 'Hours & Wages Report',
                'description' => 'Fetch hours and wages report from Seven Shifts',
                'category' => 'Seven Shifts',
                'required_integration' => 'seven_shifts',
                'default_params' => ['hours-wages' => true],
                'requires_tenant' => true,
                'sort_order' => 50,
            ],
            [
                'command_name' => 'seven:fetch-shifts',
                'display_name' => 'Fetch Shifts',
                'description' => 'Fetch shift data from Seven Shifts API',
                'category' => 'Seven Shifts',
                'required_integration' => 'seven_shifts',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 60,
            ],
            [
                'command_name' => 'seven:fetch-wagesdata',
                'display_name' => 'Fetch Wages Data',
                'description' => 'Fetch wages data from Seven Shifts API',
                'category' => 'Seven Shifts',
                'required_integration' => 'seven_shifts',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 70,
            ],
            [
                'command_name' => 'fetch:worked-hours-wages',
                'display_name' => 'Fetch Worked Hours & Wages',
                'description' => 'Fetch worked hours and wages from Seven Shifts',
                'category' => 'Seven Shifts',
                'required_integration' => 'seven_shifts',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 80,
            ],
            [
                'command_name' => 'seven:fetch-punches',
                'display_name' => 'Fetch Time Punches',
                'description' => 'Fetch time punch data from Seven Shifts API',
                'category' => 'Seven Shifts',
                'required_integration' => 'seven_shifts',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 90,
            ],

            // Toast Commands
            [
                'command_name' => 'toast:fetch-services',
                'display_name' => 'Fetch Services',
                'description' => 'Fetch restaurant services from Toast API',
                'category' => 'Toast',
                'required_integration' => 'toast',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 100,
            ],
            [
                'command_name' => 'toast:fetch-employees',
                'display_name' => 'Fetch Employees',
                'description' => 'Fetch employee data from Toast API',
                'category' => 'Toast',
                'required_integration' => 'toast',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 110,
            ],
            [
                'command_name' => 'toast:fetch-orders',
                'display_name' => 'Fetch Orders',
                'description' => 'Fetch orders from Toast API',
                'category' => 'Toast',
                'required_integration' => 'toast',
                'default_params' => [
                    'startDate' => '{date:Ymd:-3days}',
                    'endDate' => '{date:Ymd:today}',
                ],
                'requires_tenant' => true,
                'sort_order' => 120,
            ],
            [
                'command_name' => 'toast:fetch-stock',
                'display_name' => 'Fetch Stock Levels',
                'description' => 'Fetch stock levels from Toast API',
                'category' => 'Toast',
                'required_integration' => 'toast',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 130,
            ],
            [
                'command_name' => 'toast:fetch-void-reasons',
                'display_name' => 'Fetch Void Reasons',
                'description' => 'Fetch void reasons from Toast API',
                'category' => 'Toast',
                'required_integration' => 'toast',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 140,
            ],
            [
                'command_name' => 'toast:fetch-discount-codes',
                'display_name' => 'Fetch Discount Codes',
                'description' => 'Fetch discount codes from Toast API',
                'category' => 'Toast',
                'required_integration' => 'toast',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 150,
            ],
            [
                'command_name' => 'toast:fetch-time-entries',
                'display_name' => 'Fetch Time Entries',
                'description' => 'Fetch labor time entries from Toast API',
                'category' => 'Toast',
                'required_integration' => 'toast',
                'default_params' => [
                    'start' => '{date:Ymd:-2days}',
                    'end' => '{date:Ymd:today}',
                ],
                'requires_tenant' => true,
                'sort_order' => 160,
            ],
            [
                'command_name' => 'toast:fetch-business-hours',
                'display_name' => 'Fetch Business Hours',
                'description' => 'Fetch business hours from Toast API',
                'category' => 'Toast',
                'required_integration' => 'toast',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 170,
            ],
            [
                'command_name' => 'toast:fetch-dining-options',
                'display_name' => 'Fetch Dining Options',
                'description' => 'Fetch dining options from Toast API',
                'category' => 'Toast',
                'required_integration' => 'toast',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 180,
            ],
            [
                'command_name' => 'toast:fetch-jobs',
                'display_name' => 'Fetch Job Positions',
                'description' => 'Fetch job positions from Toast API',
                'category' => 'Toast',
                'required_integration' => 'toast',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 190,
            ],
            [
                'command_name' => 'toast:fetch-discount-reasons',
                'display_name' => 'Fetch Discount Reasons',
                'description' => 'Fetch discount reasons from Toast API',
                'category' => 'Toast',
                'required_integration' => 'toast',
                'default_params' => null,
                'requires_tenant' => true,
                'sort_order' => 200,
            ],

            // Reservations Commands
            [
                'command_name' => 'reservations:import',
                'display_name' => 'Import Reservations',
                'description' => 'Import reservations from Resy API',
                'category' => 'Reservations',
                'required_integration' => 'resy',
                'default_params' => [
                    'days-back' => 2,
                    'days-forward' => 15,
                ],
                'requires_tenant' => true,
                'sort_order' => 300,
            ],

            // Integrations Commands
            [
                'command_name' => 'integration:run',
                'display_name' => 'Run Integration',
                'description' => 'Run all integrations for a tenant',
                'category' => 'Integrations',
                'required_integration' => null,
                'default_params' => [],
                'requires_tenant' => true,
                'is_active' => true,
                'default_enabled' => true,
                'sort_order' => 350,
            ],

            // Tips Commands
            [
                'command_name' => 'generate:tip-pools',
                'display_name' => 'Generate Tip Pools',
                'description' => 'Generate tip pools for locations',
                'category' => 'Tips',
                'required_integration' => null,
                'default_params' => ['force' => true],
                'requires_tenant' => false,
                'sort_order' => 400,
            ],

            // Metrics & Caching Commands
            [
                'command_name' => 'metrics:cache-daily',
                'display_name' => 'Cache Daily Metrics',
                'description' => 'Cache daily metrics for reporting',
                'category' => 'Caching',
                'required_integration' => null,
                'default_params' => ['days' => 7],
                'requires_tenant' => false,
                'sort_order' => 500,
            ],
            [
                'command_name' => 'cache:warm-daily-log',
                'display_name' => 'Warm Daily Log Cache',
                'description' => 'Pre-cache daily log data for faster loading',
                'category' => 'Caching',
                'required_integration' => null,
                'default_params' => ['days' => 10],
                'requires_tenant' => false,
                'sort_order' => 510,
            ],
        ];

        foreach ($commands as $command) {
            // Ensure required fields have defaults
            $commandData = array_merge([
                'is_active' => true,
                'default_enabled' => true,
            ], $command);
            
            ScheduledCommand::updateOrCreate(
                ['display_name' => $command['display_name']], // Use display_name as unique identifier
                $commandData
            );
        }
    }
}
