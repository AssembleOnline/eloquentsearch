<?php

namespace Assemble\EloquentSearch;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;

class EloquentSearchServiceProvider extends ServiceProvider
{
    use Concerns\JoinsToModel;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $source = __DIR__.'/../config/eloquent_search.php';

        $this->publishes([$source => config_path('eloquent_search.php')]);

        $this->mergeConfigFrom($source, 'eloquent_search');


        // query helper
        Builder::macro('joinsToModel', $this->joinsToModel());
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
