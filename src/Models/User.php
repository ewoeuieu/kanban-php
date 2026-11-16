<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $fillable = ['email', 'name', 'password_hash'];
    protected $hidden = ['password_hash'];
}
