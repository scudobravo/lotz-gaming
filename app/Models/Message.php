<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'scene_id',
        'content',
        'type',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'json'
    ];

    /**
     * Get the scene that owns the message.
     */
    public function scene()
    {
        return $this->belongsTo(Scene::class);
    }
} 