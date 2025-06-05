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
            $response->addChild('Message', $project->initialScene->entry_message);

            return response($response->asXML(), 200)
                ->header('Content-Type', 'text/xml');

        } catch (\Exception $e) {
            Log::error('Errore nell\'invio del messaggio iniziale', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $response = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
            $response->addChild('Message', 'Si è verificato un errore. Riprova più tardi.');
            
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

            // Se non esiste un progresso e il messaggio è vuoto o "join", mostra il messaggio iniziale
            if (!$userProgress && (empty(trim($body)) || strtolower(trim($body)) === 'join')) {
                $response = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
                $response->addChild('Message', 'Benvenuto in Subiaco Bibliotech! Per iniziare, invia "join subiaco-bibliotech"');
                return response($response->asXML(), 200)->header('Content-Type', 'text/xml');
            }

            // Estrai il progetto dal messaggio se è un messaggio di join
            $project = null;
            if (preg_match('/^join\s+(\w+)$/i', $body, $matches)) {
                $projectSlug = $matches[1];
                $project = Project::where('slug', $projectSlug)->first();
                
                if (!$project) {
                    return $this->sendErrorResponse('Progetto non trovato. Verifica il codice e riprova.');
                }
            }

            // Se non esiste un progresso e non è un messaggio di join, invia errore
            if (!$userProgress && !$project) {
                return $this->sendErrorResponse('Per iniziare, invia "join subiaco-bibliotech"');
            }

            // Se esiste un progresso ma è un nuovo join, verifica che sia lo stesso progetto
            if ($userProgress && $project && $userProgress->project_id !== $project->id) {
                return $this->sendErrorResponse('Hai già un gioco in corso. Completa quello prima di iniziarne un altro.');
            }

            // Se non esiste un progresso e abbiamo un progetto, creane uno nuovo
            if (!$userProgress && $project) {
                $userProgress = UserProgress::create([
                    'phone_number' => $from,
                    'project_id' => $project->id,
                    'current_scene_id' => $project->initial_scene_id,
                    'attempts_remaining' => 3,
                    'last_interaction_at' => now()
                ]);
            }

            // Aggiorna l'ultima interazione
            $userProgress->update(['last_interaction_at' => now()]);

            // Ottieni la scena corrente
            $currentScene = Scene::find($userProgress->current_scene_id);
            if (!$currentScene) {
                return $this->sendErrorResponse('Scena non trovata. Riprova più tardi.');
            }

            // Verifica che la scena appartenga al progetto corretto
            if ($currentScene->project_id !== $userProgress->project_id) {
                return $this->sendErrorResponse('Errore di configurazione del gioco. Contatta l\'amministratore.');
            }

            // Gestisci la scena in base al suo tipo
            $response = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
            
            switch ($currentScene->type) {
                case 'intro':
                    $this->handleIntroScene($userProgress, $currentScene, $body, $response);
                    break;
                case 'investigation':
                    $this->handleInvestigationScene($userProgress, $currentScene, $body, $response);
                    break;
                case 'puzzle':
                    $this->handlePuzzleScene($userProgress, $currentScene, $body, $response);
                    break;
                case 'final':
                    $this->handleFinalScene($userProgress, $currentScene, $body, $response);
                    break;
                default:
                    return $this->sendErrorResponse('Tipo di scena non valido.');
            }

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
        // Aggiungi media se presente
        if ($scene->media_gif) {
            $response->addChild('Media', $scene->media_gif);
        }
        if ($scene->media_audio) {
            $response->addChild('Media', $scene->media_audio);
        }

        // Aggiungi il messaggio della scena
        $response->addChild('Message', $scene->entry_message);

        // Se c'è una scena successiva, passa ad essa
        if ($scene->next_scene_id) {
            $userProgress->update(['current_scene_id' => $scene->next_scene_id]);
        }
    }

    /**
     * Gestisce una scena di tipo investigation
     */
    private function handleInvestigationScene($userProgress, $scene, $message, $response)
    {
        // Se il messaggio è una scelta valida
        $choice = $scene->choices()->where('label', $message)->first();
        
        if ($choice) {
            // Passa alla scena target
            $userProgress->update(['current_scene_id' => $choice->target_scene_id]);
            
            // Ottieni la nuova scena
            $nextScene = Scene::find($choice->target_scene_id);
            
            // Aggiungi media se presente
            if ($nextScene->media_gif) {
                $response->addChild('Media', $nextScene->media_gif);
            }
            if ($nextScene->media_audio) {
                $response->addChild('Media', $nextScene->media_audio);
            }

            // Aggiungi il messaggio della nuova scena
            $response->addChild('Message', $nextScene->entry_message);
        } else {
            // Se la scelta non è valida, mostra le opzioni disponibili
            $messageText = $scene->entry_message . "\n\nOpzioni disponibili:\n";
            foreach ($scene->choices as $index => $choice) {
                $messageText .= ($index + 1) . ". " . $choice->label . "\n";
            }
            $response->addChild('Message', $messageText);
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
            $response->addChild('Message', $scene->success_message);

            // Passa alla scena successiva se presente
            if ($scene->next_scene_id) {
                $userProgress->update(['current_scene_id' => $scene->next_scene_id]);
            }
        } else {
            // Decrementa i tentativi rimanenti
            $userProgress->decrement('attempts_remaining');

            if ($userProgress->attempts_remaining <= 0) {
                // Se non ci sono più tentativi, mostra il messaggio di fallimento
                $response->addChild('Message', $scene->failure_message);
            } else {
                // Altrimenti, mostra il messaggio di errore e i tentativi rimanenti
                $response->addChild('Message', "Risposta errata. Tentativi rimanenti: " . $userProgress->attempts_remaining);
            }
        }
    }

    /**
     * Gestisce una scena di tipo final
     */
    private function handleFinalScene($userProgress, $scene, $message, $response)
    {
        // Aggiungi media se presente
        if ($scene->media_gif) {
            $response->addChild('Media', $scene->media_gif);
        }
        if ($scene->media_audio) {
            $response->addChild('Media', $scene->media_audio);
        }

        // Aggiungi il messaggio finale
        $response->addChild('Message', $scene->entry_message);
    }

    /**
     * Invia una risposta di errore
     */
    private function sendErrorResponse($message)
    {
        $response = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
        $response->addChild('Message', $message);
        
        return response($response->asXML(), 200)
            ->header('Content-Type', 'text/xml');
    }
}
