<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'identifier',
        'name',
        'description',
        'image'
    ];

    protected $appends = ['image_url'];

    /**
     * Get the projects that require this item.
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_items');
    }

    /**
     * Get the user progress items for this item.
     */
    public function userProgressItems()
    {
        return $this->hasMany(UserProgressItem::class);
    }

    public function getImageUrlAttribute()
    {
        return $this->image ? Storage::url($this->image) : null;
    }
} 