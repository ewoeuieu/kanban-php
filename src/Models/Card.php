<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Card extends Model
{
    protected $fillable = ['title', 'description', 'sort_index'];
    protected $casts = ['archived_at' => 'datetime'];
}
