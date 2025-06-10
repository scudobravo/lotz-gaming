<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Scene extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'title',
        'entry_message',
        'type',
        'order',
        'metadata',
        'media_gif',
        'media_audio',
        'puzzle_question',
        'correct_answer',
        'success_message',
        'failure_message',
        'max_attempts',
        'item_id',
        'character_id',
        'next_scene_id',
        'choices'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    protected $appends = ['media_gif_url', 'media_audio_url'];

    /**
     * Get the project that owns the scene.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the choices for the scene.
     */
    public function choices()
    {
        return $this->hasMany(Choice::class);
    }

    /**
     * Get the messages for the scene.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the next scenes through choices.
     */
    public function nextScenes()
    {
        return $this->hasManyThrough(Scene::class, Choice::class, 'scene_id', 'id', 'id', 'next_scene_id');
    }

    public function getMediaGifUrlAttribute()
    {
        return $this->media_gif ? Storage::url($this->media_gif) : null;
    }

    public function getMediaAudioUrlAttribute()
    {
        return $this->media_audio ? Storage::url($this->media_audio) : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($scene) {
            if (!$scene->order) {
                // Se non è specificato un ordine, prendi l'ultimo ordine del progetto e aggiungi 1
                $lastOrder = static::where('project_id', $scene->project_id)
                    ->max('order');
                $scene->order = $lastOrder ? $lastOrder + 1 : 1;
            }
        });

        static::updating(function ($scene) {
            if ($scene->isDirty('next_scene_id')) {
                $oldNextSceneId = $scene->getOriginal('next_scene_id');
                $newNextSceneId = $scene->next_scene_id;

                if ($newNextSceneId) {
                    // Ottieni la scena successiva
                    $nextScene = static::find($newNextSceneId);
                    
                    // Se la scena successiva esiste e ha un ordine maggiore
                    if ($nextScene && $nextScene->order > $scene->order) {
                        // Sposta tutte le scene tra la corrente e la successiva di un posto indietro
                        static::where('project_id', $scene->project_id)
                            ->where('order', '>', $scene->order)
                            ->where('order', '<', $nextScene->order)
                            ->decrement('order');
                        
                        // Imposta l'ordine della scena corrente a uno meno della scena successiva
                        $scene->order = $nextScene->order - 1;
                    }
                } elseif ($oldNextSceneId) {
                    // Se stiamo rimuovendo la scena successiva, sposta questa scena alla fine
                    $lastOrder = static::where('project_id', $scene->project_id)
                        ->max('order');
                    $scene->order = $lastOrder + 1;
                }
            }
        });
    }
} 