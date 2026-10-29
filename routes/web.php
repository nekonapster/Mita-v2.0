<?php

use App\Livewire\Admin\UserRoleManager;
use App\Livewire\Dashboard;
use App\Livewire\Economics\CostRateManager;
use App\Livewire\Economics\EconomicMovementsManager;
use App\Livewire\Economics\EconomicsDashboard;
use App\Livewire\Economics\TimeEntryValidation;
use App\Livewire\Portal\EmployeePortal;
use App\Livewire\Roster\ShiftManager;
use App\Livewire\Roster\WeeklyRoster;
use App\Livewire\Rrhh\AbsenceIndex;
use App\Livewire\Rrhh\EmployeeIndex;
use App\Livewire\Rrhh\EmployeeShow;
use App\Livewire\Rrhh\IncidentIndex;
use App\Livewire\Services\CatalogManager;
use App\Livewire\Services\ClientIndex;
use App\Livewire\Services\ProjectIndex;
use App\Livewire\Services\ProjectShow;
use App\Livewire\Services\ServiceNeedsManager;
use Illuminate\Support\Facades\Route;
use App\Models\Empleado;

/*
|--------------------------------------------------------------------------
| Web Routes - MiTa (Gestión Integral de Servicios y Socorrismo)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {
    // Panel de Control General
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Módulo 1: Recursos Humanos
    Route::prefix('rrhh')->name('rrhh.')->group(function () {
        Route::get('/empleados', EmployeeIndex::class)
            ->can('viewAny', Empleado::class)
            ->name('empleados');
        Route::get('/empleados/{id}', EmployeeShow::class)
            ->can('viewAny', \App\Models\Empleado::class)
            ->name('empleados.show');
        Route::get('/ausencias', AbsenceIndex::class)->name('ausencias');
        Route::get('/incidencias', IncidentIndex::class)->name('incidencias');
    });

    // Módulo 2: Gestión de Servicios
    Route::prefix('servicios')->name('servicios.')->group(function () {
        Route::get('/clientes', ClientIndex::class)->name('clientes');
        Route::get('/proyectos', ProjectIndex::class)->name('proyectos');
        Route::get('/proyectos/{id}', ProjectShow::class)->name('proyectos.show');
        Route::get('/necesidades', ServiceNeedsManager::class)->name('necesidades');
        Route::get('/catalogos', CatalogManager::class)->name('catalogos');
    });

    // Módulo 3: Planificación de Personal
    Route::prefix('planificacion')->name('planificacion.')->group(function () {
        Route::get('/cuadrante', WeeklyRoster::class)->name('cuadrante');
        Route::get('/turnos', ShiftManager::class)->name('turnos');
    });

    // Módulo 4: Control Económico y Horas Realizadas
    Route::prefix('horas')->name('horas.')->group(function () {
        Route::get('/validacion', TimeEntryValidation::class)->name('validacion');
    });

    Route::prefix('economia')->name('economia.')->group(function () {
        Route::get('/informes', EconomicsDashboard::class)->name('informes');
        Route::get('/tarifas', CostRateManager::class)->name('tarifas');
        Route::get('/movimientos', EconomicMovementsManager::class)->name('movimientos');
    });

    // Portal del Empleado (Turnos asignados e imputación de horas)
    Route::prefix('portal')->name('portal.')->group(function () {
        Route::get('/empleado', EmployeePortal::class)->name('empleado');
    });

    // Administración del Sistema (Gestión de usuarios y asignación de roles)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/usuarios', UserRoleManager::class)->name('usuarios');
    });
});
