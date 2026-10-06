@props(['tautan', 'pratinjau' => false])

@if (! $tautan->tertutup())
    @can('view', $tautan)
        <div class="space-y-2">
            <a href="{{ route('berkas.buka', $tautan) }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1 text-blue-800 underline">
                {{ $tautan->label ?: 'Buka berkas' }}
            </a>
            @if ($tautan->status_cek === 'tidak_dapat_diakses')
                <span class="text-xs text-amber-700">Tautan mungkin tidak dapat diakses.</span>
            @endif
            @if ($pratinjau && $tautan->penyedia === 'google_drive' && $tautan->drive_file_id)
                <iframe src="https://drive.google.com/file/d/{{ $tautan->drive_file_id }}/preview"
                        class="h-96 w-full rounded border" sandbox="allow-scripts allow-same-origin"
                        referrerpolicy="no-referrer" loading="lazy" title="Pratinjau berkas"></iframe>
            @endif
        </div>
    @endcan
@endif
