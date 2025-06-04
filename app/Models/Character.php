<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Character extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'gif_path',
        'gif_url',
        'audio_path',
        'audio_url'
    ];

    public function projects()
    {
        return $this->belongsToMany(Project::class);
    }
} 