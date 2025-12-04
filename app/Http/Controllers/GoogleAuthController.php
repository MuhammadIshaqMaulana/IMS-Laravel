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

            // Ambil daftar email admin dari .env dan bersihkan dari spasi
            $adminEmails = array_map('trim', explode(',', env('ADMIN_EMAILS', '')));
            $userEmail = $googleUser->getEmail();

            // --- LOGIKA OTORISASI ADMIN ---

            // 1. Cek apakah email pengguna ada di daftar ADMIN_EMAILS
            $isAdmin = in_array($userEmail, $adminEmails);

            if (!$isAdmin) {
                // Jika bukan admin, tolak login
                return redirect()->route('home')->with('error', 'Akses ditolak. Email Anda tidak terdaftar sebagai admin IMS.');
            }

            // 2. Cari user berdasarkan google_id atau email
            $user = User::where('google_id', $googleUser->getId())
                        ->orWhere('email', $userEmail)
                        ->first();

            if ($user) {
                // User sudah ada (Login atau link akun)
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'is_admin' => $isAdmin, // Perbarui status admin (tetap True jika sudah lolos cek)
                ]);
            } else {
                // User baru (hanya terjadi jika email lolos cek admin)
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $userEmail,
                    'google_id' => $googleUser->getId(),
                    'password' => Hash::make(Str::random(16)), // Password acak
                    'is_admin' => true, // Secara eksplisit set true karena lolos validasi
                ]);
            }

            // 3. Login pengguna
            Auth::login($user);

            // Redirect ke halaman yang aman (daftar bahan mentah)
            return redirect()->route('bahan-mentah.index')
                             ->with('success', 'Selamat datang Admin, Anda berhasil login via Google.');

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
