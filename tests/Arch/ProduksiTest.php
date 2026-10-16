<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Produksi Profil Ringan (STANDAR-TEKNIS §1b dan §7): basis data MariaDB NATIVE di host,
 * tanpa container database, Redis, Horizon, maupun Gotenberg; setiap container berbatas memori dan lognya dirotasi.
 */
function komposProduksi(): array
{
    return Yaml::parseFile(base_path('docker-compose.prod.yml'));
}

it('hanya memuat layanan app, nginx, scheduler, dan backup', function () {
    expect(array_keys(komposProduksi()['services']))->toEqualCanonicalizing(['app', 'nginx', 'scheduler', 'backup']);
});

it('tidak memuat container database, redis, queue, atau gotenberg', function () {
    $kompos = komposProduksi();

    foreach (['queue', 'redis', 'gotenberg', 'db', 'mysql', 'mariadb', 'meilisearch'] as $layanan) {
        expect($kompos['services'])->not->toHaveKey($layanan);
    }

    foreach (['redis-data', 'db-data'] as $volume) {
        expect($kompos['volumes'] ?? [])->not->toHaveKey($volume);
    }

    // hasil parse (tanpa komentar): tidak ada image/perintah Redis, Horizon, atau Gotenberg
    expect(json_encode($kompos))->not->toMatch('/horizon|redis|gotenberg/i');
});

it('memakai image mariadb hanya sebagai klien cadangan berprofil backup', function () {
    $kompos = komposProduksi();

    foreach ($kompos['services'] as $nama => $layanan) {
        if (str_contains((string) ($layanan['image'] ?? ''), 'mariadb') || str_contains((string) ($layanan['image'] ?? ''), 'mysql')) {
            expect($nama)->toBe('backup')
                ->and($layanan['profiles'])->toBe(['backup'])
                ->and($layanan['command'])->toBe(['/skrip/backup.sh']);
        }
    }
});

it('menghubungkan container ke basis data host lewat host-gateway', function () {
    foreach (['app', 'scheduler', 'backup'] as $nama) {
        expect(komposProduksi()['services'][$nama]['extra_hosts'])->toContain('host.docker.internal:host-gateway');
    }

    expect(file_get_contents(base_path('.env.production.example')))->toContain("\nDB_HOST=host.docker.internal\n");
});

it('menjalankan penjadwal sebagai pemicu worker tanpa bergantung pada layanan lain', function () {
    $penjadwal = komposProduksi()['services']['scheduler'];

    expect($penjadwal['command'])->toBe(['php', 'artisan', 'schedule:work'])
        ->and($penjadwal)->not->toHaveKey('depends_on')
        ->and($penjadwal['stop_grace_period'])->toBe('60s');
});

it('membatasi memori dan merotasi log setiap container', function () {
    foreach (komposProduksi()['services'] as $nama => $layanan) {
        expect($layanan)->toHaveKey('mem_limit')
            ->and($layanan['logging']['driver'])->toBe('json-file')
            ->and($layanan['logging']['options'])->toBe(['max-size' => '10m', 'max-file' => '3']);
    }
});

it('menjaga Dockerfile tanpa Redis dan Horizon serta berbasis PHP 8.5', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->not->toMatch('/redis|horizon/i')
        ->and($dockerfile)->toContain('ARG PHP_VERSION=8.5')
        ->and($dockerfile)->toContain('pdo_mysql');
});

it('menyetel PHP-FPM ondemand dengan empat worker', function () {
    $pool = file_get_contents(base_path('docker/php/www.conf'));

    expect($pool)->toMatch('/^pm = ondemand$/m')
        ->and($pool)->toMatch('/^pm\.max_children = 4$/m')
        ->and($pool)->toMatch('/^pm\.process_idle_timeout = 10s$/m');
});
