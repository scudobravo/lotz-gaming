<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CharacterController extends Controller
{
    public function index()
    {
        return Inertia::render('Characters/Index', [
            'characters' => Character::with('projects')->get()
        ]);
    }

    public function create()
    {
        return Inertia::render('Characters/Create', [
            'projects' => Project::all()
        ]);
    }

    public function store(Request $request)
    {
        Log::info('Richiesta di creazione ricevuta', [
            'all' => $request->all(),
            'has_file_gif' => $request->hasFile('gif'),
            'has_file_audio' => $request->hasFile('audio'),
            'files' => $request->allFiles(),
            'gif_present' => $request->has('gif'),
            'gif_type' => $request->input('gif') ? gettype($request->input('gif')) : 'null',
            'request_files' => $request->files->all(),
            'content_type' => $request->header('Content-Type')
        ]);

        try {
            // Validazione base
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:characters,name',
                'description' => 'required|string',
                'project_ids' => 'nullable|array',
                'project_ids.*' => 'exists:projects,id'
            ]);

            // Crea il personaggio
            $character = Character::create([
                'name' => $validated['name'],
                'description' => $validated['description']
            ]);

            // Gestione del file media
            if ($request->hasFile('gif')) {
                $file = $request->file('gif');
                
                if (!$file->isValid()) {
                    throw new \Exception('File non valido: ' . $file->getErrorMessage());
                }

                // Validazione specifica per il file
                $request->validate([
                    'gif' => 'required|file|mimes:mp4,jpg,jpeg,png|max:16384'
                ]);

                Log::info('File media ricevuto', [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'tmp_path' => $file->getPathname(),
                    'isValid' => $file->isValid(),
                    'error' => $file->getError(),
                    'error_message' => $file->getErrorMessage()
                ]);

                // Genera un nome unico per il file
                $fileName = time() . '_' . $file->getClientOriginalName();
                
                // Salva il file usando storeAs
                $path = $file->storeAs('characters/media', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file');
                }
                
                // Aggiorna il modello
                $character->update([
                    'gif_path' => $path,
                    'gif_url' => '/storage/' . $path
                ]);

                Log::info('Media salvato con successo', [
                    'path' => $path,
                    'url' => '/storage/' . $path,
                    'full_path' => Storage::disk('public')->path($path)
                ]);
            }

            // Gestione del file audio
            if ($request->hasFile('audio')) {
                $file = $request->file('audio');
                
                if (!$file->isValid()) {
                    throw new \Exception('File audio non valido: ' . $file->getErrorMessage());
                }

                // Validazione specifica per il file audio
                $request->validate([
                    'audio' => 'required|file|mimes:mp3,wav|max:16384'
                ]);

                // Genera un nome unico per il file
                $fileName = time() . '_' . $file->getClientOriginalName();
                
                // Salva il file usando storeAs
                $path = $file->storeAs('characters/audio', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file audio');
                }
                
                // Aggiorna il modello
                $character->update([
                    'audio_path' => $path,
                    'audio_url' => '/storage/' . $path
                ]);
            }

            // Gestione dei progetti
            if ($request->has('project_ids')) {
                $character->projects()->attach($request->input('project_ids'));
            }

            return response()->json(['success' => true]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Errore di validazione durante la creazione', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'message' => 'Errore di validazione',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Errore durante la creazione', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Errore durante la creazione',
                'errors' => ['gif' => [$e->getMessage()]]
            ], 422);
        }
    }

    public function edit(Character $character)
    {
        return Inertia::render('Characters/Edit', [
            'character' => $character->load('projects'),
            'projects' => Project::all()
        ]);
    }

    public function update(Request $request, Character $character)
    {
        Log::info('Richiesta di update ricevuta', [
            'all' => $request->all(),
            'has_file_gif' => $request->hasFile('gif'),
            'has_file_audio' => $request->hasFile('audio'),
            'files' => $request->allFiles(),
            'gif_present' => $request->has('gif'),
            'gif_type' => $request->input('gif') ? gettype($request->input('gif')) : 'null',
            'request_files' => $request->files->all(),
            'content_type' => $request->header('Content-Type')
        ]);

        try {
            // Validazione base
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:characters,name,' . $character->id,
                'description' => 'required|string',
                'project_ids' => 'nullable|array',
                'project_ids.*' => 'exists:projects,id'
            ]);

            // Aggiorna i campi base
            $character->update([
                'name' => $validated['name'],
                'description' => $validated['description']
            ]);

            // Gestione del file media
            if ($request->hasFile('gif')) {
                $file = $request->file('gif');
                
                if (!$file->isValid()) {
                    throw new \Exception('File non valido: ' . $file->getErrorMessage());
                }

                // Validazione specifica per il file
                $request->validate([
                    'gif' => 'required|file|mimes:mp4,jpg,jpeg,png|max:16384'
                ]);

                Log::info('File media ricevuto', [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'tmp_path' => $file->getPathname(),
                    'isValid' => $file->isValid(),
                    'error' => $file->getError(),
                    'error_message' => $file->getErrorMessage()
                ]);

                // Elimina il vecchio media se esiste
                if ($character->gif_path && Storage::disk('public')->exists($character->gif_path)) {
                    Storage::disk('public')->delete($character->gif_path);
                }

                // Genera un nome unico per il file
                $fileName = time() . '_' . $file->getClientOriginalName();
                
                // Salva il file usando storeAs
                $path = $file->storeAs('characters/media', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file');
                }
                
                // Aggiorna il modello
                $character->update([
                    'gif_path' => $path,
                    'gif_url' => '/storage/' . $path
                ]);

                Log::info('Media salvato con successo', [
                    'path' => $path,
                    'url' => '/storage/' . $path,
                    'full_path' => Storage::disk('public')->path($path)
                ]);
            } else {
                Log::info('Nessun file media presente nella richiesta');
            }

            // Gestione dei progetti
            if ($request->has('project_ids')) {
                $character->projects()->sync($request->input('project_ids'));
            }

            return response()->json(['success' => true]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Errore di validazione durante l\'aggiornamento', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'message' => 'Errore di validazione',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Errore durante l\'aggiornamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Errore durante l\'aggiornamento',
                'errors' => ['gif' => [$e->getMessage()]]
            ], 422);
        }
    }

    public function destroy(Character $character)
    {
        $character->delete();
        return redirect()->route('characters.index')
            ->with('success', 'Personaggio eliminato con successo.');
    }
} 