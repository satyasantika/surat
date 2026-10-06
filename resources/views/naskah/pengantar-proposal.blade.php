{{-- Semua keluaran lewat {{ }} (ter-escape); isi kaya lewat <x-isi-aman> di layout. --}}
@extends('naskah.layout')

@section('badan')
    <p>Dengan hormat,</p>
    @include('naskah.partials.variabel')
@endsection
