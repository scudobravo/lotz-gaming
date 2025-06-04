<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Scene;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProjectController extends Controller
{
    /**
     * Display a listing of the projects.
     */
    public function index()
    {
        $projects = Project::with(['creator', 'initialScene', 'requiredItems'])
            ->latest()
            ->paginate(10);

        return Inertia::render('Projects/Index', [
            'projects' => $projects
        ]);
    }

    /**
     * Show the form for creating a new project.
     */
    public function create()
    {
        $scenes = Scene::all();
        $items = Item::orderBy('identifier')->get();

        return Inertia::render('Projects/Create', [
            'scenes' => $scenes,
            'items' => $items
        ]);
    }

    /**
     * Store a newly created project in storage.
     */
    public function store(Request $request)
    {
        Log::info('Richiesta di creazione progetto ricevuta', [
            'all' => $request->all(),
            'has_file_cover' => $request->hasFile('cover_image'),
            'files' => $request->allFiles(),
            'cover_present' => $request->has('cover_image'),
            'cover_type' => $request->input('cover_image') ? gettype($request->input('cover_image')) : 'null',
            'request_files' => $request->files->all(),
            'content_type' => $request->header('Content-Type')
        ]);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'status' => 'required|in:draft,published',
                'initial_scene_id' => 'nullable|exists:scenes,id',
                'required_items' => 'required|array',
                'required_items.*' => 'nullable|exists:items,id'
            ]);

            $slug = Str::slug($validated['name']);
            
            if (Project::where('slug', $slug)->exists()) {
                return back()->withErrors([
                    'name' => 'Esiste già un progetto con questo nome. Per favore, scegli un nome diverso.'
                ])->withInput();
            }

            $validated['slug'] = $slug;
            $validated['created_by'] = Auth::id();

            if ($request->hasFile('cover_image')) {
                $file = $request->file('cover_image');
                
                if (!$file->isValid()) {
                    throw new \Exception('File non valido: ' . $file->getErrorMessage());
                }

                Log::info('File immagine ricevuto', [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'tmp_path' => $file->getPathname(),
                    'isValid' => $file->isValid(),
                    'error' => $file->getError(),
                    'error_message' => $file->getErrorMessage()
                ]);

                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('projects/covers', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file');
                }
                
                $validated['cover_image'] = $path;
                $validated['cover_url'] = '/storage/' . $path;

                Log::info('Immagine salvata con successo', [
                    'path' => $path,
                    'url' => '/storage/' . $path,
                    'full_path' => Storage::disk('public')->path($path)
                ]);
            }

            if ($validated['status'] === 'published') {
                $validated['qr_code'] = $this->generateQRCode($validated['slug']);
            }

            $project = Project::create($validated);

            // Filtra i valori nulli o vuoti da required_items prima di fare il sync
            $requiredItems = array_filter($request->required_items, function($item) {
                return $item !== null && $item !== '' && $item !== 'null';
            });

            if (!empty($requiredItems)) {
                $project->requiredItems()->sync($requiredItems);
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
                'errors' => ['cover_image' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project)
    {
        return view('projects.show', compact('project'));
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(Project $project)
    {
        $project->load(['initialScene', 'requiredItems']);
        $scenes = Scene::all();
        $items = Item::orderBy('identifier')->get();

        return Inertia::render('Projects/Edit', [
            'project' => $project,
            'scenes' => $scenes,
            'items' => $items
        ]);
    }

    /**
     * Update the specified project in storage.
     */
    public function update(Request $request, Project $project)
    {
        Log::info('Richiesta di update progetto ricevuta', [
            'all' => $request->all(),
            'has_file_cover' => $request->hasFile('cover_image'),
            'files' => $request->allFiles(),
            'cover_present' => $request->has('cover_image'),
            'cover_type' => $request->input('cover_image') ? gettype($request->input('cover_image')) : 'null',
            'request_files' => $request->files->all(),
            'content_type' => $request->header('Content-Type')
        ]);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'status' => 'required|in:draft,published',
                'initial_scene_id' => 'nullable|exists:scenes,id',
                'required_items' => 'required|array',
                'required_items.*' => 'exists:items,id'
            ]);

            $validated['slug'] = Str::slug($validated['name']);

            if ($request->hasFile('cover_image')) {
                $file = $request->file('cover_image');
                
                if (!$file->isValid()) {
                    throw new \Exception('File non valido: ' . $file->getErrorMessage());
                }

                Log::info('File immagine ricevuto', [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'tmp_path' => $file->getPathname(),
                    'isValid' => $file->isValid(),
                    'error' => $file->getError(),
                    'error_message' => $file->getErrorMessage()
                ]);

                // Elimina la vecchia immagine se esiste
                if ($project->cover_image && Storage::disk('public')->exists($project->cover_image)) {
                    Storage::disk('public')->delete($project->cover_image);
                }

                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('projects/covers', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file');
                }
                
                $validated['cover_image'] = $path;
                $validated['cover_url'] = '/storage/' . $path;

                Log::info('Immagine salvata con successo', [
                    'path' => $path,
                    'url' => '/storage/' . $path,
                    'full_path' => Storage::disk('public')->path($path)
                ]);
            }

            if ($validated['status'] === 'published' && !$project->qr_code) {
                $validated['qr_code'] = $this->generateQRCode($validated['slug']);
            }

            $project->update($validated);

            // Filtra i valori nulli o vuoti da required_items prima di fare il sync
            $requiredItems = array_filter($request->required_items, function($item) {
                return $item !== null && $item !== '' && $item !== 'null';
            });

            if (!empty($requiredItems)) {
                $project->requiredItems()->sync($requiredItems);
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
                'errors' => ['cover_image' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * Remove the specified project from storage.
     */
    public function destroy(Project $project)
    {
        if ($project->cover_image && Storage::disk('public')->exists($project->cover_image)) {
            Storage::disk('public')->delete($project->cover_image);
        }
        
        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Progetto eliminato con successo.');
    }

    /**
     * Generate QR code for the project
     */
    private function generateQRCode(string $slug): string
    {
        // Implementare la generazione del QR code
        return "qr-code-{$slug}.png";
    }
} 