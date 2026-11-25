<?php

use App\Http\Controllers\AccountDeletionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'site.home')->name('home');
Route::view('/confidentialite', 'site.privacy')->name('privacy');
Route::redirect('/privacy', '/confidentialite');

Route::get('/suppression-compte', [AccountDeletionController::class, 'create'])->name('account.delete');
Route::post('/suppression-compte', [AccountDeletionController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('account.delete.store');
Route::redirect('/delete-account', '/suppression-compte');
