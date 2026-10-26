<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\GagalMasukGoogle;
use App\Services\Auth\PenautanGoogle;
use App\Support\Beranda;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as UserGoogle;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as RedirectSymfony;
use Throwable;

/**
 * Masuk dengan Google dari halaman masuk publik maupun halaman masuk panel. Pengguna berperan panel selalu
 * diarahkan ke /admin; pengguna tanpa peran panel ke berandanya (dengan pesan bila alur dimulai dari panel).
 *
 * Tantangan MFA Filament hanya dapat dituntaskan di dalam komponen Login panel (perlu kata sandi), sehingga akun
 * yang memasang verifikasi dua langkah ditolak di sini dan diminta masuk lewat halaman admin.
 */
class GoogleController extends Controller
{
    public function arah(Request $request): RedirectSymfony
    {
        if (blank(config('services.google.client_id')) || blank(config('services.google.client_secret'))) {
            return $this->gagal('Masuk dengan Google belum tersedia.');
        }

        if ($request->query('dari') === 'admin') {
            $request->session()->put('google_dari', 'admin');
        } else {
            $request->session()->forget('google_dari');
        }

        /** @var AbstractProvider $penyedia */
        $penyedia = Socialite::driver('google');

        return $penyedia->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(Request $request, PenautanGoogle $penautan): RedirectResponse
    {
        $dariAdmin = $request->session()->pull('google_dari') === 'admin';

        if ($request->filled('error')) {
            return $this->gagal('Masuk dengan Google dibatalkan.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->gagal('Sesi masuk dengan Google kedaluwarsa. Silakan coba lagi.');
        } catch (Throwable $e) {
            report($e);

            return $this->gagal('Tidak dapat terhubung dengan Google. Silakan coba lagi.');
        }

        try {
            $user = $this->tautkan($penautan, $googleUser);
        } catch (GagalMasukGoogle $e) {
            return $this->gagal($e->getMessage());
        } catch (UniqueConstraintViolationException) {
            return $this->gagal('Masuk dengan Google gagal sesaat. Silakan coba lagi.');
        }

        if (! $user->aktif) {
            return $this->gagal('Surel atau kata sandi salah.');
        }

        $peranPanel = $user->roles->pluck('name')->diff(User::PERAN_TANPA_PANEL)->isNotEmpty();

        // Akun panel tidak diberi cookie "ingat saya" (berumur panjang); hanya akun tanpa peran panel.
        Auth::login($user, ! $peranPanel);
        $request->session()->regenerate();

        if ($peranPanel) {
            return redirect()->to(url('admin'));
        }

        $tujuan = redirect()->to(Beranda::url($user));

        return $dariAdmin ? $tujuan->with('status', 'Akun ini tidak memakai panel admin.') : $tujuan;
    }

    /**
     * Dua login pertama yang bersamaan dapat bertabrakan pada indeks unik; percobaan kedua menemukan baris yang
     * sudah dibuat percobaan lain.
     *
     * @throws GagalMasukGoogle
     * @throws UniqueConstraintViolationException
     */
    private function tautkan(PenautanGoogle $penautan, UserGoogle $googleUser): User
    {
        $raw = $googleUser instanceof AbstractUser ? $googleUser->getRaw() : [];
        $argumen = [
            (string) $googleUser->getId(),
            (string) $googleUser->getEmail(),
            filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOL),
            (string) $googleUser->getName(),
            isset($raw['hd']) ? (string) $raw['hd'] : null,
            // Tantangan MFA Filament hanya ada di komponen Login panel; akun ber-MFA ditolak sebelum efek samping penautan.
            function (User $user): void {
                if (MultiFactorChallenge::make()->hasEnabledProviders($user)) {
                    throw new GagalMasukGoogle('Akun ini memakai verifikasi dua langkah; masuk lewat halaman admin.');
                }
            },
        ];

        try {
            return $penautan->masuk(...$argumen);
        } catch (UniqueConstraintViolationException) {
            return $penautan->masuk(...$argumen);
        }
    }

    private function gagal(string $pesan): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $pesan]);
    }
}
