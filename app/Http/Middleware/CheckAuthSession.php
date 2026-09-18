<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAuthSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Tolak jika user masih dalam status pre-auth 2FA pending
        if (session('2fa_pending')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Harap selesaikan verifikasi 2FA terlebih dahulu.'
                ], 403);
            }

            return redirect()->route('login.2fa');
        }

        // Tolak jika belum login sama sekali
        if (!session('logged_in')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sesi login telah berakhir. Silakan login kembali.'
                ], 401);
            }

            return redirect()->route('login')->withErrors([
                'login_error' => 'Silakan masuk (login) terlebih dahulu untuk mengakses dashboard.'
            ]);
        }

        return $next($request);
    }
}
