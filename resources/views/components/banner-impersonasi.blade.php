@if (session('impersonator_id') && auth()->check())
    <div class="w-full bg-red-600 px-4 py-2 text-center text-sm font-medium text-white" role="alert">
        Anda sedang masuk sebagai {{ auth()->user()->name }} ({{ auth()->user()->email }}).
        <form method="POST" action="{{ route('impersonasi.selesai') }}" class="inline">
            @csrf
            <button type="submit" class="ml-2 underline">Kembali ke akun saya</button>
        </form>
    </div>
@endif
