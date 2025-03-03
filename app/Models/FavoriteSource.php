<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FavoriteSource extends Model
{
    protected $fillable = [
        'user_id',
        'source',
    ];
}
