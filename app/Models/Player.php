<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    protected $fillable = [
        'game_id',
        'name',
        'token',
        'role',
        'alive',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
