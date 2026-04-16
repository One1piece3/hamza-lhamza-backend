<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminTokenStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'is_admin' => false,
        ]);

        return response()->json([
            'message' => 'Compte client cree avec succes',
            'role' => 'customer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);

        $email = strtolower($data['email']);
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Identifiants invalides',
            ], 422);
        }

        if (!$user->is_admin) {
            return response()->json([
                'message' => 'Connexion client reussie',
                'role' => 'customer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        }

        $token = Str::random(60);
        $remember = (bool) ($data['remember'] ?? false);
        $expiresAt = $remember ? now()->addDays(30) : now()->addHours(8);

        AdminTokenStore::put($token, $user->id, $expiresAt);

        return response()->json([
            'message' => 'Connexion admin reussie',
            'role' => 'admin',
            'token' => $token,
            'expires_in_minutes' => now()->diffInMinutes($expiresAt),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->attributes->get('admin_token');

        if ($token) {
            AdminTokenStore::forget($token);
        }

        return response()->json([
            'message' => 'Deconnexion reussie',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink([
            'email' => strtolower($data['email']),
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => 'Lien de reinitialisation envoye si le compte existe.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            [
                ...$data,
                'email' => strtolower($data['email']),
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => 'Mot de passe reinitialise avec succes.',
        ]);
    }
}
