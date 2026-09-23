<?php

use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\ChoreController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\ElectricityController;
use App\Http\Controllers\Admin\FamilyMemberController;
use App\Http\Controllers\Admin\PerformanceController;
use App\Http\Controllers\Admin\WeatherController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ChoreActionController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/', function () {
    $user = auth()->user();

    if ($user === null) {
        return redirect()->route('login');
    }

    return redirect()->route($user->isAdmin() ? 'admin.dashboard' : 'admin.chores.index');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('admin')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/family', [FamilyMemberController::class, 'index'])->name('family.index');
        Route::get('/family/create', [FamilyMemberController::class, 'create'])->name('family.create');
        Route::post('/family', [FamilyMemberController::class, 'store'])->name('family.store');
        Route::get('/family/{family}/edit', [FamilyMemberController::class, 'edit'])->name('family.edit');
        Route::put('/family/{family}', [FamilyMemberController::class, 'update'])->name('family.update');
        Route::post('/family/{family}/absences', [FamilyMemberController::class, 'storeAbsence'])->name('family.absences.store');
        Route::delete('/family/{family}/absences/{absence}', [FamilyMemberController::class, 'destroyAbsence'])->name('family.absences.destroy');

        Route::get('/performance', PerformanceController::class)->name('performance');

        Route::get('/chores/create', [ChoreController::class, 'create'])->name('chores.create');
        Route::post('/chores', [ChoreController::class, 'store'])->name('chores.store');
        Route::get('/chores/{chore}/edit', [ChoreController::class, 'edit'])->name('chores.edit');
        Route::put('/chores/{chore}', [ChoreController::class, 'update'])->name('chores.update');
        Route::post('/chores/{chore}/archive', [ChoreController::class, 'archive'])->name('chores.archive');

        Route::get('/electricity', [ElectricityController::class, 'edit'])->name('electricity.edit');
        Route::put('/electricity', [ElectricityController::class, 'update'])->name('electricity.update');
        Route::post('/electricity/refresh', [ElectricityController::class, 'refresh'])->middleware('throttle:6,1')->name('electricity.refresh');

        Route::get('/weather', [WeatherController::class, 'edit'])->name('weather.edit');
        Route::put('/weather', [WeatherController::class, 'update'])->name('weather.update');
        Route::post('/weather/refresh', [WeatherController::class, 'refresh'])->middleware('throttle:6,1')->name('weather.refresh');

        Route::get('/calendar', [CalendarController::class, 'edit'])->name('calendar.edit');
        Route::put('/calendar', [CalendarController::class, 'update'])->name('calendar.update');
        Route::post('/calendar/sources', [CalendarController::class, 'storeSource'])->middleware('throttle:10,1')->name('calendar.sources.store');
        Route::post('/calendar/ical', [CalendarController::class, 'storeIcal'])->middleware('throttle:10,1')->name('calendar.ical.store');
        Route::put('/calendar/sources', [CalendarController::class, 'sources'])->name('calendar.sources');
        Route::post('/calendar/refresh', [CalendarController::class, 'refresh'])->middleware('throttle:6,1')->name('calendar.refresh');
        Route::delete('/calendar', [CalendarController::class, 'disconnect'])->name('calendar.disconnect');

        Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('/devices/approve', [DeviceController::class, 'approve'])->middleware('throttle:10,1')->name('devices.approve');
        Route::post('/devices/{device}/revoke', [DeviceController::class, 'revoke'])->name('devices.revoke');
    });

    Route::get('/chores', [ChoreController::class, 'index'])->name('chores.index');
    Route::post('/chores/occurrences/{occurrence}/complete', [ChoreActionController::class, 'complete'])->name('chores.complete');
    Route::post('/chores/occurrences/{occurrence}/reopen', [ChoreActionController::class, 'reopen'])->name('chores.reopen');
});

Route::get('/display/pair', [DevicePairingController::class, 'create'])->name('display.pair');
Route::post('/display/pair', [DevicePairingController::class, 'store'])->middleware('throttle:10,1')->name('display.pair.store');

Route::middleware('display')->group(function () {
    Route::get('/display', [DisplayController::class, 'show'])->name('display.show');
    Route::post('/display/chores/{occurrence}/complete', [ChoreActionController::class, 'complete'])->name('display.chores.complete');
});

Route::prefix('api/v1')->group(function () {
    Route::post('/device-pairings', [DevicePairingController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/device-pairings/current', [DevicePairingController::class, 'current']);
    Route::post('/device-pairings/claim', [DevicePairingController::class, 'claim']);

    Route::middleware('display')->group(function () {
        Route::get('/display', [DisplayController::class, 'json']);
        Route::post('/chores/{occurrence}/complete', [ChoreActionController::class, 'complete']);
        Route::post('/chores/{occurrence}/reopen', [ChoreActionController::class, 'reopen']);
    });
});
