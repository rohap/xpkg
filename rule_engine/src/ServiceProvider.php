<?php

namespace Xpkg\RuleEngine;

use Xpkg\RuleEngine\Commands\RulesInstall;
use Xpkg\RuleEngine\Commands\RulesSync;
use Illuminate\Support\ServiceProvider as laravelServiceProvider;

class ServiceProvider extends laravelServiceProvider
{
    public function register()
    {
        $this->mergeConfig();
    }

    public function boot()
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->publishSeeders();

        if ($this->app->runningInConsole()) {
            $this->commands([
                RulesSync::class,
                RulesInstall::class,
            ]);
        }
    }

    private function mergeConfig()
    {
        $path = $this->getConfigPath();
        $this->mergeConfigFrom($path, 'rules');
    }

    private function publishConfig()
    {
        $path = $this->getConfigPath();
        $this->publishes([$path => config_path('rules.php')], 'config');
    }

    private function publishMigrations()
    {
        $path = $this->getMigrationsPath();
        $this->publishes([$path => database_path('migrations')], 'migrations');
    }

    private function publishSeeders()
    {
        $path = $this->getSeedersPath();
        $this->publishes([$path => database_path('seeders')], 'seeders');
    }

    private function getConfigPath(): string
    {
        return __DIR__ . '/config/rules.php';
    }

    private function getMigrationsPath(): string
    {
        return __DIR__ . '/migrations/';
    }

    private function getSeedersPath(): string
    {
        return __DIR__ . '/seeders/';
    }
}