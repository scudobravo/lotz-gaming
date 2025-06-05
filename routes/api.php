<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwilioController;
use App\Models\Project;

Route::get('/projects/{slug}', function ($slug) {
    $project = Project::where('slug', $slug)
        ->with(['initialScene' => function($query) {
            $query->select('id', 'project_id', 'entry_message');
        }])
        ->firstOrFail();
    
    return response()->json($project);
});

// Twilio Webhook
Route::match(['post', 'get'], 'twilio/webhook', [TwilioController::class, 'handleIncomingMessage'])->name('twilio.webhook');

// Twilio Send Initial Message
Route::post('/twilio/send-initial-message', [TwilioController::class, 'sendInitialMessage']); 