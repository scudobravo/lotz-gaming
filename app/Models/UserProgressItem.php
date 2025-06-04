<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProgressItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_progress_id',
        'item_id',
        'collected_at'
    ];

    protected $casts = [
        'collected_at' => 'datetime'
    ];

    /**
     * Get the user progress that owns this item.
     */
    public function userProgress()
    {
        return $this->belongsTo(UserProgress::class);
    }

    /**
     * Get the item that was collected.
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
} 