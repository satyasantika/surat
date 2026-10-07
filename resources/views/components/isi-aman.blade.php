@props(['html' => null])
{{-- Satu-satunya tempat keluaran HTML mentah: isi sudah disanitasi saat simpan dan disanitasi ulang di sini. --}}
{!! \App\Support\SanitasiHtml::bersihkan($html) !!}
