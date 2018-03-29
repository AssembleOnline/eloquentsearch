<?php

namespace Assemble\EloquentSearch\Concerns;

use Closure;
use Illuminate\Database\Query\Expression;
use DB;

trait JoinsToModel
{
    

    private function joinsToModel() {
        return function($relation, $from) {

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
                        $FK = str_replace($table.'.', $hash.'.', $FK);
                    // }

                    $PK = $relation->getQualifiedOwnerKeyName();
                break;
                case  'Illuminate\Database\Eloquent\Relations\HasOne':
                    // For HasOneOrMany
                    $query = $relation->getQuery()->getQuery();
                    $hash = $relation->getRelationCountHash();
                    $table = $related->getTable();
                    // dd(get_class_methods($relation));
                    $FK = $relation->getQualifiedForeignKeyName();

                    // handle self relations
                    // if(get_class($relation->getParent()) === get_class($relation->getRelated())) {
                        $query->from($table.' as '.$hash);
                        $related->setTable($hash);
                        $FK = str_replace($table.'.', $hash.'.', $FK);
                    // }
                    $PK = $relation->getQualifiedParentKeyName();
                break;
                // case  'Illuminate\Database\Eloquent\Relations\BelongsToMany':
                //     // For BelongsToMany
                //     $query = $relation->getQuery()->getQuery();
                //     $hash = $relation->getRelationCountHash();
                //     $table = $relation->getTable();

                //     $FK = $relation->getQualifiedForeignPivotKeyName();

                //     // pivot

                //     $PK = $relation->getQualifiedParentKeyName();
                //     $query->from($table);

                //     // handle self relations
                //     // if(get_class($relation->getParent()) === get_class($relation->getRelated())) {
                //         $query->from($table.' as '.$hash);
                //         $related->setTable($hash);
                //         $FK = str_replace($table, $hash, $FK);
                //     // }

                // break;
                default:
                    throw new \Exception("joinsToModel can only be called on belongsTo and hasOne relations.");
            }

            $this->leftJoin($query->from, $PK, '=', $FK);
            $this->priorJoinToModelParents[$table] = $hash;

            return $this;
        };
    } 

    
}
