<?php

it('menjawab health check', function () {
    $this->get('/up')->assertOk();
});

it('memakai locale indonesia dan zona waktu jakarta', function () {
    expect(app()->getLocale())->toBe('id')
        ->and(config('app.timezone'))->toBe('Asia/Jakarta');
});
