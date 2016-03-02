# Eloquent Model Searcher for Laravel 5

## Installation

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


## Configuration

The config file can be found in the laravel config folder at `config/eloquent_search.php`,
here you can define the classes related to your models as below.
``` php
return [
	'search_models' => [
		/* 
			Add your searchable eloquent models here, this is an example of user model usage.

			If you have your models sitting in the app root as default, something like this should find them.
			
				'user' => User::class,
	    	
	    	Otherwise if you have them elsewhere or the application cant seem to find them, try prefix them as such.
	    
	    		'user' => App\Models\User::class,

	    */
	   	
	    'user' => User::class,

	]
];
```


### Additional Feature:

Implement the method 'isSearchable' in your models for the searcher to determine if it is allowed to search/return that model.
``` php
public function isSearchable(){
	// Do your checks to determine if the model may be searched by the user
	return true;
}
```
This feature lets you restrict user searches to only the models they are allowed to see.


