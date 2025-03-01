<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadyFeed extends Model
{
    protected $fillable = [
        'source',
        'originalTitle',
        'newTitle',
        'link',
        'originalDescription',
        'newDescription',
        'thumbnail',
        'location',
        'pubDate',
    ];
}
