<?php

namespace Assemble\EloquentSearch\Concerns;

use Closure;
use Illuminate\Database\Query\Expression;
use DB;

trait JoinsToModel
{
    

    private function joinsToModel() {
        return function($relation, $from, $dd = false) {

            /**
             * Wrap the attributes of the give JSON path.
             *
             * @param  array  $path
             * @return array
             */
            $wrapAttributes = function($path) {
                $arr = array_map(function ($attribute) {
                    return '"'.$attribute.'"';
                }, ( is_array($path) ? $path : explode('.', $path) ));
                return ( is_array($path) ? $arr : implode('.', $arr) );
            };

            if($from != null) {
                $from = (new $from)->newQuery();
            } else {
                $from = $this;
            }
            $relation = $from->getRelationWithoutConstraints($relation);
            

            if($dd) {
                // dd($this);
            }

            $parent = $relation->getParent();
            if(property_exists($this, 'priorJoinToModelParents')) {
                if(array_key_exists($parent->getTable(), $this->priorJoinToModelParents)) {
                    $parent->setTable($this->priorJoinToModelParents[$parent->getTable()]);
                }
            } else {
                $this->priorJoinToModelParents = [];
            }
            $related = $relation->getRelated();
            switch(get_class($relation)) {
                case  'Illuminate\Database\Eloquent\Relations\BelongsToMany':
                    // For BelongsToMany
                    $query = $relation->getQuery()->getQuery();
                    $hash = $relation->getRelationCountHash();
                    $table = $relation->getTable();
                    $FK = $relation->getQualifiedForeignPivotKeyName();
                    $query->from($table);

                    // handle self relations
                    // if(get_class($relation->getParent()) === get_class($relation->getRelated())) {
                        $query->from($table.' as '.$hash);
                        $related->setTable($hash);
                        $FK = str_replace($table, $hash, $FK);
                    // }

                break;
                case  'Illuminate\Database\Eloquent\Relations\BelongsTo':
                    // For HasOneOrMany
                    $query = $relation->getQuery()->getQuery();
                    $hash = $relation->getRelationCountHash();
                    $table = $related->getTable();
                    $FK = $relation->getQualifiedForeignKey();

                    // handle self relations
                    // if(get_class($relation->getParent()) === get_class($relation->getRelated())) {
                        $query->from($table.' as '.$hash);
                        $related->setTable($hash);
                        $FK = str_replace($table, $hash, $FK);
                    // }

                break;
                default:
                    // For HasOneOrMany
                    $query = $relation->getQuery()->getQuery();
                    $hash = $relation->getRelationCountHash();
                    $table = $related->getTable();
                    $FK = $relation->getQualifiedForeignKeyName();

                    // handle self relations
                    // if(get_class($relation->getParent()) === get_class($relation->getRelated())) {
                        $query->from($table.' as '.$hash);
                        $related->setTable($hash);
                        $FK = str_replace($table, $hash, $FK);
                    // }
            }

            $this->leftJoin($query->from, $relation->getQualifiedOwnerKeyName(), '=', $FK);
            $this->priorJoinToModelParents[$table] = $hash;

            // $this->groupBy($relation->getQualifiedParentKeyName());

            // if(empty($this->getQuery()->orders)) {
            //     $this->orderBy($relation->getQualifiedParentKeyName(), "asc"); // apply default order
            // }

            // dd($this->toSql());
            return $this;
        };
    } 

    
}
