<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AnnouncementController;
use App\Services\AnnouncementService;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    return view('login');
});

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::post('/login', function () {
    $email = request('email');
    $password = request('password');
    return (new AuthController())->login($email, $password);
});

Route::get('/logout', [AuthController::class, 'logout']);

Route::get('/dashboard', function () {
    return view('dashboard', [
        'announcements' => (new AnnouncementService())->getAll()
    ]);
})->name('dashboard')
    ->middleware('user.session');

Route::post(
    '/news/announcement',
    [AnnouncementController::class, 'store']
);

Route::post('/news/announcement/cancel/{id}', [AnnouncementController::class, 'cancel']);
Route::post('/news/announcement/send/{id}', [AnnouncementController::class, 'send']);
