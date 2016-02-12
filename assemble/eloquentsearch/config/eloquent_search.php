<?php
/** 
* Config file to store the requestable entities for searching functionality.
* This config maps the item call to their respective class instance to be used in constructing the query.
 **/
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