<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\UserDevice;

class HandleFcmTokenHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Nur verarbeiten, wenn der Benutzer eingeloggt ist und die Header vorhanden sind
        if (auth()->check() && $request->hasHeader('X-FCM-Token')) {
            $user = auth()->user();
            $fcmToken = $request->header('X-FCM-Token');
            $deviceId = $request->header('X-Device-ID');

            // Falls dieser Token bereits bei einem anderen Benutzer registriert ist, dort entfernen
            UserDevice::where('fcm_token', $fcmToken)
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
            if ($user->fcm_token !== $fcmToken) {
                $user->update(['fcm_token' => $fcmToken]);
            }
        }

        return $response;
    }
}
