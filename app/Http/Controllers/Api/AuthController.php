<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'device_name' => 'required',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken,
            'user' => $user
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

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
        $timezone = $request->header('X-Timezone') ?? $request->timezone;

        if ($timezone) {
            $user->update(['timezone' => $timezone]);
        }

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
            'timezone' => 'nullable|string|timezone',
        ]);

        if ($request->timezone) {
            $request->user()->update(['timezone' => $request->timezone]);
        }

        $request->user()->updateFcmToken(
            $request->fcm_token,
            $request->device_id
        );

        return response()->json(['message' => 'FCM Token aktualisiert']);
    }
}
