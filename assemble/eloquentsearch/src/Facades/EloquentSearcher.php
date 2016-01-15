<?php

namespace Assemble\EloquentSearch\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * This is the authorizer facade class.
 *
 * @author Alex Blake <alex@assemble.co.za>
 */
class EloquentSearcher extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'eloquentsearch.searcher';
    }
}
