<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Letter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'title',
        'content',
        'unlock_conditions',
        'metadata'
    ];

    protected $casts = [
        'unlock_conditions' => 'json',
        'metadata' => 'json'
    ];

    /**
     * Get the project that owns the letter.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
} 