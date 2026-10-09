<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Get current authenticated user status.
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user) {
            return response()->json([
                'authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        }

        return response()->json([
            'authenticated' => false,
            'user' => null,
        ]);
    }

    /**
     * Handle user login.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], true)) {
            $request->session()->regenerate();
            $user = Auth::user();

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Username atau password salah.',
        ], 422);
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout.',
            'csrf_token' => csrf_token(),
        ]);
    }

    /**
     * Register a new user (only accessible by authenticated users).
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,username', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'name' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:4'],
        ], [
            'username.required' => 'Username wajib diisi.',
            'username.min' => 'Username minimal 3 karakter.',
            'username.unique' => 'Username sudah terdaftar, gunakan username lain.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, strip, dan underscore.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 4 karakter.',
        ]);

        $newUser = User::create([
            'username' => $validated['username'],
            'name' => $validated['name'] ?: $validated['username'],
            'email' => $validated['username'] . '@geoma.local',
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Akun baru berhasil ditambahkan.',
            'user' => [
                'id' => $newUser->id,
                'username' => $newUser->username,
                'name' => $newUser->name,
            ],
        ]);
    }
}
