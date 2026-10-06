<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity;

class Aktivitas extends Activity
{
    use HasUuids;

    protected static function booted(): void
    {
        // Selama "masuk sebagai", catat pelaku asli agar tanggung jawab tetap terlacak.
        static::creating(function (self $aktivitas) {
            if (app()->bound('session.store') && ($asli = session('impersonator_id'))) {
                $aktivitas->properties = collect($aktivitas->properties)->put('impersonator_id', $asli);
            }
        });
    }
}
