<?php

use App\Livewire\Admin\TenantMultiRds;
use App\Livewire\Admin\RdsManagement;
use App\Livewire\Admin\UserRoutingManagement;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\SystemHealthDashboard;
use App\Livewire\Admin\AnalyticsDashboard;
use App\Livewire\Admin\BillingManagement;
use App\Livewire\Admin\SubscriptionPlanManagement;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('admin.health');
    })->name('dashboard');

    // System Health Dashboard (Command Central)
    Route::get('/admin/health', SystemHealthDashboard::class)
        ->name('admin.health');

    // Analytics Dashboard
    Route::get('/admin/analytics', AnalyticsDashboard::class)
        ->middleware('check.permission:reports.view')
        ->name('admin.analytics');
    
    Route::get('/admin/analytics/export', function () {
        $component = new AnalyticsDashboard();
        return $component->exportAnalytics();
    })
        ->middleware('check.permission:reports.export')
        ->name('admin.analytics.export');

    // Billing Management
    Route::get('/admin/billing', BillingManagement::class)
        ->middleware('check.permission:billing.view')
        ->name('admin.billing');

    // Subscription Plan Management
    Route::get('/admin/subscription-plans', SubscriptionPlanManagement::class)
        ->middleware('check.permission:billing.edit')
        ->name('admin.subscription-plans');

    // RDS Instance Management (System Admin Only)
    Route::get('/admin/rds', RdsManagement::class)
        ->middleware('check.permission:rds.manage')
        ->name('admin.rds');

    // Tenant Management - Multi-RDS View
    Route::get('/admin/tenants', TenantMultiRds::class)
        ->middleware('check.permission:tenant.view')
        ->name('admin.tenants');

    // User Email Routing Management
    Route::get('/admin/user-routing', UserRoutingManagement::class)
        ->middleware('check.permission:user.view')
        ->name('admin.user-routing');

    // Audit Logs
    Route::get('/admin/audit-logs', AuditLogViewer::class)
        ->middleware('check.permission:audit.view')
        ->name('admin.audit-logs');

    // Permission Management (User Management / Rump Admin)
    Route::get('/um/{userId?}', \App\Livewire\Permissions\ManageMaster::class)
        ->middleware('check.permission:user.manage')
        ->name('manage.index');
    
    Route::post('/permissions/menu-organizer/update-order', [\App\Http\Controllers\MenuOrganizerController::class, 'updateOrder'])
        ->middleware('check.permission:user.manage')
        ->name('menu.organizer.update');
});
