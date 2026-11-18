<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Trava de segurança: os testes apagam o banco a cada execução. Com a
     * configuração em cache (php artisan config:cache), o phpunit.xml é
     * ignorado e o banco usado seria o do sistema. Nesse caso, nada roda.
     */
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($app->configurationIsCached() || ($connection !== 'sqlite' && ! str_ends_with($database, '_testing'))) {
            throw new RuntimeException("Testes interrompidos: o banco seria \"{$database}\", não o de testes. Rode \"php artisan config:clear\" e tente de novo.");
        }

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
