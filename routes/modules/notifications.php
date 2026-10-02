<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
| Centro de notificaciones: cada usuario accede sólo a las propias (sin permiso especial).
*/

Route::get('notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('notificaciones/leer-todas', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
Route::post('notificaciones/{notification}/abrir', [NotificationController::class, 'open'])->whereUuid('notification')->name('notifications.open');
Route::post('notificaciones/{notification}/no-leida', [NotificationController::class, 'markUnread'])->whereUuid('notification')->name('notifications.unread');
