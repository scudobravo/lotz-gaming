<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Scene;
use App\Models\Project;
use App\Models\UserProgress;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Twilio\TwiML\MessagingResponse;

class TwilioController extends Controller
{
    protected $twilioClient;

    public function __construct()
    {
        $this->twilioClient = new Client(
            config('services.twilio.account_sid'),
            config('services.twilio.auth_token')
        );
    }

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
     * Prepara un URL media per Twilio
     */
    private function prepareMediaUrl($path)
    {
        $cleanPath = str_replace('/storage/', '', $path);
        $url = str_replace('http://', 'https://', config('app.url') . '/storage/' . $cleanPath);
        $url = str_replace(' ', '%20', $url);
        
        // Verifica estensione file
        $extension = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
        
        // Se il nome del file contiene .mp4 ma l'estensione è .gif, forziamo l'estensione .mp4
        if (strpos($cleanPath, '.mp4') !== false && $extension === 'gif') {
            $cleanPath = str_replace('.gif', '.mp4', $cleanPath);
            $url = str_replace('.gif', '.mp4', $url);
            $extension = 'mp4';
        }
        
        $validMediaTypes = [
            'images' => ['jpg', 'jpeg', 'png', 'gif', 'mp4'],
            'audio' => ['mp3', 'ogg', 'amr', 'wav']
        ];
        
        if (!in_array($extension, array_merge($validMediaTypes['images'], $validMediaTypes['audio']))) {
            Log::error('Formato media non supportato', [
                'path' => $path,
                'extension' => $extension
            ]);
            throw new \Exception("Formato file non supportato: .$extension");
        }
        
        return $url;
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

            if ($request->isMethod('post')) {
                Log::info('Richiesta POST rilevata');
                
                $phoneNumber = $request->input('From') ?? $request->input('phone_number');
                $projectId = $request->input('project_id', 1);
                $messageBody = $request->input('Body');

                if (!$phoneNumber) {
                    Log::error('Numero di telefono mancante');
                    return $this->sendErrorResponse('Numero di telefono mancante');
                }

                if (!str_starts_with($phoneNumber, 'whatsapp:')) {
                    $phoneNumber = 'whatsapp:' . $phoneNumber;
                }

                if ($messageBody === 'Invia questo messaggio per iniziare il gioco!') {
                    Log::info('Messaggio iniziale rilevato, reset del progresso');
                    
                    UserProgress::where('phone_number', $phoneNumber)
                        ->where('project_id', $projectId)
                        ->delete();

                    $project = Project::find($projectId);
                    if (!$project) {
                        return $this->sendErrorResponse('Progetto non trovato');
                    }

                    $initialScene = Scene::find($project->initial_scene_id);
                    if (!$initialScene) {
                        return $this->sendErrorResponse('Scena iniziale non trovata');
                    }

                    UserProgress::create([
                        'phone_number' => $phoneNumber,
                        'project_id' => $projectId,
                        'current_scene_id' => $initialScene->id,
                        'attempts_remaining' => 3,
                        'last_interaction_at' => now()
                    ]);

                    try {
                        $response = new MessagingResponse();

                        // 1. Messaggio testuale
                        $textMessage = $response->message(strip_tags($initialScene->entry_message));
                        $textMessage->setAttribute('format', 'html');

                        // 2. GIF (se presente)
                        if ($initialScene->media_gif_url) {
                            $gifUrl = $this->prepareMediaUrl($initialScene->media_gif_url);
                            $gifMessage = $response->message('');
                            $gifMessage->media($gifUrl, ['contentType' => 'video/mp4']);
                            Log::info('GIF aggiunta', ['url' => $gifUrl]);
                        }

                        // 3. Audio (se presente)
                        if ($initialScene->media_audio_url) {
                            $audioUrl = $this->prepareMediaUrl($initialScene->media_audio_url);
                            $audioMessage = $response->message('');
                            $audioMessage->media($audioUrl, ['contentType' => 'audio/mpeg']);
                            Log::info('Audio aggiunto', ['url' => $audioUrl]);
                        }

                        Log::info('Risposta TwiML generata', ['response' => $response->asXML()]);

                        return response($response)
                            ->header('Content-Type', 'text/xml')
                            ->header('X-Twilio-Webhook-Response', 'true');

                    } catch (\Exception $e) {
                        Log::error('Errore generazione TwiML', ['error' => $e->getMessage()]);
                        throw $e;
                    }
                }

                return $this->handleIncomingMessage($request);
            }

            // Gestione richiesta GET
            $response = new MessagingResponse();
            $message = $response->message('Invia questo messaggio per iniziare il gioco!');
            $message->setAttribute('format', 'html');

            return response($response)
                ->header('Content-Type', 'text/xml')
                ->header('X-Twilio-Webhook-Response', 'true');

        } catch (\Exception $e) {
            Log::error('Errore in sendInitialMessage', ['error' => $e->getMessage()]);
            return $this->sendErrorResponse('Si è verificato un errore. Riprova più tardi.');
        }
    }

    /**
     * Gestisce i messaggi in arrivo da WhatsApp
     */
    public function handleIncomingMessage(Request $request)
    {
        try {
            Log::info('Messaggio in arrivo', [
                'from' => $request->input('From'),
                'body' => $request->input('Body')
            ]);

            $phoneNumber = $request->input('From');
            $projectId = $request->input('project_id', 1);

            if (!$phoneNumber) {
                Log::error('Numero di telefono mancante');
                return $this->sendErrorResponse('Numero di telefono mancante');
            }

            $userProgress = UserProgress::where('phone_number', $phoneNumber)
                ->where('project_id', $projectId)
                ->first();

            if (!$userProgress) {
                Log::error('Progresso utente non trovato');
                return $this->sendErrorResponse('Progresso utente non trovato');
            }

            $currentScene = Scene::find($userProgress->current_scene_id);
            if (!$currentScene) {
                Log::error('Scena corrente non trovata');
                return $this->sendErrorResponse('Scena corrente non trovata');
            }

            $response = new MessagingResponse();

            switch ($currentScene->type) {
                case 'intro':
                    $this->handleIntroScene($userProgress, $currentScene, $request->input('Body'), $response);
                    break;
                case 'investigation':
                    $this->handleInvestigationScene($userProgress, $currentScene, $request->input('Body'), $response);
                    break;
                case 'puzzle':
                    $this->handlePuzzleScene($userProgress, $currentScene, $request->input('Body'), $response);
                    break;
                case 'final':
                    $this->handleFinalScene($userProgress, $currentScene, $request->input('Body'), $response);
                    break;
                default:
                    Log::error('Tipo di scena non valido');
                    return $this->sendErrorResponse('Tipo di scena non valido');
            }

            return response($response)
                ->header('Content-Type', 'text/xml')
                ->header('X-Twilio-Webhook-Response', 'true');

        } catch (\Exception $e) {
            Log::error('Errore in handleIncomingMessage', ['error' => $e->getMessage()]);
            return $this->sendErrorResponse('Si è verificato un errore. Riprova più tardi.');
        }
    }

    /**
     * Gestisce una scena di tipo intro
     */
    private function handleIntroScene($userProgress, $scene, $message, $response)
    {
        if ($message === '1' && $scene->next_scene_id) {
            // L'utente ha digitato 1, procediamo con la scena successiva
            $nextScene = Scene::find($scene->next_scene_id);
            if ($nextScene) {
                Log::info('Passaggio alla scena successiva', [
                    'next_scene_id' => $nextScene->id,
                    'next_scene_type' => $nextScene->type
                ]);

                $userProgress->update(['current_scene_id' => $scene->next_scene_id]);
                
                // 1. Messaggio testuale
                $textMessage = $response->message(strip_tags($nextScene->entry_message));
                $textMessage->setAttribute('format', 'html');

                // 2. GIF (se presente)
                if ($nextScene->media_gif_url) {
                    $gifUrl = $this->prepareMediaUrl($nextScene->media_gif_url);
                    $gifMessage = $response->message('');
                    $gifMessage->media($gifUrl, ['contentType' => 'video/mp4']);
                    Log::info('GIF aggiunta', ['url' => $gifUrl]);
                }

                // 3. Audio (se presente)
                if ($nextScene->media_audio_url) {
                    $audioUrl = $this->prepareMediaUrl($nextScene->media_audio_url);
                    $audioMessage = $response->message('');
                    $audioMessage->media($audioUrl, ['contentType' => 'audio/mpeg']);
                    Log::info('Audio aggiunto', ['url' => $audioUrl]);
                }

                // 4. Se la prossima scena è di tipo investigation, mostra le opzioni
                if ($nextScene->type === 'investigation') {
                    Log::info('Scena di tipo investigation, recupero le scelte');
                    
                    // Carica esplicitamente le scelte
                    $choices = $nextScene->choices()->get();
                    Log::info('Scelte trovate', ['count' => $choices->count(), 'choices' => $choices->toArray()]);

                    if ($choices->count() > 0) {
                        $optionsMessage = "\n\nOpzioni disponibili:\n";
                        foreach ($choices as $index => $choice) {
                            $optionsMessage .= ($index + 1) . ". " . $choice->label . "\n";
                        }
                        Log::info('Messaggio opzioni preparato', ['message' => $optionsMessage]);
                        
                        $optionsResponse = $response->message($optionsMessage);
                        $optionsResponse->setAttribute('format', 'html');
                    } else {
                        Log::warning('Nessuna scelta trovata per la scena', ['scene_id' => $nextScene->id]);
                    }
                }
            }
        } else {
            // 1. Messaggio testuale
            $textMessage = $response->message(strip_tags($scene->entry_message));
            $textMessage->setAttribute('format', 'html');

            // 2. GIF (se presente)
            if ($scene->media_gif_url) {
                $gifUrl = $this->prepareMediaUrl($scene->media_gif_url);
                $gifMessage = $response->message('');
                $gifMessage->media($gifUrl, ['contentType' => 'video/mp4']);
                Log::info('GIF aggiunta', ['url' => $gifUrl]);
            }

            // 3. Audio (se presente)
            if ($scene->media_audio_url) {
                $audioUrl = $this->prepareMediaUrl($scene->media_audio_url);
                $audioMessage = $response->message('');
                $audioMessage->media($audioUrl, ['contentType' => 'audio/mpeg']);
                Log::info('Audio aggiunto', ['url' => $audioUrl]);
            }

            // 4. Messaggio per proseguire
            $continueMessage = $response->message(' Digita 1 per proseguire');
            $continueMessage->setAttribute('format', 'html');
        }
    }

    /**
     * Gestisce una scena di tipo investigation
     */
    private function handleInvestigationScene($userProgress, $scene, $message, $response)
    {
        Log::info('Gestione scena investigation', [
            'scene_id' => $scene->id,
            'message' => $message
        ]);

        // Verifica se il messaggio è un numero valido
        if (is_numeric($message)) {
            $choices = $scene->choices;
            $index = (int)$message - 1;
            
            Log::info('Scelta selezionata', [
                'index' => $index,
                'total_choices' => count($choices)
            ]);
            
            if (isset($choices[$index])) {
                $choice = $choices[$index];
                Log::info('Scelta trovata', [
                    'choice_id' => $choice->id,
                    'target_scene_id' => $choice->target_scene_id
                ]);

                $userProgress->update(['current_scene_id' => $choice->target_scene_id]);
                $nextScene = Scene::find($choice->target_scene_id);
                
                if ($nextScene) {
                    Log::info('Prossima scena trovata', [
                        'next_scene_id' => $nextScene->id,
                        'next_scene_type' => $nextScene->type
                    ]);

                    // 1. Messaggio testuale
                    $textMessage = $response->message(strip_tags($nextScene->entry_message));
                    $textMessage->setAttribute('format', 'html');

                    // 2. GIF (se presente)
                    if ($nextScene->media_gif_url) {
                        try {
                            $gifUrl = $this->prepareMediaUrl($nextScene->media_gif_url);
                            $gifMessage = $response->message('');
                            $gifMessage->media($gifUrl, ['contentType' => 'video/mp4']);
                            Log::info('GIF aggiunta', ['url' => $gifUrl]);
                        } catch (\Exception $e) {
                            Log::error('Errore nel caricamento GIF', [
                                'error' => $e->getMessage(),
                                'url' => $nextScene->media_gif_url
                            ]);
                        }
                    }

                    // 3. Audio (se presente)
                    if ($nextScene->media_audio_url) {
                        $audioUrl = $this->prepareMediaUrl($nextScene->media_audio_url);
                        $audioMessage = $response->message('');
                        $audioMessage->media($audioUrl, ['contentType' => 'audio/mpeg']);
                        Log::info('Audio aggiunto', ['url' => $audioUrl]);
                    }

                    // 4. Se la prossima scena è di tipo puzzle, mostra l'enigma
                    if ($nextScene->type === 'puzzle') {
                        Log::info('Mostro enigma', [
                            'question' => $nextScene->puzzle_question
                        ]);
                        
                        if ($nextScene->puzzle_question) {
                            $puzzleMessage = $response->message(strip_tags($nextScene->puzzle_question));
                            $puzzleMessage->setAttribute('format', 'html');
                        } else {
                            Log::warning('Mancano i dati dell\'enigma', [
                                'scene_id' => $nextScene->id
                            ]);
                            $errorMessage = $response->message('Ops! Sembra che ci sia un problema con l\'enigma. Riprova più tardi.');
                            $errorMessage->setAttribute('format', 'html');
                        }
                    }
                    
                    // Non mostrare le opzioni se siamo passati a una nuova scena
                    return response($response)
                        ->header('Content-Type', 'text/xml')
                        ->header('X-Twilio-Webhook-Response', 'true');
                }
            } else {
                // Scelta non valida
                Log::warning('Scelta non valida', [
                    'index' => $index,
                    'total_choices' => count($choices)
                ]);
                $errorMessage = $response->message('Scelta non valida. Seleziona un numero tra 1 e ' . count($choices));
                $errorMessage->setAttribute('format', 'html');
            }
        }
        
        // Mostra le opzioni disponibili solo se non siamo passati a una nuova scena
        $messageText = strip_tags($scene->entry_message);
        if ($scene->choices->count() > 0) {
            $messageText .= "\n\nOpzioni disponibili:\n";
            foreach ($scene->choices as $index => $choice) {
                $messageText .= ($index + 1) . ". " . $choice->label . "\n";
            }
        }
        
        $textMessage = $response->message($messageText);
        $textMessage->setAttribute('format', 'html');
        
        return response($response)
            ->header('Content-Type', 'text/xml')
            ->header('X-Twilio-Webhook-Response', 'true');
    }

    /**
     * Gestisce una scena di tipo puzzle
     */
    private function handlePuzzleScene($userProgress, $scene, $message, $response)
    {
        if (strtolower($message) === strtolower($scene->correct_answer)) {
            if ($scene->item_id) {
                $userProgress->collected_items()->attach($scene->item_id, ['collected_at' => now()]);
            }

            // 1. Messaggio di successo
            $textMessage = $response->message(strip_tags($scene->success_message));
            $textMessage->setAttribute('format', 'html');

            if ($scene->next_scene_id) {
                $userProgress->update(['current_scene_id' => $scene->next_scene_id]);
                $nextScene = Scene::find($scene->next_scene_id);
                
                if ($nextScene) {
                    // 2. Messaggio della prossima scena
                    $nextMessage = $response->message(strip_tags($nextScene->entry_message));
                    $nextMessage->setAttribute('format', 'html');

                    // 3. GIF (se presente)
                    if ($nextScene->media_gif_url) {
                        $gifUrl = $this->prepareMediaUrl($nextScene->media_gif_url);
                        $gifMessage = $response->message('');
                        $gifMessage->media($gifUrl, ['contentType' => 'video/mp4']);
                        Log::info('GIF aggiunta', ['url' => $gifUrl]);
                    }

                    // 4. Audio (se presente)
                    if ($nextScene->media_audio_url) {
                        $audioUrl = $this->prepareMediaUrl($nextScene->media_audio_url);
                        $audioMessage = $response->message('');
                        $audioMessage->media($audioUrl, ['contentType' => 'audio/mpeg']);
                        Log::info('Audio aggiunto', ['url' => $audioUrl]);
                    }

                    // 5. Messaggio per proseguire solo se la prossima scena è di tipo intro
                    if ($nextScene->type === 'intro') {
                        $continueMessage = $response->message(' Digita 1 per proseguire');
                        $continueMessage->setAttribute('format', 'html');
                    }
                }
            }
        } else {
            $userProgress->decrement('attempts_remaining');

            $textMessage = $response->message(
                $userProgress->attempts_remaining <= 0 
                    ? strip_tags($scene->failure_message)
                    : '<p>Risposta errata. <b>Tentativi rimanenti: ' . $userProgress->attempts_remaining . '</b></p>'
            );
            $textMessage->setAttribute('format', 'html');

            // Se ci sono ancora tentativi, mostra nuovamente l'enigma
            if ($userProgress->attempts_remaining > 0) {
                $puzzleMessage = $response->message(strip_tags($scene->entry_message));
                $puzzleMessage->setAttribute('format', 'html');
            }
        }
    }

    /**
     * Gestisce una scena di tipo final
     */
    private function handleFinalScene($userProgress, $scene, $message, $response)
    {
        // 1. Messaggio testuale
        $textMessage = $response->message(strip_tags($scene->entry_message));
        $textMessage->setAttribute('format', 'html');

        // 2. GIF (se presente)
        if ($scene->media_gif_url) {
            $gifUrl = $this->prepareMediaUrl($scene->media_gif_url);
            $gifMessage = $response->message('');
            $gifMessage->media($gifUrl, ['contentType' => 'video/mp4']);
            Log::info('GIF aggiunta', ['url' => $gifUrl]);
        }

        // 3. Audio (se presente)
        if ($scene->media_audio_url) {
            $audioUrl = $this->prepareMediaUrl($scene->media_audio_url);
            $audioMessage = $response->message('');
            $audioMessage->media($audioUrl, ['contentType' => 'audio/mpeg']);
            Log::info('Audio aggiunto', ['url' => $audioUrl]);
        }
    }

    /**
     * Invia una risposta di errore
     */
    private function sendErrorResponse($message)
    {
        $response = new MessagingResponse();
        $message = $response->message('<p>' . $message . '</p>');
        $message->setAttribute('format', 'html');
        
        return response($response)
            ->header('Content-Type', 'text/xml')
            ->header('X-Twilio-Webhook-Response', 'true');
    }
}
