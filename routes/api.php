<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwilioController;
use App\Models\Project;
use App\Models\Scene;

Route::get('/projects/{slug}', function ($slug) {
    $project = Project::where('slug', $slug)->firstOrFail();
    
    // Carica esplicitamente la scena iniziale
    $initialScene = Scene::where('id', $project->initial_scene_id)->first();
    $project->initialScene = $initialScene;
    
    return response()->json($project);
});

// Twilio Webhook
Route::match(['post', 'get'], 'twilio/webhook', [TwilioController::class, 'sendInitialMessage'])->name('twilio.webhook');

// Twilio Handle Incoming Message
Route::post('/twilio/handle-message', [TwilioController::class, 'handleIncomingMessage'])->name('twilio.handle-message');

// Twilio Send Initial Message
Route::post('/twilio/send-initial-message', [TwilioController::class, 'sendInitialMessage']); 