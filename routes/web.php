<?php

use App\Http\Controllers\OnboardingBuddyController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'buddy');
Route::post('/onboarding-buddy/ask', [OnboardingBuddyController::class, 'ask']);
