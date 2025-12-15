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

            // Ambil daftar email admin dari .env dan bersihkan dari spasi (Untuk cek awal)
            $adminEmailsFromEnv = array_map('trim', explode(',', env('ADMIN_EMAILS', '')));

            // 1. Cari user berdasarkan google_id atau email
            $user = User::where('google_id', $googleUser->getId())
                        ->orWhere('email', $userEmail)
                        ->first();

            // LOGIKA OTORISASI DAN PENDAFTARAN
            if (!$user) {
                // KASUS 1: USER BARU. Cek apakah user ada di daftar ADMIN_EMAILS di .env
                $isNewAdmin = in_array($userEmail, $adminEmailsFromEnv);

                if (!$isNewAdmin) {
                    // Jika user baru tidak ada di daftar .env, TOLAK LOGIN.
                    return redirect()->route('home')->with('error', 'Akses ditolak. Email Anda tidak terdaftar sebagai admin IMS.');
                }

                // Buat user baru dan set sebagai admin
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $userEmail,
                    'google_id' => $googleUser->getId(),
                    'password' => Hash::make(Str::random(16)), // Password acak
                    'is_admin' => true, // SET PERAN ADMIN DI SINI
                ]);
            } else {
                // KASUS 2: USER LAMA. Update detail jika diperlukan.
                $user->update([
                    'google_id' => $googleUser->getId(),
                    // Status is_admin tidak diubah karena sudah diatur di login pertama
                ]);

                // Pengecekan keamanan tambahan: pastikan user lama adalah admin
                if (!$user->is_admin) {
                     return redirect()->route('home')->with('error', 'Akses ditolak. Akun Anda bukan admin IMS.');
                }
            }

            // 3. Login pengguna
            Auth::login($user);

            // Redirect ke Dashboard setelah login sukses
            return redirect()->route('dashboard')
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
