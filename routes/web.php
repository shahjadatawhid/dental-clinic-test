<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/dashboard')->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

// M02 Staff & schedules
Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('staff', 'staff.index')->middleware('permission:staff.view')->name('staff.index');
    Volt::route('staff/create', 'staff.form')->middleware('permission:staff.manage')->name('staff.create');
    Volt::route('staff/{staff}/edit', 'staff.form')->middleware('permission:staff.manage')->name('staff.edit');
});

// M01 Administration
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('users', 'admin.users.index')->middleware('permission:users.view')->name('users.index');
    Volt::route('users/create', 'admin.users.form')->middleware('permission:users.manage')->name('users.create');
    Volt::route('users/{user}/edit', 'admin.users.form')->middleware('permission:users.manage')->name('users.edit');

    Volt::route('roles', 'admin.roles.index')->middleware('permission:roles.view')->name('roles.index');
    Volt::route('roles/{role:name}', 'admin.roles.edit')->middleware('permission:roles.view')->name('roles.edit');

    Volt::route('settings', 'admin.settings.index')->middleware('permission:settings.view')->name('settings.index');

    Volt::route('audit', 'admin.audit.index')->middleware('permission:audit.view')->name('audit.index');
});

require __DIR__.'/auth.php';
