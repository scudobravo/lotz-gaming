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
                'media_gif' => 'nullable|image|mimes:gif,jpg,jpeg,png|max:2048',
                'media_audio' => 'nullable|file|mimes:mp3,wav|max:10240',
                'next_scene_id' => 'nullable|exists:scenes,id'
            ]);

            DB::beginTransaction();

            // Gestione del file GIF
            if ($request->hasFile('media_gif')) {
                $file = $request->file('media_gif');
                
                if (!$file->isValid()) {
                    throw new \Exception('File non valido: ' . $file->getErrorMessage());
                }

                Log::info('File GIF ricevuto', [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'tmp_path' => $file->getPathname(),
                    'isValid' => $file->isValid(),
                    'error' => $file->getError(),
                    'error_message' => $file->getErrorMessage()
                ]);

                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('scenes/gifs', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file');
                }
                
                $validated['media_gif'] = $path;
                $validated['media_gif_url'] = '/storage/' . $path;

                Log::info('GIF salvata con successo', [
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
        
        // Carica solo i dati essenziali della scena
        $sceneData = [
            'id' => $scene->id,
            'title' => $scene->title,
            'type' => $scene->type,
            'entry_message' => $scene->entry_message,
            'media_gif_url' => $scene->media_gif_url,
            'media_audio_url' => $scene->media_audio_url,
            'puzzle_question' => $scene->puzzle_question,
            'correct_answer' => $scene->correct_answer,
            'success_message' => $scene->success_message,
            'failure_message' => $scene->failure_message,
            'max_attempts' => $scene->max_attempts,
            'item_id' => $scene->item_id,
            'character_id' => $scene->character_id,
            'project_id' => $scene->project_id,
            'next_scene_id' => $scene->next_scene_id
        ];

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
            'scene' => $sceneData,
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
        Log::info('Richiesta di update scena ricevuta', [
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
                'type' => 'required|in:intro,investigation,puzzle,final',
                'entry_message' => 'required|string',
                'media_gif' => 'nullable|image|mimes:gif,jpg,jpeg,png|max:2048',
                'media_audio' => 'nullable|file|mimes:mp3,wav|max:10240',
                'puzzle_question' => 'required_if:type,puzzle|nullable|string',
                'correct_answer' => 'required_if:type,puzzle|nullable|string',
                'success_message' => 'required_if:type,puzzle|nullable|string',
                'failure_message' => 'required_if:type,puzzle|nullable|string',
                'max_attempts' => 'required_if:type,puzzle|nullable|integer|min:1',
                'item_id' => 'nullable|exists:items,id',
                'character_id' => 'nullable|exists:characters,id',
                'project_id' => 'required|exists:projects,id',
                'next_scene_id' => 'nullable|exists:scenes,id',
                'choices' => 'required_if:type,investigation|array',
                'choices.*.label' => 'required_if:type,investigation|string|max:255',
                'choices.*.target_scene_id' => 'required_if:type,investigation|exists:scenes,id',
                'choices.*.order' => 'required_if:type,investigation|integer|min:0'
            ]);

            DB::beginTransaction();

            // Gestione del file GIF
            if ($request->hasFile('media_gif')) {
                $file = $request->file('media_gif');
                
                if (!$file->isValid()) {
                    throw new \Exception('File non valido: ' . $file->getErrorMessage());
                }

                Log::info('File GIF ricevuto', [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'tmp_path' => $file->getPathname(),
                    'isValid' => $file->isValid(),
                    'error' => $file->getError(),
                    'error_message' => $file->getErrorMessage()
                ]);

                // Elimina la vecchia GIF se esiste
                if ($scene->media_gif && Storage::disk('public')->exists($scene->media_gif)) {
                    Storage::disk('public')->delete($scene->media_gif);
                }

                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('scenes/gifs', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file');
                }
                
                $validated['media_gif'] = $path;
                $validated['media_gif_url'] = '/storage/' . $path;

                Log::info('GIF salvata con successo', [
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

                // Elimina il vecchio audio se esiste
                if ($scene->media_audio && Storage::disk('public')->exists($scene->media_audio)) {
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

            // Gestione delle scelte per le scene di tipo investigation
            if ($scene->type === 'investigation') {
                // Elimina le scelte esistenti
                $scene->choices()->delete();
                
                // Crea le nuove scelte
                foreach ($request->choices as $choice) {
                    $scene->choices()->create([
                        'label' => $choice['label'],
                        'target_scene_id' => $choice['target_scene_id'],
                        'order' => $choice['order']
                    ]);
                }
            }

            DB::commit();
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