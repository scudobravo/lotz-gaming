<?php

namespace App\Http\Controllers;

use App\Models\Scene;
use App\Models\Project;
use App\Models\Character;
use App\Models\Item;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SceneController extends Controller
{
    /**
     * Display a listing of the scenes.
     */
    public function index()
    {
        $scenes = Scene::with('project')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Scenes/Index', [
            'scenes' => $scenes
        ]);
    }

    /**
     * Display a listing of the scenes for a specific project.
     */
    public function indexProject(Project $project)
    {
        $scenes = $project->scenes()->orderBy('order')->paginate(10);
        return view('scenes.index', compact('project', 'scenes'));
    }

    /**
     * Show the form for creating a new scene.
     */
    public function create()
    {
        $projects = Project::all();
        $characters = Character::all();
        
        return Inertia::render('Scenes/Create', [
            'projects' => $projects,
            'characters' => $characters
        ]);
    }

    /**
     * Store a newly created scene in storage.
     */
    public function store(Request $request)
    {
        Log::info('Richiesta di creazione scena ricevuta', [
            'all' => $request->all(),
            'has_file_gif' => $request->hasFile('media_gif'),
            'has_file_audio' => $request->hasFile('media_audio'),
            'files' => $request->allFiles(),
            'gif_present' => $request->has('media_gif'),
            'gif_type' => $request->input('media_gif') ? gettype($request->input('media_gif')) : 'null',
            'request_files' => $request->files->all(),
            'content_type' => $request->header('Content-Type')
        ]);

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'entry_message' => 'required|string',
                'type' => 'required|in:intro,investigation,puzzle,final',
                'metadata' => 'nullable|json',
                'project_id' => 'required|exists:projects,id',
                'media_gif' => 'nullable|file|mimes:mp4,jpg,jpeg,png|max:16384',
                'media_audio' => 'nullable|file|mimes:mp3,wav|max:16384',
                'next_scene_id' => 'nullable|exists:scenes,id'
            ]);

            DB::beginTransaction();

            // Gestione del file media (GIF/Video)
            if ($request->hasFile('media_gif')) {
                $file = $request->file('media_gif');
                
                if (!$file->isValid()) {
                    throw new \Exception('File non valido: ' . $file->getErrorMessage());
                }

                Log::info('File media ricevuto', [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'tmp_path' => $file->getPathname(),
                    'isValid' => $file->isValid(),
                    'error' => $file->getError(),
                    'error_message' => $file->getErrorMessage()
                ]);

                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('scenes/media', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file');
                }
                
                $validated['media_gif'] = $path;
                $validated['media_gif_url'] = '/storage/' . $path;

                Log::info('Media salvato con successo', [
                    'path' => $path,
                    'url' => '/storage/' . $path,
                    'full_path' => Storage::disk('public')->path($path)
                ]);
            }

            // Gestione del file audio
            if ($request->hasFile('media_audio')) {
                $file = $request->file('media_audio');
                
                if (!$file->isValid()) {
                    throw new \Exception('File audio non valido: ' . $file->getErrorMessage());
                }

                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('scenes/audio', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file audio');
                }
                
                $validated['media_audio'] = $path;
                $validated['media_audio_url'] = '/storage/' . $path;
            }

            $scene = Scene::create($validated);

            DB::commit();
            return response()->json(['success' => true]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Errore di validazione durante la creazione', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'message' => 'Errore di validazione',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore durante la creazione', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Errore durante la creazione',
                'errors' => ['media_gif' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * Display the specified scene.
     */
    public function show(Project $project, Scene $scene)
    {
        return view('scenes.show', compact('project', 'scene'));
    }

    /**
     * Show the form for editing the specified scene.
     */
    public function edit(Scene $scene)
    {
        $projects = Project::select(['id', 'name'])->get();
        $characters = Character::select(['id', 'name'])->get();
        $items = Item::select(['id', 'name'])->get();
        $availableScenes = Scene::where('project_id', $scene->project_id)
            ->where('id', '!=', $scene->id)
            ->select(['id', 'title', 'type'])
            ->get();
        
        // Carica le scelte separatamente
        $choices = $scene->choices()
            ->select(['id', 'label', 'target_scene_id', 'order'])
            ->get()
            ->map(function($choice) {
                return [
                    'id' => $choice->id,
                    'label' => $choice->label,
                    'target_scene_id' => $choice->target_scene_id,
                    'order' => $choice->order
                ];
            });
        
        return Inertia::render('Scenes/Edit', [
            'scene' => $scene,
            'choices' => $choices,
            'projects' => $projects,
            'characters' => $characters,
            'availableScenes' => $availableScenes,
            'items' => $items
        ]);
    }

    /**
     * Update the specified scene in storage.
     */
    public function update(Request $request, Scene $scene)
    {
        try {
            Log::info('Inizio aggiornamento scena', [
                'scene_id' => $scene->id,
                'request_data' => $request->all(),
                'choices_data' => $request->input('choices')
            ]);

            DB::beginTransaction();

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'type' => 'required|in:intro,investigation,puzzle,final',
                'entry_message' => 'required|string',
                'media_gif' => 'nullable|file|mimes:mp4,jpg,jpeg,png|max:16384',
                'media_audio' => 'nullable|file|mimes:mp3,wav|max:16384',
                'puzzle_question' => 'nullable|string',
                'correct_answer' => 'nullable|string',
                'success_message' => 'nullable|string',
                'failure_message' => 'nullable|string',
                'max_attempts' => 'nullable|integer|min:1',
                'item_id' => 'nullable|exists:items,id',
                'character_id' => 'nullable|exists:characters,id',
                'project_id' => 'required|exists:projects,id',
                'next_scene_id' => 'nullable|exists:scenes,id',
                'choices' => 'nullable|string'
            ]);

            Log::info('Dati validati', [
                'validated_data' => $validated
            ]);

            // Gestione del file media (GIF/Video)
            if ($request->hasFile('media_gif')) {
                $file = $request->file('media_gif');
                
                if (!$file->isValid()) {
                    throw new \Exception('File non valido: ' . $file->getErrorMessage());
                }

                // Elimina il vecchio file se esiste
                if ($scene->media_gif) {
                    Storage::disk('public')->delete($scene->media_gif);
                }

                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('scenes/media', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file');
                }
                
                $validated['media_gif'] = $path;
                $validated['media_gif_url'] = '/storage/' . $path;
            }

            // Gestione del file audio
            if ($request->hasFile('media_audio')) {
                $file = $request->file('media_audio');
                
                if (!$file->isValid()) {
                    throw new \Exception('File audio non valido: ' . $file->getErrorMessage());
                }

                // Elimina il vecchio file se esiste
                if ($scene->media_audio) {
                    Storage::disk('public')->delete($scene->media_audio);
                }

                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('scenes/audio', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file audio');
                }
                
                $validated['media_audio'] = $path;
                $validated['media_audio_url'] = '/storage/' . $path;
            }

            // Aggiorna la scena
            $scene->update($validated);

            // Gestione delle scelte
            if ($request->has('choices')) {
                $choices = json_decode($request->input('choices'), true);
                Log::info('Scelte decodificate', ['choices' => $choices]);

                if (json_last_error() === JSON_ERROR_NONE && is_array($choices)) {
                    // Elimina le scelte esistenti
                    $scene->choices()->delete();

                    // Se il tipo è investigation, crea le nuove scelte
                    if ($scene->type === 'investigation' && !empty($choices)) {
                        foreach ($choices as $choiceData) {
                            $scene->choices()->create([
                                'label' => $choiceData['label'],
                                'target_scene_id' => $choiceData['target_scene_id'],
                                'order' => $choiceData['order']
                            ]);
                        }
                    }
                } else {
                    Log::error('Errore decodifica JSON scelte', [
                        'json_error' => json_last_error_msg(),
                        'raw_choices' => $request->input('choices')
                    ]);
                }
            }

            DB::commit();

            Log::info('Scena aggiornata con successo', [
                'scene_id' => $scene->id,
                'updated_data' => $validated,
                'choices' => $request->input('choices')
            ]);

            return response()->json(['success' => true]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Errore di validazione durante l\'aggiornamento', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'message' => 'Errore di validazione',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore durante l\'aggiornamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Errore durante l\'aggiornamento',
                'errors' => ['media_gif' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * Remove the specified scene from storage.
     */
    public function destroy(Scene $scene)
    {
        // Elimina i file multimediali se esistono
        if ($scene->media_gif && Storage::disk('public')->exists($scene->media_gif)) {
            Storage::disk('public')->delete($scene->media_gif);
        }
        if ($scene->media_audio && Storage::disk('public')->exists($scene->media_audio)) {
            Storage::disk('public')->delete($scene->media_audio);
        }

        $scene->delete();

        return redirect()->route('scenes.index')
            ->with('success', 'Scena eliminata con successo.');
    }

    /**
     * Update the order of scenes.
     */
    public function updateOrder(Request $request, Project $project)
    {
        $validated = $request->validate([
            'scenes' => 'required|array',
            'scenes.*.id' => 'required|exists:scenes,id',
            'scenes.*.order' => 'required|integer|min:0'
        ]);

        foreach ($validated['scenes'] as $sceneData) {
            Scene::where('id', $sceneData['id'])
                ->where('project_id', $project->id)
                ->update(['order' => $sceneData['order']]);
        }

        return response()->json(['message' => 'Ordine aggiornato con successo']);
    }
} 