<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BoardColumn extends Model
{
    protected $fillable = ['title', 'sort_index'];

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'column_id');
    }
}
