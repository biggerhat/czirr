<?php

use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\Settings\DataExportController;
use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/export', DataExportController::class)->middleware('throttle:5,1')->name('data.export');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/notifications', [NotificationController::class, 'edit'])->name('notifications.edit');
    Route::post('settings/timezone/detect', [NotificationController::class, 'detectTimezone'])->name('timezone.detect');
    Route::patch('settings/notifications', [NotificationController::class, 'update'])->name('notifications.update');

    Route::post('settings/push-subscription', [NotificationController::class, 'subscribe'])->name('push.subscribe');
    Route::delete('settings/push-subscription', [NotificationController::class, 'unsubscribe'])->name('push.unsubscribe');

    Route::post('settings/calendar-feed', [CalendarFeedController::class, 'regenerate'])->name('calendar-feed.regenerate');
    Route::delete('settings/calendar-feed', [CalendarFeedController::class, 'destroy'])->name('calendar-feed.destroy');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

});
