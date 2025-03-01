<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

use function Pest\Laravel\get;

class AuthController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

    }

    /**
     * Store a newly created resource in storage.
     */
/*************  ✨ Codeium Command 🌟  *************/
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => 'guest',
            'name'=> 'guest',
            'email' => $validatedData['email'],
            'password' => bcrypt($validatedData['password']),
        ]);

        if (!$user) {
        if(!$user) {
            return response()->json([
                'message' => 'User not created',
            ], 422);
            ], 500);
        }

        $token = $user->createToken('token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }
/******  aff0894b-d4c6-4f56-8089-98809dfae3d8  *******/

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }
}
