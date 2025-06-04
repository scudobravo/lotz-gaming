<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_image',
        'status',
        'qr_code',
        'initial_scene_id',
        'created_by'
    ];

    protected $casts = [
        'config' => 'json'
    ];

    /**
     * Get the creator of the project.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the scenes for the project.
     */
    public function scenes()
    {
        return $this->hasMany(Scene::class);
    }

    /**
     * Get the initial scene of the project.
     */
    public function initialScene()
    {
        return $this->belongsTo(Scene::class, 'initial_scene_id');
    }

    /**
     * Get the required items for the project.
     */
    public function requiredItems()
    {
        return $this->belongsToMany(Item::class, 'project_item');
    }

    /**
     * Get the letters for the project.
     */
    public function letters()
    {
        return $this->hasMany(Letter::class);
    }
} 