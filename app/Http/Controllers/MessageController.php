<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Scene;
use App\Models\Project;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Display a listing of the messages for a specific scene.
     */
    public function index(Project $project, Scene $scene)
    {
        $messages = $scene->messages()->paginate(10);
        return view('messages.index', compact('project', 'scene', 'messages'));
    }

    /**
     * Show the form for creating a new message.
     */
    public function create(Project $project, Scene $scene)
    {
        return view('messages.create', compact('project', 'scene'));
    }

    /**
     * Store a newly created message in storage.
     */
    public function store(Request $request, Project $project, Scene $scene)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'type' => 'required|in:text,image,audio,video,gif',
            'metadata' => 'nullable|json'
        ]);

        $message = $scene->messages()->create($validated);

        return redirect()->route('projects.scenes.messages.index', [$project, $scene])
            ->with('success', 'Messaggio creato con successo.');
    }

    /**
     * Display the specified message.
     */
    public function show(Project $project, Scene $scene, Message $message)
    {
        return view('messages.show', compact('project', 'scene', 'message'));
    }

    /**
     * Show the form for editing the specified message.
     */
    public function edit(Project $project, Scene $scene, Message $message)
    {
        return view('messages.edit', compact('project', 'scene', 'message'));
    }

    /**
     * Update the specified message in storage.
     */
    public function update(Request $request, Project $project, Scene $scene, Message $message)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'type' => 'required|in:text,image,audio,video,gif',
            'metadata' => 'nullable|json'
        ]);

        $message->update($validated);

        return redirect()->route('projects.scenes.messages.index', [$project, $scene])
            ->with('success', 'Messaggio aggiornato con successo.');
    }

    /**
     * Remove the specified message from storage.
     */
    public function destroy(Project $project, Scene $scene, Message $message)
    {
        $message->delete();

        return redirect()->route('projects.scenes.messages.index', [$project, $scene])
            ->with('success', 'Messaggio eliminato con successo.');
    }
} 