<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number',
        'project_id',
        'current_scene_id',
        'attempts_remaining',
        'last_interaction_at'
    ];

    protected $casts = [
        'last_interaction_at' => 'datetime'
    ];

    /**
     * Get the project that owns this progress.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the current scene of the user.
     */
    public function currentScene()
    {
        return $this->belongsTo(Scene::class, 'current_scene_id');
    }

    /**
     * Get the collected items for this progress.
     */
    public function collectedItems()
    {
        return $this->hasMany(UserProgressItem::class);
    }
} 