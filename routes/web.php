<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GoogleConnectionController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SlipVerificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DoctorPayrollController;
use App\Http\Controllers\DoctorAssignmentController;
use App\Http\Controllers\DoctorVerifyController;

// Verifikasi hanya bisa diakses dengan token acak di QR; tidak perlu akun payroll.
Route::get('/verifikasi-slip/{payroll}/{token}', [SlipVerificationController::class,'show'])
    ->middleware('throttle:30,1')->name('slips.verify');

Route::get('/verifikasi-honor-dokter/{doctorPayroll}/{token}', [DoctorVerifyController::class,'show'])
    ->middleware('throttle:30,1')->name('doctors.verify');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
});

Route::middleware(['auth', \App\Http\Middleware\NoStoreSensitiveContent::class])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::resource('employees', EmployeeController::class)->except('show');
    Route::get('/payrolls', [PayrollController::class,'index'])->name('payrolls.index');
    Route::get('/payrolls/create', [PayrollController::class,'create'])->name('payrolls.create');
    Route::post('/payrolls/preview', [PayrollController::class,'preview'])->name('payrolls.preview');
    Route::post('/payrolls', [PayrollController::class,'store'])->name('payrolls.store');
    Route::get('/payrolls/{payroll}', [PayrollController::class,'show'])->name('payrolls.show');
    Route::get('/payrolls/{payroll}/edit', [PayrollController::class,'edit'])->name('payrolls.edit');
    Route::put('/payrolls/{payroll}', [PayrollController::class,'update'])->name('payrolls.update');
    Route::delete('/payrolls/{payroll}', [PayrollController::class,'destroy'])->name('payrolls.destroy');
    Route::get('/payrolls/{payroll}/pdf', [PayrollController::class,'pdf'])->name('payrolls.pdf');
    Route::post('/payrolls/{payroll}/approve', [PayrollController::class,'approve'])->middleware('can:approve-payroll')->name('payrolls.approve');

    // Modul honor dokter independen dari payroll karyawan biasa.
    Route::get('/doctor-payrolls', [DoctorPayrollController::class, 'index'])->name('doctors.index');
    Route::get('/doctor-payrolls/create', [DoctorPayrollController::class, 'create'])->name('doctors.create');
    Route::post('/doctor-payrolls/preview', [DoctorPayrollController::class, 'preview'])->name('doctors.preview');
    Route::post('/doctor-payrolls', [DoctorPayrollController::class, 'store'])->name('doctors.store');
    Route::get('/doctor-payrolls/{doctorPayroll}', [DoctorPayrollController::class, 'show'])->name('doctors.show');
    Route::get('/doctor-payrolls/{doctorPayroll}/edit', [DoctorPayrollController::class, 'edit'])->name('doctors.edit');
    Route::put('/doctor-payrolls/{doctorPayroll}', [DoctorPayrollController::class, 'update'])->name('doctors.update');
    Route::delete('/doctor-payrolls/{doctorPayroll}', [DoctorPayrollController::class, 'destroy'])->name('doctors.delete');
    Route::get('/doctor-payrolls/{doctorPayroll}/pdf', [DoctorPayrollController::class, 'pdf'])->name('doctors.pdf');
    Route::post('/doctor-payrolls/{doctorPayroll}/approve', [DoctorPayrollController::class, 'approve'])->middleware('can:approve-payroll')->name('doctors.approve');
    Route::post('/doctor-payrolls/{doctorPayroll}/send', [DoctorPayrollController::class, 'send'])->name('doctors.send');

    Route::get('/deliveries', [DeliveryController::class,'index'])->name('deliveries.index');
    Route::post('/deliveries/bulk', [DeliveryController::class,'bulk'])->name('deliveries.bulk');
    Route::post('/deliveries/{payroll}', [DeliveryController::class,'single'])->name('deliveries.single');

    Route::get('/reports', [ReportController::class,'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class,'csv'])->name('reports.csv');
    Route::get('/reports/audit', [ReportController::class,'audit'])->middleware('can:manage-settings')->name('reports.audit');

    Route::middleware('can:manage-settings')->group(function () {
        Route::get('/doctor-settings', [DoctorAssignmentController::class, 'index'])->name('doctors.settings');
        Route::get('/doctor-settings/create', [DoctorAssignmentController::class, 'create'])->name('doctors.assignments.create');
        Route::post('/doctor-settings', [DoctorAssignmentController::class, 'store'])->name('doctors.assignments.store');
        Route::get('/doctor-settings/{assignment}/edit', [DoctorAssignmentController::class, 'edit'])->name('doctors.assignments.edit');
        Route::put('/doctor-settings/{assignment}', [DoctorAssignmentController::class, 'update'])->name('doctors.assignments.update');
        Route::post('/doctor-settings/procedure', [DoctorAssignmentController::class, 'procedure'])->name('doctors.procedures.store');
        Route::put('/doctor-settings/procedure/{procedure}', [DoctorAssignmentController::class, 'procedureUpdate'])->name('doctors.procedures.update');
        Route::post('/doctor-settings/procedure/{procedure}/status', [DoctorAssignmentController::class, 'procedureStatus'])->name('doctors.procedures.status');
        Route::get('/settings', [SettingsController::class,'index'])->name('settings.index');
        Route::post('/settings/clinic', [SettingsController::class,'clinic'])->name('settings.clinic');
        Route::post('/settings/wages', [SettingsController::class,'wage'])->name('settings.wage');
        Route::post('/settings/bpjs', [SettingsController::class,'rule'])->name('settings.rule');
        Route::get('/settings/email/connect', [GoogleConnectionController::class,'connect'])->name('google.connect');
        Route::get('/settings/email/callback', [GoogleConnectionController::class,'callback'])->name('google.callback');
        Route::post('/settings/email/disconnect', [GoogleConnectionController::class,'disconnect'])->name('google.disconnect');
    });
});
