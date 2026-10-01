<?php

use App\Http\Controllers\AcceptInvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/app'));
Route::get('/login', fn () => redirect('/app/login'))->name('login');
Route::get('/convites/{invitation}', [AcceptInvitationController::class, 'show'])->whereNumber('invitation');
Route::post('/convites/{invitation}/preparar', [AcceptInvitationController::class, 'prepare'])->whereNumber('invitation')->middleware('throttle:10,1');
Route::post('/convites/{invitation}/aceitar', [AcceptInvitationController::class, 'accept'])->whereNumber('invitation')->middleware('throttle:10,1');
