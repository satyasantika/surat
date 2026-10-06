{{-- Semua keluaran lewat {{ }} (ter-escape); isi kaya lewat <x-isi-aman> di layout. --}}
@extends('naskah.layout')

@section('badan')
    <p>Pada hari ini telah dilaksanakan kegiatan dengan rincian berikut:</p>
    @include('naskah.partials.variabel')
@endsection
