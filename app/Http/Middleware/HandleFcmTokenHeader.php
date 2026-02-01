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
            auth()->user()->updateFcmToken(
                $request->header('X-FCM-Token'),
                $request->header('X-Device-ID')
            );
        }

        return $response;
    }
}
