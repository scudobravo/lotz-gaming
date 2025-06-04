<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\Project;
use Illuminate\Http\Request;

class LetterController extends Controller
{
    /**
     * Display a listing of the letters for a specific project.
     */
    public function index(Project $project)
    {
        $letters = $project->letters()->paginate(10);
        return view('letters.index', compact('project', 'letters'));
    }

    /**
     * Show the form for creating a new letter.
     */
    public function create(Project $project)
    {
        return view('letters.create', compact('project'));
    }

    /**
     * Store a newly created letter in storage.
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'unlock_conditions' => 'nullable|json',
            'metadata' => 'nullable|json'
        ]);

        $letter = $project->letters()->create($validated);

        return redirect()->route('projects.letters.index', $project)
            ->with('success', 'Lettera creata con successo.');
    }

    /**
     * Display the specified letter.
     */
    public function show(Project $project, Letter $letter)
    {
        return view('letters.show', compact('project', 'letter'));
    }

    /**
     * Show the form for editing the specified letter.
     */
    public function edit(Project $project, Letter $letter)
    {
        return view('letters.edit', compact('project', 'letter'));
    }

    /**
     * Update the specified letter in storage.
     */
    public function update(Request $request, Project $project, Letter $letter)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'unlock_conditions' => 'nullable|json',
            'metadata' => 'nullable|json'
        ]);

        $letter->update($validated);

        return redirect()->route('projects.letters.index', $project)
            ->with('success', 'Lettera aggiornata con successo.');
    }

    /**
     * Remove the specified letter from storage.
     */
    public function destroy(Project $project, Letter $letter)
    {
        $letter->delete();

        return redirect()->route('projects.letters.index', $project)
            ->with('success', 'Lettera eliminata con successo.');
    }
} 