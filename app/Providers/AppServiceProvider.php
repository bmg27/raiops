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
        // RAINBO Permission Blade Directives
        
        /**
         * @can_rainbo('permission.name') - Check if user has RAINBO permission
         */
        Blade::if('canRainbo', function (string $permission) {
            $user = auth()->user();
            if (!$user) {
                return false;
            }
            return $user->hasRainboPermission($permission);
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
