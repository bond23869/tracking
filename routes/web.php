<?php

use App\Http\Controllers\Base\InvitationsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Team management routes (require permission to manage team)
    Route::middleware('permission:manage team')->group(function () {
        Route::get('users', [InvitationsController::class, 'index'])->name('users.index');
        Route::post('invitations', [InvitationsController::class, 'store'])->name('invitations.store');
        Route::post('invitations/{invitation}/resend', [InvitationsController::class, 'resend'])->name('invitations.resend');
        Route::delete('invitations/{invitation}', [InvitationsController::class, 'destroy'])->name('invitations.destroy');
        Route::patch('users/{user}', [InvitationsController::class, 'updateUser'])->name('users.update');
        Route::delete('users/{user}', [InvitationsController::class, 'removeUser'])->name('users.remove');
    });
});

// Public invitation routes (no auth required)
Route::get('invitations/{token}', [InvitationsController::class, 'show'])->name('invitations.accept');
Route::post('invitations/{token}/accept', [InvitationsController::class, 'accept'])->name('invitations.accept.post');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
