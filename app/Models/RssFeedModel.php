<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;




class RssFeedModel extends Model

{
    use HasFactory;
    protected $table = 'rss_feeds';
    protected $fillable = ['source', 'title', 'link', 'description', 'pubDate','isPublished'];
    protected $casts = [
        'pubDate' => 'datetime'
    ];


}
