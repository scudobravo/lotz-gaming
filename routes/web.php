<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\CharacterController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Rotte pubbliche
Route::get('/disclaimer/{project_slug}', function ($project_slug) {
    return Inertia::render('Disclaimer', [
        'project_slug' => $project_slug
    ]);
})->name('disclaimer');

Route::get('/subiaco-bibliotech', function () {
    return Inertia::render('Disclaimer', [
        'project_slug' => 'subiaco-bibliotech'
    ]);
})->name('subiaco-bibliotech');

// Rotte protette
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('projects', ProjectController::class);
    Route::resource('scenes', SceneController::class);
    Route::resource('items', ItemController::class);
    Route::resource('characters', CharacterController::class);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
