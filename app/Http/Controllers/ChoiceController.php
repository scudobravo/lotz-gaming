<?php

namespace App\Http\Controllers;

use App\Models\Choice;
use App\Models\Scene;
use App\Models\Project;
use Illuminate\Http\Request;

class ChoiceController extends Controller
{
    /**
     * Display a listing of the choices for a specific scene.
     */
    public function index(Project $project, Scene $scene)
    {
        $choices = $scene->choices()->paginate(10);
        return view('choices.index', compact('project', 'scene', 'choices'));
    }

    /**
     * Show the form for creating a new choice.
     */
    public function create(Project $project, Scene $scene)
    {
        $nextScenes = $project->scenes()->where('id', '!=', $scene->id)->get();
        return view('choices.create', compact('project', 'scene', 'nextScenes'));
    }

    /**
     * Store a newly created choice in storage.
     */
    public function store(Request $request, Project $project, Scene $scene)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:255',
            'next_scene_id' => 'nullable|exists:scenes,id',
            'is_correct' => 'boolean',
            'metadata' => 'nullable|json'
        ]);

        $choice = $scene->choices()->create($validated);

        return redirect()->route('projects.scenes.choices.index', [$project, $scene])
            ->with('success', 'Scelta creata con successo.');
    }

    /**
     * Display the specified choice.
     */
    public function show(Project $project, Scene $scene, Choice $choice)
    {
        return view('choices.show', compact('project', 'scene', 'choice'));
    }

    /**
     * Show the form for editing the specified choice.
     */
    public function edit(Project $project, Scene $scene, Choice $choice)
    {
        $nextScenes = $project->scenes()->where('id', '!=', $scene->id)->get();
        return view('choices.edit', compact('project', 'scene', 'choice', 'nextScenes'));
    }

    /**
     * Update the specified choice in storage.
     */
    public function update(Request $request, Project $project, Scene $scene, Choice $choice)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:255',
            'next_scene_id' => 'nullable|exists:scenes,id',
            'is_correct' => 'boolean',
            'metadata' => 'nullable|json'
        ]);

        $choice->update($validated);

        return redirect()->route('projects.scenes.choices.index', [$project, $scene])
            ->with('success', 'Scelta aggiornata con successo.');
    }

    /**
     * Remove the specified choice from storage.
     */
    public function destroy(Project $project, Scene $scene, Choice $choice)
    {
        $choice->delete();

        return redirect()->route('projects.scenes.choices.index', [$project, $scene])
            ->with('success', 'Scelta eliminata con successo.');
    }
} 