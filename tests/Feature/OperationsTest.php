<?php

use App\Enums\Role;
use Illuminate\Support\Facades\Storage;

it('envia os cabeçalhos de segurança', function () {
    $response = $this->get(route('login'));

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($response->headers->get('Permissions-Policy'))->toContain('geolocation=(self)');
});

it('gera a cópia do banco e apaga as antigas', function () {
    Storage::fake('local');
    Storage::disk('local')->put('backups/antiga.sql.gz', str_repeat('x', 200));
    touch(Storage::disk('local')->path('backups/antiga.sql.gz'), now()->subDays(30)->getTimestamp());
    userWithRole(Role::Admin);

    $this->artisan('app:backup-database', ['--php' => true, '--keep' => 14])->assertSuccessful();

    $files = Storage::disk('local')->files('backups');
    expect($files)->toHaveCount(1)
        ->and($files[0])->toEndWith('.sql.gz')
        ->and(gzdecode(Storage::disk('local')->get($files[0])))->toContain('CREATE TABLE `users`');
});

it('agenda fila, avisos e backup', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('app:send-alerts --upcoming')
        ->expectsOutputToContain('app:backup-database')
        ->assertSuccessful();
});
