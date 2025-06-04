<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ItemController extends Controller
{
    /**
     * Display a listing of the items.
     */
    public function index()
    {
        $items = Item::latest()->paginate(10);

        return Inertia::render('Items/Index', [
            'items' => $items
        ]);
    }

    /**
     * Show the form for creating a new item.
     */
    public function create()
    {
        return Inertia::render('Items/Create');
    }

    /**
     * Store a newly created item in storage.
     */
    public function store(Request $request)
    {
        Log::info('Richiesta di creazione elemento ricevuta', [
            'all' => $request->all(),
            'has_file_image' => $request->hasFile('image'),
            'files' => $request->allFiles(),
            'image_present' => $request->has('image'),
            'image_type' => $request->input('image') ? gettype($request->input('image')) : 'null',
            'request_files' => $request->files->all(),
            'content_type' => $request->header('Content-Type')
        ]);

        try {
            $validated = $request->validate([
                'identifier' => 'required|string|max:10|unique:items',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:gif,jpg,jpeg,png|max:2048'
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                
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
                $path = $file->storeAs('items', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file');
                }
                
                $validated['image'] = $path;

                Log::info('Immagine salvata con successo', [
                    'path' => $path,
                    'url' => '/storage/' . $path,
                    'full_path' => Storage::disk('public')->path($path)
                ]);
            }

            Item::create($validated);

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
                'errors' => ['image' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * Show the form for editing the specified item.
     */
    public function edit(Item $item)
    {
        return Inertia::render('Items/Edit', [
            'item' => $item
        ]);
    }

    /**
     * Update the specified item in storage.
     */
    public function update(Request $request, Item $item)
    {
        Log::info('Richiesta di update elemento ricevuta', [
            'all' => $request->all(),
            'has_file_image' => $request->hasFile('image'),
            'files' => $request->allFiles(),
            'image_present' => $request->has('image'),
            'image_type' => $request->input('image') ? gettype($request->input('image')) : 'null',
            'request_files' => $request->files->all(),
            'content_type' => $request->header('Content-Type')
        ]);

        try {
            $validated = $request->validate([
                'identifier' => 'required|string|max:10|unique:items,identifier,' . $item->id,
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:gif,jpg,jpeg,png|max:2048'
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                
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
                if ($item->image && Storage::disk('public')->exists($item->image)) {
                    Storage::disk('public')->delete($item->image);
                }

                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('items', $fileName, 'public');
                
                if (!$path) {
                    throw new \Exception('Impossibile salvare il file');
                }
                
                $validated['image'] = $path;

                Log::info('Immagine salvata con successo', [
                    'path' => $path,
                    'url' => '/storage/' . $path,
                    'full_path' => Storage::disk('public')->path($path)
                ]);
            }

            $item->update($validated);

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
                'errors' => ['image' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * Remove the specified item from storage.
     */
    public function destroy(Item $item)
    {
        // Elimina l'immagine se esiste
        if ($item->image && Storage::disk('public')->exists($item->image)) {
            Storage::disk('public')->delete($item->image);
        }

        $item->delete();

        return redirect()->route('items.index')
            ->with('success', 'Elemento eliminato con successo.');
    }
} 