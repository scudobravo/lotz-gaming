<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'description'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function isMasterAdmin(): bool
    {
        return $this->name === 'MA';
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === 'SA';
    }

    public function isAdmin(): bool
    {
        return $this->name === 'A';
    }
}
