<?php
use Illuminate\Support\Facades\Route;
use Ekonomi\SsoAuth\Http\Controllers\SsoController;

Route::get('/auth/redirect', [SsoController::class, 'redirect'])->name('sso.login');
Route::get('/auth/callback', [SsoController::class, 'callback']);
Route::post('/logout', [SsoController::class, 'logout'])->name('logout');
Route::get('/auth/close-tab', [SsoController::class, 'closeTab'])->name('sso.close-tab');