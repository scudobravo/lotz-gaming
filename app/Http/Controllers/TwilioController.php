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
     * Formatta il messaggio HTML per Twilio
     */
    private function formatMessageForTwilio($message)
    {
        // Decodifica prima le entità HTML
        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Rimuovi gli spazi non necessari
        $message = preg_replace('/\s+/', ' ', $message);
        
        // Sostituisci &nbsp; con spazi normali
        $message = str_replace('&nbsp;', ' ', $message);
        
        // Rimuovi le virgolette non necessarie
        $message = str_replace('"', '', $message);
        
        // Mantieni solo i tag HTML consentiti
        $message = strip_tags($message, '<p><em><strong><br><b><i>');
        
        // Assicurati che il messaggio sia valido HTML
        if (!preg_match('/<[^>]*>/', $message)) {
            $message = '<p>' . $message . '</p>';
        }
        
        return $message;
    }

    /**
     * Invia il messaggio iniziale a un utente
     */
    public function sendInitialMessage(Request $request)
    {
        try {
            Log::info('sendInitialMessage chiamato', [
                'method' => $request->method(),
                'request' => $request->all()
            ]);

            // Se è una richiesta POST, significa che è un messaggio in arrivo
            if ($request->isMethod('post')) {
                Log::info('Richiesta POST rilevata');
                
                // Estrai il numero di telefono dalla richiesta Twilio
                $phoneNumber = $request->input('From');
                $projectId = $request->input('project_id', 1);

                if (!$phoneNumber) {
                    Log::error('Numero di telefono mancante nella richiesta Twilio');
                    return $this->sendErrorResponse('Numero di telefono mancante');
                }

                // Cerca il progresso dell'utente
                $userProgress = UserProgress::where('phone_number', $phoneNumber)
                    ->where('project_id', $projectId)
                    ->first();

                Log::info('User progress trovato in sendInitialMessage', ['user_progress' => $userProgress]);

                // Se il messaggio è "Invia questo messaggio per iniziare il gioco!", resetta il progresso
                if ($request->input('Body') === 'Invia questo messaggio per iniziare il gioco!') {
                    Log::info('Messaggio iniziale rilevato, reset del progresso');
                    
                    // Se esiste un progresso, eliminalo
                    if ($userProgress) {
                        $userProgress->delete();
                        Log::info('Progresso esistente eliminato');
                    }

                    // Ottieni il progetto e la sua scena iniziale
                    $project = Project::find($projectId);
                    if (!$project) {
                        Log::error('Progetto non trovato', ['project_id' => $projectId]);
                        return $this->sendErrorResponse('Progetto non trovato');
                    }

                    // Ottieni la scena iniziale
                    $initialScene = Scene::find($project->initial_scene_id);
                    if (!$initialScene) {
                        Log::error('Scena iniziale non trovata', ['initial_scene_id' => $project->initial_scene_id]);
                        return $this->sendErrorResponse('Scena iniziale non trovata');
                    }

                    // Crea un nuovo progresso utente
                    $userProgress = UserProgress::create([
                        'phone_number' => $phoneNumber,
                        'project_id' => $projectId,
                        'current_scene_id' => $initialScene->id,
                        'attempts_remaining' => 3,
                        'last_interaction_at' => now()
                    ]);

                    // Costruisci la risposta XML
                    $xml = '<?xml version="1.0" encoding="UTF-8"?><Response>';
                    
                    // Aggiungi media se presente
                    if ($initialScene->media_gif_url) {
                        $xml .= '<Message><Media>' . config('app.url') . $initialScene->media_gif_url . '</Media></Message>';
                        Log::info('Aggiunto media GIF', ['url' => $initialScene->media_gif_url]);
                    }
                    if ($initialScene->media_audio_url) {
                        $xml .= '<Message><Media>' . config('app.url') . $initialScene->media_audio_url . '</Media></Message>';
                        Log::info('Aggiunto media audio', ['url' => $initialScene->media_audio_url]);
                    }

                    // Aggiungi il messaggio formattato in HTML
                    $xml .= '<Message format="html"><Body>' . $initialScene->entry_message . '</Body></Message>';
                    $xml .= '</Response>';

                    Log::info('Risposta iniziale inviata', [
                        'response' => $xml,
                        'media_gif_url' => $initialScene->media_gif_url,
                        'media_audio_url' => $initialScene->media_audio_url,
                        'message' => $initialScene->entry_message
                    ]);

                    return response($xml, 200)
                        ->header('Content-Type', 'text/xml');
                }

                // Se non è il messaggio iniziale, passa a handleIncomingMessage
                Log::info('Messaggio non iniziale, passaggio a handleIncomingMessage');
                return $this->handleIncomingMessage($request);
            }

            // Se è una richiesta GET, significa che è il messaggio iniziale
            Log::info('Richiesta GET rilevata, invio messaggio iniziale');
            $xml = '<?xml version="1.0" encoding="UTF-8"?><Response>';
            $xml .= '<Message><Body>Invia questo messaggio per iniziare il gioco!</Body></Message>';
            $xml .= '</Response>';

            Log::info('Risposta Twilio generata per messaggio di benvenuto', [
                'response' => $xml
            ]);

            return response($xml, 200)
                ->header('Content-Type', 'text/xml');

        } catch (\Exception $e) {
            Log::error('Errore nell\'invio del messaggio di benvenuto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $xml = '<?xml version="1.0" encoding="UTF-8"?><Response>';
            $xml .= '<Message><Body>Si è verificato un errore. Riprova più tardi.</Body></Message>';
            $xml .= '</Response>';
            
            return response($xml, 200)
                ->header('Content-Type', 'text/xml');
        }
    }

    /**
     * Gestisce i messaggi in arrivo da WhatsApp
     */
    public function handleIncomingMessage(Request $request)
    {
        try {
            Log::info('Twilio incoming message received', [
                'request' => $request->all(),
                'from' => $request->input('From'),
                'body' => $request->input('Body'),
                'message_sid' => $request->input('MessageSid')
            ]);

            // Estrai il numero di telefono dalla richiesta Twilio
            $phoneNumber = $request->input('From');
            $projectId = $request->input('project_id', 1);

            if (!$phoneNumber) {
                Log::error('Numero di telefono mancante nella richiesta Twilio');
                return $this->sendErrorResponse('Numero di telefono mancante');
            }

            // Cerca il progresso dell'utente
            $userProgress = UserProgress::where('phone_number', $phoneNumber)
                ->where('project_id', $projectId)
                ->first();

            Log::info('User progress trovato', ['user_progress' => $userProgress]);

            if (!$userProgress) {
                Log::error('Progresso utente non trovato');
                return $this->sendErrorResponse('Progresso utente non trovato');
            }

            // Ottieni la scena corrente
            $currentScene = Scene::find($userProgress->current_scene_id);
            if (!$currentScene) {
                Log::error('Scena corrente non trovata', ['scene_id' => $userProgress->current_scene_id]);
                return $this->sendErrorResponse('Scena corrente non trovata');
            }

            Log::info('Scena corrente trovata', ['current_scene' => $currentScene]);

            // Prepara la risposta
            $response = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');

            // Gestisci la scena in base al suo tipo
            Log::info('Tipo di scena', ['scene_type' => $currentScene->type]);

            switch ($currentScene->type) {
                case 'intro':
                    Log::info('Gestione scena intro');
                    $this->handleIntroScene($userProgress, $currentScene, $request->input('Body'), $response);
                    break;
                case 'investigation':
                    Log::info('Gestione scena investigation');
                    $this->handleInvestigationScene($userProgress, $currentScene, $request->input('Body'), $response);
                    break;
                case 'puzzle':
                    Log::info('Gestione scena puzzle');
                    $this->handlePuzzleScene($userProgress, $currentScene, $request->input('Body'), $response);
                    break;
                case 'final':
                    Log::info('Gestione scena final');
                    $this->handleFinalScene($userProgress, $currentScene, $request->input('Body'), $response);
                    break;
                default:
                    Log::error('Tipo di scena non valido', ['type' => $currentScene->type]);
                    return $this->sendErrorResponse('Tipo di scena non valido');
            }

            Log::info('Risposta Twilio generata', [
                'response' => $response->asXML(),
                'current_scene' => $userProgress->current_scene_id,
                'next_scene' => $userProgress->next_scene_id,
                'user_progress' => $userProgress->id
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
                        $media = $response->addChild('Media');
                        $media[0] = config('app.url') . $nextScene->media_gif_url;
                    }
                    if ($nextScene->media_audio_url) {
                        $media = $response->addChild('Media');
                        $media[0] = config('app.url') . $nextScene->media_audio_url;
                    }

                    // Aggiungi il messaggio della nuova scena
                    $message = $response->addChild('Message');
                    $message->addAttribute('format', 'html');
                    $body = $message->addChild('Body');
                    $body[0] = $this->formatMessageForTwilio($nextScene->entry_message);
                    
                    Log::info('Scena successiva impostata', [
                        'next_scene_id' => $nextScene->id,
                        'next_scene_message' => $nextScene->entry_message
                    ]);
                }
            } else {
                // Se non ci sono scene successive, mostra un messaggio di fine
                $message = $response->addChild('Message');
                $message->addAttribute('format', 'html');
                $body = $message->addChild('Body');
                $body[0] = '<p>Hai completato questa parte del gioco. Presto arriveranno nuove avventure!</p>';
                Log::info('Nessuna scena successiva trovata');
            }
        } else {
            // Se il messaggio è diverso, ripeti il messaggio della scena corrente
            if ($scene->media_gif_url) {
                $media = $response->addChild('Media');
                $media[0] = config('app.url') . $scene->media_gif_url;
            }
            if ($scene->media_audio_url) {
                $media = $response->addChild('Media');
                $media[0] = config('app.url') . $scene->media_audio_url;
            }
            $message = $response->addChild('Message');
            $message->addAttribute('format', 'html');
            $body = $message->addChild('Body');
            $body[0] = $this->formatMessageForTwilio($scene->entry_message);
            
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
                $media = $response->addChild('Media');
                $media[0] = config('app.url') . $nextScene->media_gif_url;
            }
            if ($nextScene->media_audio_url) {
                $media = $response->addChild('Media');
                $media[0] = config('app.url') . $nextScene->media_audio_url;
            }

            // Aggiungi il messaggio della nuova scena
            $message = $response->addChild('Message');
            $message->addAttribute('format', 'html');
            $body = $message->addChild('Body');
            $body[0] = $this->formatMessageForTwilio($nextScene->entry_message);
        } else {
            Log::info('Scelta non valida, mostro le opzioni disponibili');
            
            // Aggiungi media se presente
            if ($scene->media_gif_url) {
                $media = $response->addChild('Media');
                $media[0] = config('app.url') . $scene->media_gif_url;
            }
            if ($scene->media_audio_url) {
                $media = $response->addChild('Media');
                $media[0] = config('app.url') . $scene->media_audio_url;
            }
            
            // Costruisci il messaggio con le opzioni
            $message = $response->addChild('Message');
            $message->addAttribute('format', 'html');
            $body = $message->addChild('Body');
            
            // Aggiungi il messaggio principale con la formattazione HTML
            $messageText = $this->formatMessageForTwilio($scene->entry_message);
            
            // Aggiungi le opzioni disponibili
            if ($scene->choices->count() > 0) {
                $messageText .= "\n\n<b>Opzioni disponibili:</b>\n";
                foreach ($scene->choices as $index => $choice) {
                    $messageText .= ($index + 1) . ". " . $choice->label . "\n";
                }
            }
            
            $body[0] = $messageText;
            
            Log::info('Messaggio di risposta generato', [
                'message' => $messageText,
                'choices' => $scene->choices->toArray()
            ]);
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
            $body = $message->addChild('Body');
            $body[0] = $this->formatMessageForTwilio($scene->success_message);

            // Passa alla scena successiva se presente
            if ($scene->next_scene_id) {
                $userProgress->update(['current_scene_id' => $scene->next_scene_id]);
                
                // Ottieni la nuova scena
                $nextScene = Scene::find($scene->next_scene_id);
                if ($nextScene) {
                    // Aggiungi media se presente
                    if ($nextScene->media_gif_url) {
                        $media = $response->addChild('Media');
                        $media[0] = config('app.url') . $nextScene->media_gif_url;
                    }
                    if ($nextScene->media_audio_url) {
                        $media = $response->addChild('Media');
                        $media[0] = config('app.url') . $nextScene->media_audio_url;
                    }

                    // Aggiungi il messaggio della nuova scena
                    $message = $response->addChild('Message');
                    $message->addAttribute('format', 'html');
                    $body = $message->addChild('Body');
                    $body[0] = $this->formatMessageForTwilio($nextScene->entry_message);
                }
            }
        } else {
            // Decrementa i tentativi rimanenti
            $userProgress->decrement('attempts_remaining');

            if ($userProgress->attempts_remaining <= 0) {
                // Se non ci sono più tentativi, mostra il messaggio di fallimento
                $message = $response->addChild('Message');
                $message->addAttribute('format', 'html');
                $body = $message->addChild('Body');
                $body[0] = $this->formatMessageForTwilio($scene->failure_message);
            } else {
                // Altrimenti, mostra il messaggio di errore e i tentativi rimanenti
                $message = $response->addChild('Message');
                $message->addAttribute('format', 'html');
                $body = $message->addChild('Body');
                $body[0] = '<p>Risposta errata. <b>Tentativi rimanenti: ' . $userProgress->attempts_remaining . '</b></p>';
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
            $media = $response->addChild('Media');
            $media[0] = config('app.url') . $scene->media_gif_url;
        }
        if ($scene->media_audio_url) {
            $media = $response->addChild('Media');
            $media[0] = config('app.url') . $scene->media_audio_url;
        }

        // Aggiungi il messaggio finale
        $message = $response->addChild('Message');
        $message->addAttribute('format', 'html');
        $body = $message->addChild('Body');
        $body[0] = $this->formatMessageForTwilio($scene->entry_message);
    }

    /**
     * Invia una risposta di errore
     */
    private function sendErrorResponse($message)
    {
        $response = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
        $message = $response->addChild('Message');
        $message->addAttribute('format', 'html');
        $body = $message->addChild('Body');
        $body[0] = '<p>' . $message . '</p>';
        
        return response($response->asXML(), 200)
            ->header('Content-Type', 'text/xml');
    }
}
