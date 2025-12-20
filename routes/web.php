<?php

use App\Livewire\Admin\TenantManagement;
use App\Livewire\Admin\RdsManagement;
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
        return redirect()->route('admin.rds');
    })->name('dashboard');

    // RDS Instance Management (System Admin Only)
    Route::get('/admin/rds', RdsManagement::class)
        ->middleware('check.permission:rds.manage')
        ->name('admin.rds');

    // Tenant Management (Super Admin Only)
    Route::get('/admin/tenants', TenantManagement::class)
        ->middleware('check.permission:tenant.manage')
        ->name('admin.tenants');

    // Permission Management (User Management / Rump Admin)
    Route::get('/um/{userId?}', \App\Livewire\Permissions\ManageMaster::class)
        ->middleware('check.permission:user.manage')
        ->name('manage.index');
    
    Route::post('/permissions/menu-organizer/update-order', [\App\Http\Controllers\MenuOrganizerController::class, 'updateOrder'])
        ->middleware('check.permission:user.manage')
        ->name('menu.organizer.update');
});
