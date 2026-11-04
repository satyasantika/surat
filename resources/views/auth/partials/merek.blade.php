{{-- Tanda merek (ikon amplop) seperti header beranda. $ponsel = varian bilah atas pada layar sempit. --}}
<a href="{{ route('beranda') }}" @class(['sr-merek', 'sr-merek-ponsel' => $ponsel ?? false])>
    <i aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="{{ ($ponsel ?? false) ? '#ffffff' : '#1e3a8a' }}" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></i>
    {{ config('app.name') }}
</a>
