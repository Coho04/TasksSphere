<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
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

        // Falls FCM Token im Header oder Body mitgesendet wird, auch beim Login speichern
        $fcmToken = $request->header('X-FCM-Token') ?? $request->fcm_token;
        $deviceId = $request->header('X-Device-ID') ?? $request->device_id;

        if ($fcmToken) {
            $user->updateFcmToken($fcmToken, $deviceId);
        }

        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $accessToken = $request->user()->currentAccessToken();

        $accessToken->delete();
        return response()->json(['message' => 'Abgemeldet']);
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'device_id' => 'nullable|string',
        ]);

        $request->user()->updateFcmToken(
            $request->fcm_token,
            $request->device_id
        );

        return response()->json(['message' => 'FCM Token aktualisiert']);
    }
}
