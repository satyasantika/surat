<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Jejak audit server-side (BR-22). Pelaku selalu auth()->user() (bawaan activitylog);
 * jangan memanggil causedBy() dengan nilai dari input pengguna.
 */
trait TercatatAktivitas
{
    use LogsActivity;

    /** @var list<string> Atribut yang tidak boleh masuk log selain yang disembunyikan model. */
    protected static array $dikecualikanDariLog = [
        'password', 'remember_token', 'token', 'api_token',
        'app_authentication_secret', 'app_authentication_recovery_codes',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(array_values(array_unique([...static::$dikecualikanDariLog, ...$this->getHidden()])))
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->namaLog());
    }

    protected function namaLog(): string
    {
        return Str::kebab(class_basename($this));
    }
}
