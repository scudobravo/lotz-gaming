<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Scene;
use App\Models\Project;
use App\Models\UserProgress;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwilioController extends Controller
{
    /**
     * Invia il messaggio iniziale a un utente
     */
    public function sendInitialMessage(Request $request)
    {
        try {
            $validated = $request->validate([
                'phone_number' => 'required|string',
                'project_id' => 'required|exists:projects,id'
            ]);

            $project = Project::with('initialScene')->findOrFail($validated['project_id']);
            
            if (!$project->initialScene) {
                return response()->json(['error' => 'Scena iniziale non trovata'], 404);
            }

            // Creiamo la risposta XML per Twilio
            $response = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
            
            // Aggiungi media se presente
            if ($project->initialScene->media_gif_url) {
                $response->addChild('Media', $project->initialScene->media_gif_url);
            }
            if ($project->initialScene->media_audio_url) {
                $response->addChild('Media', $project->initialScene->media_audio_url);
            }

            // Aggiungi il messaggio formattato in HTML
            $message = $response->addChild('Message');
            $message->addAttribute('format', 'html');
            $message->addChild('Body', $project->initialScene->entry_message);

            return response($response->asXML(), 200)
                ->header('Content-Type', 'text/xml');

        } catch (\Exception $e) {
            Log::error('Errore nell\'invio del messaggio iniziale', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $response = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
            $message = $response->addChild('Message');
            $message->addAttribute('format', 'html');
            $message->addChild('Body', 'Si è verificato un errore. Riprova più tardi.');
            
            return response($response->asXML(), 200)
                ->header('Content-Type', 'text/xml');
        }
    }

    /**
     * Gestisce i messaggi in arrivo da WhatsApp
     */
    public function handleIncomingMessage(Request $request)
    {
        Log::info('Twilio incoming message received', [
            'request' => $request->all(),
            'from' => $request->input('From'),
            'body' => $request->input('Body'),
            'message_sid' => $request->input('MessageSid')
        ]);

        $from = $request->input('From');
        $body = $request->input('Body', '');
        $messageSid = $request->input('MessageSid');

        try {
            // Cerca il progresso dell'utente
            $userProgress = UserProgress::where('phone_number', $from)->first();
            Log::info('User progress trovato', [
                'user_progress' => $userProgress ? $userProgress->toArray() : null
            ]);

            // Se non esiste un progresso, creane uno nuovo con il progetto Subiaco Bibliotech
            if (!$userProgress) {
                $project = Project::where('slug', 'subiaco-bibliotech')->first();
                if (!$project) {
                    return $this->sendErrorResponse('Progetto non trovato. Contatta l\'amministratore.');
                }

                $userProgress = UserProgress::create([
                    'phone_number' => $from,
                    'project_id' => $project->id,
                    'current_scene_id' => $project->initial_scene_id,
                    'attempts_remaining' => 3,
                    'last_interaction_at' => now()
                ]);
                Log::info('Nuovo user progress creato', [
                    'user_progress' => $userProgress->toArray()
                ]);
            }

            // Aggiorna l'ultima interazione
            $userProgress->update(['last_interaction_at' => now()]);

            // Ottieni la scena corrente
            $currentScene = Scene::find($userProgress->current_scene_id);
            Log::info('Scena corrente trovata', [
                'current_scene' => $currentScene ? $currentScene->toArray() : null
            ]);

            if (!$currentScene) {
                return $this->sendErrorResponse('Scena non trovata. Riprova più tardi.');
            }

            // Verifica che la scena appartenga al progetto corretto
            if ($currentScene->project_id !== $userProgress->project_id) {
                return $this->sendErrorResponse('Errore di configurazione del gioco. Contatta l\'amministratore.');
            }

            // Gestisci la scena in base al suo tipo
            $response = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
            
            Log::info('Tipo di scena', [
                'scene_type' => $currentScene->type
            ]);
            
            switch ($currentScene->type) {
                case 'intro':
                    Log::info('Gestione scena intro');
                    $this->handleIntroScene($userProgress, $currentScene, $body, $response);
                    break;
                case 'investigation':
                    Log::info('Gestione scena investigation');
                    $this->handleInvestigationScene($userProgress, $currentScene, $body, $response);
                    break;
                case 'puzzle':
                    Log::info('Gestione scena puzzle');
                    $this->handlePuzzleScene($userProgress, $currentScene, $body, $response);
                    break;
                case 'final':
                    Log::info('Gestione scena final');
                    $this->handleFinalScene($userProgress, $currentScene, $body, $response);
                    break;
                default:
                    Log::error('Tipo di scena non valido', [
                        'scene_type' => $currentScene->type
                    ]);
                    return $this->sendErrorResponse('Tipo di scena non valido.');
            }

            // Log della risposta
            Log::info('Risposta Twilio generata', [
                'response' => $response->asXML(),
                'current_scene' => $currentScene->id,
                'next_scene' => $currentScene->next_scene_id,
                'user_progress' => $userProgress->current_scene_id
            ]);

            return response($response->asXML(), 200)
                ->header('Content-Type', 'text/xml');

        } catch (\Exception $e) {
            Log::error('Error processing Twilio message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return $this->sendErrorResponse('Si è verificato un errore. Riprova più tardi.');
        }
    }

    /**
     * Gestisce una scena di tipo intro
     */
    private function handleIntroScene($userProgress, $scene, $message, $response)
    {
        // Log dei messaggi per debug
        Log::info('Confronto messaggi in handleIntroScene', [
            'user_message' => $message,
            'scene_message' => $scene->entry_message,
            'stripped_user_message' => strip_tags($message),
            'stripped_scene_message' => strip_tags($scene->entry_message)
        ]);

        // Se il messaggio dell'utente è uguale al messaggio della scena, procedi con la scena successiva
        if (trim(strip_tags($message)) === trim(strip_tags($scene->entry_message))) {
            Log::info('Messaggi corrispondono, procedo alla scena successiva', [
                'next_scene_id' => $scene->next_scene_id
            ]);

            // Se c'è una scena successiva, passa ad essa e mostra il suo messaggio
            if ($scene->next_scene_id) {
                $nextScene = Scene::find($scene->next_scene_id);
                if ($nextScene) {
                    // Aggiorna il progresso
                    $userProgress->update(['current_scene_id' => $scene->next_scene_id]);
                    
                    // Aggiungi media se presente
                    if ($nextScene->media_gif_url) {
                        $response->addChild('Media', $nextScene->media_gif_url);
                    }
                    if ($nextScene->media_audio_url) {
                        $response->addChild('Media', $nextScene->media_audio_url);
                    }

                    // Aggiungi il messaggio della nuova scena
                    $message = $response->addChild('Message');
                    $message->addAttribute('format', 'html');
                    $message->addChild('Body', $nextScene->entry_message);
                    
                    Log::info('Scena successiva impostata', [
                        'next_scene_id' => $nextScene->id,
                        'next_scene_message' => $nextScene->entry_message
                    ]);
                }
            } else {
                // Se non ci sono scene successive, mostra un messaggio di fine
                $message = $response->addChild('Message');
                $message->addAttribute('format', 'html');
                $message->addChild('Body', 'Hai completato questa parte del gioco. Presto arriveranno nuove avventure!');
                Log::info('Nessuna scena successiva trovata');
            }
        } else {
            // Se il messaggio è diverso, ripeti il messaggio della scena corrente
            if ($scene->media_gif_url) {
                $response->addChild('Media', $scene->media_gif_url);
            }
            if ($scene->media_audio_url) {
                $response->addChild('Media', $scene->media_audio_url);
            }
            $message = $response->addChild('Message');
            $message->addAttribute('format', 'html');
            $message->addChild('Body', $scene->entry_message);
            
            Log::info('Messaggi non corrispondono, ripeto la scena corrente');
        }
    }

    /**
     * Gestisce una scena di tipo investigation
     */
    private function handleInvestigationScene($userProgress, $scene, $message, $response)
    {
        // Log delle scelte disponibili
        Log::info('Scelte disponibili per la scena', [
            'scene_id' => $scene->id,
            'choices' => $scene->choices->toArray(),
            'user_message' => $message
        ]);

        // Se il messaggio è una scelta valida
        $choice = $scene->choices()->where('label', $message)->first();
        
        if ($choice) {
            Log::info('Scelta valida trovata', [
                'choice' => $choice->toArray()
            ]);

            // Passa alla scena target
            $userProgress->update(['current_scene_id' => $choice->target_scene_id]);
            
            // Ottieni la nuova scena
            $nextScene = Scene::find($choice->target_scene_id);
            
            // Aggiungi media se presente
            if ($nextScene->media_gif_url) {
                $response->addChild('Media', $nextScene->media_gif_url);
            }
            if ($nextScene->media_audio_url) {
                $response->addChild('Media', $nextScene->media_audio_url);
            }

            // Aggiungi il messaggio della nuova scena
            $message = $response->addChild('Message');
            $message->addAttribute('format', 'html');
            $message->addChild('Body', $nextScene->entry_message);
        } else {
            Log::info('Scelta non valida, mostro le opzioni disponibili');
            
            // Se la scelta non è valida, mostra le opzioni disponibili
            $messageText = strip_tags($scene->entry_message) . "\n\nOpzioni disponibili:\n";
            foreach ($scene->choices as $index => $choice) {
                $messageText .= ($index + 1) . ". " . $choice->label . "\n";
            }
            
            // Aggiungi media se presente
            if ($scene->media_gif_url) {
                $response->addChild('Media', $scene->media_gif_url);
            }
            if ($scene->media_audio_url) {
                $response->addChild('Media', $scene->media_audio_url);
            }
            
            $message = $response->addChild('Message');
            $message->addAttribute('format', 'html');
            $message->addChild('Body', $messageText);
        }
    }

    /**
     * Gestisce una scena di tipo puzzle
     */
    private function handlePuzzleScene($userProgress, $scene, $message, $response)
    {
        // Verifica se la risposta è corretta
        if (strtolower($message) === strtolower($scene->correct_answer)) {
            // Aggiungi l'elemento alla collezione se presente
            if ($scene->item_id) {
                $userProgress->collected_items()->attach($scene->item_id, ['collected_at' => now()]);
            }

            // Aggiungi il messaggio di successo
            $message = $response->addChild('Message');
            $message->addAttribute('format', 'html');
            $message->addChild('Body', $scene->success_message);

            // Passa alla scena successiva se presente
            if ($scene->next_scene_id) {
                $userProgress->update(['current_scene_id' => $scene->next_scene_id]);
            }
        } else {
            // Decrementa i tentativi rimanenti
            $userProgress->decrement('attempts_remaining');

            if ($userProgress->attempts_remaining <= 0) {
                // Se non ci sono più tentativi, mostra il messaggio di fallimento
                $message = $response->addChild('Message');
                $message->addAttribute('format', 'html');
                $message->addChild('Body', $scene->failure_message);
            } else {
                // Altrimenti, mostra il messaggio di errore e i tentativi rimanenti
                $message = $response->addChild('Message');
                $message->addAttribute('format', 'html');
                $message->addChild('Body', "Risposta errata. Tentativi rimanenti: " . $userProgress->attempts_remaining);
            }
        }
    }

    /**
     * Gestisce una scena di tipo final
     */
    private function handleFinalScene($userProgress, $scene, $message, $response)
    {
        // Aggiungi media se presente
        if ($scene->media_gif_url) {
            $response->addChild('Media', $scene->media_gif_url);
        }
        if ($scene->media_audio_url) {
            $response->addChild('Media', $scene->media_audio_url);
        }

        // Aggiungi il messaggio finale
        $message = $response->addChild('Message');
        $message->addAttribute('format', 'html');
        $message->addChild('Body', $scene->entry_message);
    }

    /**
     * Invia una risposta di errore
     */
    private function sendErrorResponse($message)
    {
        $response = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
        $message = $response->addChild('Message');
        $message->addAttribute('format', 'html');
        $message->addChild('Body', $message);
        
        return response($response->asXML(), 200)
            ->header('Content-Type', 'text/xml');
    }
}
