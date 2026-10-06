{{-- Semua keluaran lewat {{ }} (ter-escape); isi kaya lewat <x-isi-aman> di layout. --}}
@extends('naskah.layout')

@section('badan')
    <p>Yang bertanda tangan di bawah ini memberi tugas kepada:</p>
    @include('naskah.partials.variabel')
@endsection
