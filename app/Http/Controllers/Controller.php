<?php

namespace App\Http\Controllers;

use App\Models\RssFeedModel;
use Illuminate\Http\Request;

abstract class Controller 
{
    public function index(Request $request)
    {
       $data = RssFeedModel::all();

       response()->json($data);
    }
}

