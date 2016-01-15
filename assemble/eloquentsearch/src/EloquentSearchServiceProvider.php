<?php

namespace Assemble\EloquentSearch;

use Illuminate\Support\ServiceProvider;

class EloquentSearchServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->setupConfig();
    }


    /**
     * Setup the config.
     *
     * @return void
     */
    protected function setupConfig()
    {
        $source = realpath(__DIR__.'/../config/eloquent_search.php');

        $this->publishes([$source => config_path('eloquent_search.php')]);

        $this->mergeConfigFrom($source, 'eloquent_search');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('eloquentsearch.searcher', function ($app) {
            return new Searcher;
        });
    }


}
