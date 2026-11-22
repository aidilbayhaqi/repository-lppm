<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\ReferenceController;
use App\Http\Controllers\Admin\RegistrationApprovalController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\WorkCoverController;
use App\Http\Controllers\RepositoryController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');
Route::get('/', [RepositoryController::class, 'home'])->name('home');
Route::get('/lookups/{entity}', LookupController::class)->middleware('throttle:120,1')->name('lookups');
Route::get('/search', [RepositoryController::class, 'search'])->middleware('throttle:90,1')->name('repository.search');
Route::get('/karya/{type}/{id}', [RepositoryController::class, 'show'])->whereIn('type', ['publikasi', 'buku', 'hki'])->whereNumber('id')->name('repository.show');
Route::get('/dosen', [RepositoryController::class, 'lecturers'])->name('repository.lecturers');
Route::get('/dosen/{dosen}', [RepositoryController::class, 'lecturer'])->name('repository.lecturer');
Route::get('/penulis/{penulis}', [RepositoryController::class, 'author'])->name('repository.author');
Route::get('/jurnal/{jurnal}', [RepositoryController::class, 'journal'])->name('repository.journal');
Route::get('/sampul/{type}/{id}', [WorkCoverController::class, 'show'])->whereIn('type', ['publikasi', 'buku', 'hki'])->whereNumber('id')->name('works.cover.show');
Route::get('/profile/photo', [ProfilePhotoController::class, 'show'])->middleware(['auth', 'active', 'throttle:120,1'])->name('profile.photo.show');
Route::get('/dokumen/{document}/download', [DocumentController::class, 'download'])->middleware('throttle:30,1')->name('documents.download');
Route::middleware(array_filter(['auth', 'active', 'throttle:mutations', config('repository.require_verified_email') ? 'verified' : null]))->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'))->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/photo', [ProfilePhotoController::class, 'store'])->name('profile.photo.store');
    Route::delete('/profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::prefix('karya/{type}')->whereIn('type', ['publikasi', 'buku', 'hki'])->name('works.')->group(function () {
            Route::get('/', [WorkController::class, 'index'])->name('index');
            Route::get('/create', [WorkController::class, 'create'])->name('create');
            Route::post('/', [WorkController::class, 'store'])->name('store');
            Route::get('/{id}', [WorkController::class, 'show'])->whereNumber('id')->name('show');
            Route::get('/{id}/status', [WorkController::class, 'status'])->middleware('throttle:12,1')->whereNumber('id')->name('status');
            Route::get('/{id}/edit', [WorkController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('/{id}', [WorkController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/workflow', [WorkflowController::class, 'store'])->middleware('throttle:20,1')->whereNumber('id')->name('workflow');
            Route::post('/{id}/documents', [DocumentController::class, 'store'])->middleware('throttle:20,1')->whereNumber('id')->name('documents');
            Route::post('/{id}/cover', [WorkCoverController::class, 'store'])->middleware('throttle:20,1')->whereNumber('id')->name('cover.store');
            Route::delete('/{id}/cover', [WorkCoverController::class, 'destroy'])->whereNumber('id')->name('cover.destroy');
        });
        Route::post('/documents/{document}/replace', [DocumentController::class, 'replace'])->middleware('throttle:20,1')->name('documents.replace');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        Route::patch('/documents/{document}/visibility', [DocumentController::class, 'visibility'])->name('documents.visibility');
        Route::get('/document-versions/{version}', [DocumentController::class, 'version'])->name('documents.version');
        Route::middleware('can:manage-reference')->group(function () {
            Route::prefix('master/{entity}')->name('reference.')->group(function () {
                Route::get('/', [ReferenceController::class, 'index'])->name('index');
                Route::get('/create', [ReferenceController::class, 'create'])->name('create');
                Route::post('/', [ReferenceController::class, 'store'])->name('store');
                Route::get('/{id}/status', [ReferenceController::class, 'status'])->middleware('throttle:12,1')->whereNumber('id')->name('status');
                Route::get('/{id}/edit', [ReferenceController::class, 'edit'])->whereNumber('id')->name('edit');
                Route::put('/{id}', [ReferenceController::class, 'update'])->whereNumber('id')->name('update');
                Route::delete('/{id}', [ReferenceController::class, 'destroy'])->whereNumber('id')->name('destroy');
            });
            Route::get('/import', [ImportController::class, 'index'])->name('import.index');
            Route::get('/import/template/{type}', [ImportController::class, 'template'])->name('import.template');
            Route::post('/import/preview', [ImportController::class, 'preview'])->middleware('throttle:10,1')->name('import.preview');
            Route::get('/import/{batch}', [ImportController::class, 'show'])->name('import.show');
            Route::post('/import/{batch}/commit', [ImportController::class, 'commit'])->name('import.commit');
        });
        Route::middleware(['can:manage-users', 'password.confirm'])->prefix('registrations')->name('registrations.')->group(function () {
            Route::get('/', [RegistrationApprovalController::class, 'index'])->name('index');
            Route::get('/{registration}', [RegistrationApprovalController::class, 'show'])->name('show');
            Route::post('/{registration}/approve', [RegistrationApprovalController::class, 'approve'])->name('approve')->middleware(['throttle:20,1', 'fresh-password']);
            Route::post('/{registration}/reject', [RegistrationApprovalController::class, 'reject'])->name('reject')->middleware(['throttle:20,1', 'fresh-password']);
        });
        Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware(['can:manage-users', 'password.confirm', 'fresh-password']);
    });
});
require __DIR__.'/auth.php';
