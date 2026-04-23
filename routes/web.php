<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/lists', \App\Livewire\ListManager::class)->name('lists.index');
    Route::get('/lists/{taskList}', \App\Livewire\ListDetail::class)->name('lists.show');
});
