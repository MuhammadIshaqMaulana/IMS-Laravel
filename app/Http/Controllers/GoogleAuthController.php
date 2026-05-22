<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    /**
     * Redirect pengguna ke halaman otentikasi Google.
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Menerima informasi pengguna dari Google dan mengotentikasi.
     */
    public function handleGoogleCallback()
    {
        try {
            // Ambil data pengguna dari Google
            $googleUser = Socialite::driver('google')->user();
            $userEmail = $googleUser->getEmail();

            // 1. Cari user berdasarkan google_id atau email
            $user = User::where('google_id', $googleUser->getId())
                        ->orWhere('email', $userEmail)
                        ->first();

            // LOGIKA OTORISASI (OPEN ACCESS UNTUK DEVELOPMENT)
            if (!$user) {
                // Buat user baru dan set sebagai admin (Siapapun yang login jadi admin)
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $userEmail,
                    'google_id' => $googleUser->getId(),
                    'password' => Hash::make(Str::random(16)), // Password acak
                    'is_admin' => true, // SET PERAN ADMIN
                ]);
            } else {
                // Pastikan user lama tetap admin jika ada perubahan status manual sebelumnya
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'is_admin' => true, 
                ]);
            }

            // 3. Login pengguna
            Auth::login($user);

            // Redirect ke Dashboard setelah login sukses
            return redirect()->route('dashboard')
                             ->with('success', 'Selamat datang, Anda berhasil login via Google.');

        } catch (\Exception $e) {
            Log::error("Google Auth Error: " . $e->getMessage());
            return redirect()->route('home')->with('error', 'Gagal login dengan Google. Silakan coba lagi.');
        }
    }

    /**
     * Implementasi Logout Sederhana
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Anda telah berhasil logout.');
    }
}
