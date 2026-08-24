<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/student');
});

Route::get('/login', function () {
    return redirect('/admin#login');
})->name('login');

Route::get('/student', function () {
    return view('student');
});

Route::get('/onboarding', function () {
    return view('student');
});

Route::get('/admin', function () {
    return view('admin');
});

// APK Download Routes (Token-bound & Direct)
Route::get('/download/app', [\App\Http\Controllers\Api\AppDownloadController::class, 'downloadDirect'])->name('app.download.direct');
Route::get('/download/app/{token}', [\App\Http\Controllers\Api\AppDownloadController::class, 'download'])->name('app.download');

// Quick Diagnostic Route
Route::get('/test-diag', function () {
    $results = [];
    $results['php_version'] = PHP_VERSION;
    $results['app_key'] = !empty(config('app.key'));
    $results['storage_writable'] = is_writable(storage_path('framework/views'));
    
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $results['database_connected'] = true;
        $results['database_name'] = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        $results['users_count'] = \App\Models\User::count();
        $results['events_count'] = \App\Models\Event::count();
        $results['attendance_count'] = \App\Models\Attendance::count();
        $results['devices_count'] = \App\Models\Device::count();
    } catch (\Throwable $e) {
        $results['database_connected'] = false;
        $results['database_error'] = $e->getMessage();
    }

    return response()->json($results);
});
