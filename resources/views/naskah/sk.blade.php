{{-- Semua keluaran lewat {{ }} (ter-escape); isi kaya lewat <x-isi-aman> di layout. --}}
@extends('naskah.layout')

@section('badan')
    <p>MEMUTUSKAN:</p>
    @include('naskah.partials.variabel')
@endsection
