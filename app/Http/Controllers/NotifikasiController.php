<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Daftar notifikasi basis data milik pengguna sendiri (hanya dirinya; tanpa id pengguna lain di input). */
class NotifikasiController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('auth.notifikasi', ['daftar' => $user->notifications()->limit(50)->get(), 'belumDibaca' => $user->unreadNotifications()->count()]);
    }

    public function bacaSemua(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function buka(Request $request, string $id): RedirectResponse
    {
        $n = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $n->markAsRead();
        $url = $n->data['url'] ?? null;

        return is_string($url) && str_starts_with($url, url('/')) ? redirect()->to($url) : redirect()->route('notifikasi');
    }
}
