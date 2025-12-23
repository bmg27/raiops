<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // RAIOPS Permission Blade Directives
        
        /**
         * @canRaiOps('permission.name') - Check if user has RAIOPS permission
         */
        Blade::if('canRaiOps', function (string $permission) {
            $user = auth()->user();
            if (!$user) {
                return false;
            }
            return $user->hasRaiOpsPermission($permission);
        });

        /**
         * @is_system_admin - Check if user is system admin
         */
        Blade::if('isSystemAdmin', function () {
            $user = auth()->user();
            if (!$user) {
                return false;
            }
            return $user->isSystemAdmin();
        });
    }
}
