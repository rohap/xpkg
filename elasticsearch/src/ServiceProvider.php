<?php

namespace Xpkg\Elasticsearch;

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
            __DIR__ . '/config/elasticsearch.php' => config_path('elasticsearch.php'),
        ], 'config');
    }
}