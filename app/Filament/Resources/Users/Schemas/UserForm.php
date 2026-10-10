<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Actions\Pengguna\SimpanPengguna;
use App\Rules\SurelDomainUnsil;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun')->columns(2)->schema([
                TextInput::make('name')->label('Nama')->required()->maxLength(150),
                TextInput::make('email')->label('Surel')->email()->required()->rule(new SurelDomainUnsil),
                TextInput::make('nip_nim')->label('NIP/NIM')->maxLength(30),
                TextInput::make('telepon')->label('Telepon')->tel()->maxLength(20)
                    ->helperText('Data pribadi; dipakai untuk notifikasi.'),
                Toggle::make('aktif')->label('Aktif')->default(true),
                Toggle::make('wajib_ganti_sandi')->label('Wajib ganti sandi saat masuk')
                    ->helperText('Nyalakan untuk akun dengan kata sandi awal yang dibuat admin.')
                    ->default(false),
            ]),
            Section::make('Hak akses')->schema([
                Select::make('peran')->label('Peran')->multiple()->live()
                    ->options(fn () => collect(array_keys(PeranDanIzinSeeder::PERAN))
                        ->reject(fn (string $p) => in_array($p, SimpanPengguna::PERAN_TERBATAS, true) && ! auth()->user()->hasRole('super-admin'))
                        ->mapWithKeys(fn (string $p) => [$p => $p])->all()),
                CheckboxList::make('izin_langsung')->label('Izin untuk operator layanan')
                    ->options(array_combine(PeranDanIzinSeeder::IZIN, PeranDanIzinSeeder::IZIN))
                    ->columns(3)->searchable()
                    ->visible(fn (Get $get) => in_array('operator-layanan', (array) $get('peran'), true))
                    ->helperText('Hanya izin yang Anda miliki yang dapat diberikan.'),
            ]),
        ]);
    }
}
