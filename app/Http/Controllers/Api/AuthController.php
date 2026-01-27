<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Die Anmeldedaten sind falsch.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Abgemeldet']);
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'device_id' => 'nullable|string',
        ]);

        $user = $request->user();
        $fcmToken = $request->fcm_token;
        $deviceId = $request->device_id;

        // Falls dieser Token bereits bei einem anderen Benutzer registriert ist, dort entfernen
        \App\Models\UserDevice::where('fcm_token', $fcmToken)
            ->where('user_id', '!=', $user->id)
            ->delete();

        if ($deviceId) {
            $user->devices()->updateOrCreate(
                ['device_id' => $deviceId],
                ['fcm_token' => $fcmToken]
            );
        } else {
            $user->devices()->firstOrCreate(
                ['fcm_token' => $fcmToken],
                ['device_id' => null]
            );
        }

        // Abwärtskompatibilität: Einzelnes Token im User-Model ebenfalls aktualisieren
        $user->update([
            'fcm_token' => $fcmToken,
        ]);

        return response()->json(['message' => 'FCM Token aktualisiert']);
    }
}
