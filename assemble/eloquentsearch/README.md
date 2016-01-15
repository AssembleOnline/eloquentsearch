# Eloquent Model Searcher for Laravel 5

## Instalation

Add this line to your `providers` array:
``` php
Assemble\EloquentSearch\EloquentSearchServiceProvider::class,
```

Add this line to your `aliases` array:
``` php
'EloquentSearch' => Assemble\EloquentSearch\Facades\EloquentSearcher::class,
```

You will need to run `php artisan vendor:publish` to publish the config file to your instalation,
Once run, you can find it in `config/eloquenet_search.php`.
This config file is used to controll which models are used to search/return entities of.

