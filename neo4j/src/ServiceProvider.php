<?php

namespace Xpkg\Neo4j;

use Illuminate\Support\ServiceProvider as laravelServiceProvider;

class ServiceProvider extends laravelServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
    
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/neo4j.php' => config_path('neo4j.php'),
        ], 'config');
    }
}