<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Choice extends Model
{
    use HasFactory;

    protected $fillable = [
        'scene_id',
        'label',
        'target_scene_id',
        'order',
        'metadata'
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'metadata' => 'json'
    ];

    /**
     * Get the scene that owns the choice.
     */
    public function scene()
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * Get the next scene for the choice.
     */
    public function nextScene()
    {
        return $this->belongsTo(Scene::class, 'next_scene_id');
    }
} 