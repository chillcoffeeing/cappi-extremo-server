<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RepresentativeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->name('auth.verify-email');
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/participantes', [ParticipantController::class, 'index'])
        ->name('participants.index');
    Route::post('/participantes', [ParticipantController::class, 'store'])
        ->name('participants.store');
    Route::get('/participantes/{participant}', [ParticipantController::class, 'show'])
        ->name('participants.show');
    Route::patch('/participantes/{participant}', [ParticipantController::class, 'update'])
        ->name('participants.update');
    Route::put('/participantes/{participant}/foto', [ParticipantController::class, 'updatePhoto'])
        ->name('participants.photo');
    Route::get('/participantes/{participant}/wizard', [ParticipantController::class, 'wizard'])
        ->name('participants.wizard');
    Route::post('/participantes/{participant}/wizard/step', [ParticipantController::class, 'saveWizardStep'])
        ->name('participants.wizard.step');
    Route::post('/participantes/{participant}/wizard/complete', [ParticipantController::class, 'completeWizard'])
        ->name('participants.wizard.complete');
    Route::get('/onboarding/{draftId}', [OnboardingController::class, 'show'])
        ->name('onboarding.show');
    Route::post('/onboarding/{draftId}/step', [OnboardingController::class, 'saveStep'])
        ->name('onboarding.step');
    Route::post('/onboarding/{draftId}/complete', [OnboardingController::class, 'complete'])
        ->name('onboarding.complete');
    Route::get('/representante', [RepresentativeController::class, 'show'])
        ->name('representative.show');
    Route::put('/representante/contacto', [RepresentativeController::class, 'updateContact'])
        ->name('representative.contact');
    Route::put('/representante/identificacion', [RepresentativeController::class, 'updateIdentification'])
        ->name('representative.identification');
    Route::put('/representante/encargado', [RepresentativeController::class, 'updatePickup'])
        ->name('representative.pickup');
    Route::put('/representante/foto', [RepresentativeController::class, 'updatePhoto'])
        ->name('representative.photo');
    Route::get('/planes/activo', [PlanController::class, 'active'])
        ->name('plans.active');
    Route::get('/pagos', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/pagos/balance', [PaymentController::class, 'balance'])->name('payments.balance');
    Route::get('/config/metodos-pago', [PaymentController::class, 'methods'])->name('payment-methods.index');
    Route::post('/pagos', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/ordenes', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/ordenes', [OrderController::class, 'store'])->name('orders.store');
    Route::post('/ordenes/{order}/enlazar-pago', [OrderController::class, 'linkPayment'])->name('orders.link-payment');
    Route::get('/tienda/productos', [ProductController::class, 'index'])->name('products.index');
    Route::get('/inscripciones/{participant}/detalle', [EnrollmentController::class, 'show'])
        ->name('enrollments.show');
});
