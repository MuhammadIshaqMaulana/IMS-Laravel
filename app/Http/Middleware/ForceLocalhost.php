<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceLocalhost
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Mendapatkan host yang digunakan saat ini (misalnya: 127.0.0.1 atau localhost)
        $currentHost = $request->getHost();
        $targetHost = 'localhost';
        $port = $request->getPort();

        // Cek jika host saat ini adalah 127.0.0.1 dan port 8000 (sesuai konfigurasi Anda)
        if ($currentHost === '127.0.0.1' && $port === 8000) {

            // Bangun URL yang baru dengan host 'localhost'
            $redirectUrl = $request->getSchemeAndHttpHost() . $request->getRequestUri();

            // Ganti 127.0.0.1 dengan localhost
            $redirectUrl = str_replace('127.0.0.1', $targetHost, $redirectUrl);

            // Lakukan redirect permanen (301) ke URL berbasis localhost
            return redirect($redirectUrl, 301);
        }

        return $next($request);
    }
}
