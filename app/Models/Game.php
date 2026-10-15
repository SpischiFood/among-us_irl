<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'name',
        'code',
        'status',
        'impostor_count',
        'tasks',
    ];

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    protected function casts(): array
    {
        return [
            'tasks' => 'array',
            'impostor_count' => 'integer',
        ];
    }
}
