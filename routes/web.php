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
