<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;






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
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'name' => 'required',
            'role' => 'in:admin,user',
        ]);

        $user = User::create([
            'name' => $validatedData['name'] ?? 'guest',
            'email' => $validatedData['email'],
            'password' => bcrypt($validatedData['password']),
            'role' => $validatedData['role'] ?? 'user',
            'api_token' => Str::uuid(),
        ]);

        if (!$user) {
            return response()->json([
                'message' => 'User not created',
            ], 422);
        }

        $token = $user->createToken('token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }

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

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }


public function setApiKey(Request $request)
{
    try {
        // Validate request
        $validated = $request->validate([
            'api_key' => 'required|string'
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'message' => 'User not found or unauthorized',
            ], 401);
        }

        try {
            User::where('id', $user->id)->update([
                'api_token' => $validated['api_key'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update API key', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to update API key',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'user' => $user,
            'message' => 'API key updated successfully in dat',
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation error',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('Unexpected error while updating API key', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'An unexpected error occurred',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function signIn(Request $request)
{
    Log::info('Sign-in attempt', ['email' => $request->input('email')]);

    try {
        // ✅ التحقق من صحة المدخلات
        $auth = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        Log::info('Validation passed', ['email' => $auth['email']]);

        // ✅ البحث عن المستخدم
        $user = User::where('email', $auth['email'])->first();

        if (!$user) {
            Log::warning('User not found', ['email' => $auth['email']]);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // ✅ التحقق من كلمة المرور
        if (!Hash::check($auth['password'], $user->password)) {
            Log::warning('Password mismatch', ['email' => $auth['email']]);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        Log::info('Authentication successful', ['user_id' => $user->id]);

        // ✅ إنشاء التوكن
        $token = $user->createToken('token')->plainTextToken;

        Log::info('Token created successfully', ['user_id' => $user->id]);

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation error', [
            'errors' => $e->errors(),
        ]);

        return response()->json([
            'message' => 'Validation error',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        Log::error('Unexpected error in sign-in', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Something went wrong. Please try again later.',
        ], 500);
    }
}
}
