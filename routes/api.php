<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwilioController;

Route::get('/projects/{project}', function (App\Models\Project $project) {
    return response()->json($project);
});

// Twilio Webhook
Route::match(['post', 'get'], 'twilio/webhook', [TwilioController::class, 'handleIncomingMessage'])->name('twilio.webhook'); 