<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\Admin\NewTicketController;
use App\Http\Controllers\Admin\NyTicketController;

use App\Http\Controllers\TicketStatusController;
use App\Http\Controllers\AllTicketsController;
use App\Http\Controllers\TicketV2Controller;

use App\Http\Controllers\MyTicketsController;

use App\Http\Controllers\ClientController;

use App\Http\Controllers\CsmController;



Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [TicketStatusController::class, 'dashboardStats'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


    Route::get('/', [ClientController::class, 'index'])->name('client.main');
    Route::post('/client/tickets', [ClientController::class, 'store'])->name('client.tickets.store');
    Route::post('/client-tickets-v2', [ClientController::class, 'store'])->name('tickets.v2.store');
    Route::get('/tickets/{ref}/attachments', [ClientController::class, 'listAttachments'])
        ->name('tickets.attachments.index');

    Route::get('/tickets/{ref}/attachments/{attachment}', [ClientController::class, 'downloadAttachment'])
        ->whereNumber('attachment')
        ->name('tickets.attachments.download');

    Route::get('/client/track/ref/{ref}', [ClientController::class, 'trackByRef'])->name('client.track.byref');
    Route::get('/client/ticket/{ref}', [ClientController::class, 'ticketShow']);
    Route::get('/client/ticket/{ref}/messages', [ClientController::class, 'ticketMessages']);
    Route::post('/client/ticket/{ref}/messages', [ClientController::class, 'postTicketMessage']);

    Route::get('/client/ticket/{ref}', [ClientController::class, 'ticketShow']);
    Route::get('/client/ticket/{ref}/rating', [ClientController::class, 'ratingShow']);
    Route::post('/client/ticket/{ref}/rating', [ClientController::class, 'ratingStore']);
    Route::get('/client/rate/{ref}', [ClientController::class, 'ratePage'])->name('client.rate');

    Route::get('/csm/check/{ref}', [CsmController::class, 'check']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin Routes
    Route::get('/admin/new-tickets', [NewTicketController::class, 'index'])->name('admin.new-tickets');
    Route::get('/admin/my-tickets', [MyTicketsController::class, 'index'])->name('admin.my-tickets');

    Route::get('/all-tickets', [AllTicketsController::class, 'index'])->name('tickets.all');
    Route::put('/tickets/{id}', [TicketStatusController::class, 'update'])->name('tickets.update');
    Route::put('/tickets-attend/{id}', [TicketStatusController::class, 'attend'])->name('tickets.attend');
    Route::put('/tickets-complete/{id}', [TicketStatusController::class, 'complete'])->name('tickets.complete');
    Route::put('/tickets-close/{id}', [TicketStatusController::class, 'close'])->name('tickets.close');

    Route::get('/tickets/logs/{reference_code}', [TicketStatusController::class, 'getActivityLogs']);
    Route::post('/tickets-v2', [TicketStatusController::class, 'storeNew'])->name('tickets.v2.store');

    Route::prefix('admin/tickets-v2')->group(function () {
        Route::get('/', [TicketV2Controller::class, 'index'])->name('tickets-v2.index');
        Route::get('/create', [TicketV2Controller::class, 'create'])->name('tickets-v2.create');
        Route::post('/', [TicketV2Controller::class, 'store'])->name('tickets-v2.store'); // ✅ fixed here
    });

    Route::delete('/admin/tickets-v2/{id}', [TicketV2Controller::class, 'destroy'])->name('tickets-v2.destroy');

    Route::get('/tickets-v2/data', function () {
        $tickets = \App\Models\TicketClientDetail::orderByDesc('created_at')->get();

        return inertia('TicketsV2/AllTicketsV2', [
            'tickets' => $tickets,
        ]);
    });

    Route::get('/ratings/pending', [\App\Http\Controllers\RatingsController::class, 'pending'])->name('ratings.pending');

    Route::get('/ticket-employees', [TicketV2Controller::class, 'getEmployees']);



});

require __DIR__.'/auth.php';
