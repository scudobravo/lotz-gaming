<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwilioController;

Route::get('/projects/{project}', function (App\Models\Project $project) {
    return response()->json($project);
});

// Twilio Webhook
Route::post('/twilio/webhook', [TwilioController::class, 'handleIncomingMessage']); 