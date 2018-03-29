<?php

namespace Assemble\EloquentSearch;

use Validator;
use Config;
use Illuminate\Support\MessageBag;
use Log;

/**
 * This is the searcher class.
 *
 * @author Alex Blake <alex@assemble.co.za>
 */
class Searcher
{
    /**
     * The class entities in the system.
     */
    private $_ENTITIES;
    private $subJoined = false;

    /**
     * Create a new Searcher instance.
     */
    public function __construct()
    {
        $this->_ENTITIES = Config::get('eloquent_search.search_models');
    }


    /**
     * Accessor to retrieve the class entitiy to avoid direct connection.
     *
     * @param String $class_ref
     * @return \Class
     */
    private function getEntityClass($class_ref){
        return (array_key_exists($class_ref, $this->_ENTITIES) ? $this->_ENTITIES[$class_ref] : null );
    }


    /**
     * method to return json list of entities registered.
     *
     * @return \Illuminate\Http\Response
     */
    public function listEntities()
    {
        return array_keys($this->_ENTITIES);
    }

    /**
     * Field / Relation Searchable On model
     *
     * @return \Illuminate\Http\Response
     */
    public function searchableItem($model, $value)
    {
        if(!property_exists($model, 'searchable')) {
            return false;
        }
        foreach($model->searchable as $key => $val) {
            // simple searchable = true
            if(is_string($val)) {
                if($val == $value) {
                    return true;
                }
            }
            // @TODO: Add callable specific to relation perhaps?
            // if(is_callable($val)) {
            //     if($key == $value) {
            //         return false;
            //     }
            // }
        }
        return false;
    }

    /**
     * generate the error messages from the input
     *
     * @return Array
     */
    private function genMessages($search){

        $items = $search;

        if(isset($items) && !empty($items))
        foreach($items as $key => $value)
        {
            $messages[$key.'.entity.required'] = 'Entity at #'.$key.' Must be present';
            $messages[$key.'.entity.in'] = 'Entity at #'.$key.' is not a valid entity reference';

            if(isset($value['criteria']) && !empty($value['criteria']))
            foreach($value['criteria'] as $key2 => $sub)
            {
                if(isset($sub['field']))
                {   
                    $messages[$key.'.criteria.'.$key2.'.field'] = 'Criteria List at #'.$key.' is missing a requirement: field';  
                }
                else
                {   
                    $messages[$key.'.criteria.'.$key2.'.0'] = 'Criteria List at #'.$key.' is missing a requirement: field';  
                }

                
                if(isset($sub['where']))
                {   
                    $messages[$key.'.criteria.'.$key2.'.where'] = 'Criteria List at #'.$key.' is missing a requirement: where';  
                }
                else
                {   
                    $messages[$key.'.criteria.'.$key2.'.1'] = 'Criteria List at #'.$key.' is missing a requirement: where';  
                }


                if(isset($sub['value']))
                {   
                    $messages[$key.'.criteria.'.$key2.'.value'] = 'Criteria List at #'.$key.' is missing a requirement: value';  
                }
                else
                {   
                    $messages[$key.'.criteria.'.$key2.'.2'] = 'Criteria List at #'.$key.' is missing a requirement: value';  
                }
            }
        }
        return $messages;
    }

    /**
     * generate the input rules based on the input sent through.
     *
     * @return Array
     */
    private function genRules($search) {
        $items = $search;


        if(isset($items) && !empty($items))
        foreach($items as $key => $value)
        {
            $list_entities = implode(',',array_keys($this->_ENTITIES));
            $rules[$key.'.entity'] = 'required|in:'.$list_entities;


            if(isset($value['criteria']) && !empty($value['criteria']))
            foreach($value['criteria'] as $key2 => $sub)
            {
                if(isset($sub['field']))
                {   
                    $rules[$key.'.criteria.'.$key2.'.field'] = 'required';  
                }
                else
                {   
                    $rules[$key.'.criteria.'.$key2.'.0'] = 'required';  
                }

                
                if(isset($sub['where']))
                {   
                    $rules[$key.'.criteria.'.$key2.'.where'] = 'required';  
                }
                else
                {   
                    $rules[$key.'.criteria.'.$key2.'.1'] = 'required';  
                }


                if(isset($sub['value']))
                {   
                    $rules[$key.'.criteria.'.$key2.'.value'] = 'required';  
                }
                else
                {   
                    $rules[$key.'.criteria.'.$key2.'.2'] = 'required';  
                }
                
            }
        }
        return $rules;
    }

    /**
     * Field / Relation Searchable On model
     *
     * @param String $query reference
     * @param String $entity initial entity key
     * @param Array $order order relations
     * @param String $orderField field to order by on final relation
     * @param String $orderDir Direction of ordering [ASC,DESC]
     * @return \Illuminate\Http\Response
     */
    private function orderQuery(&$query, $entity, $order, $orderField, $orderDir) {
        if(count($order) > 0)
        {
            
            $ent = $this->getEntityClass($entity);
            $ent = new $ent;
            foreach($order as $key => $curOrder)
            {
                if(!$this->searchableItem($ent, $curOrder)) {
                    return new MessageBag([
                        'code' => 401,
                        'messages' => [
                            //@TODO: lets add more clarity here later, for now this will do.
                            'You do not have permission to view this resource.' 
                        ]
                    ]);
                }
                $rel = call_user_func([$ent, $curOrder]);
                $rel = get_class($rel->getRelated());
                $query = $query->joinsToModel($curOrder, get_class($ent));
                $ent = new $rel;
            }
            $query = $query->orderBy($query->priorJoinToModelParents[$ent->getTable()].'.'.$orderField, $orderDir);
        }
        elseif(isset($orderField) && isset($orderDir))
        {
            $query = $query->orderBy($orderField, $orderDir);
        }
    }

    /**
     * SearchPattern interpreter that will search recurseively through model relations as defined.
     *
     * @var Array
     * @return \Illuminate\Database\Query\Builder[]|array
     */
    public function getSearch($search, $orderBy = null, $orderAs = null)
    {
        //check if order set properly
        if( ( $orderBy == null || empty($orderBy) ) || ( $orderAs == null || empty($orderAs) ) )
        {
            $order = null;
            $orderDir = null;
            $orderField = null;
            $orderLast = null;
        }
        else
        {
            $orderDir = $orderAs;
            $order = explode('.', $orderBy);
            $orderField = array_pop($order);
            $orderLast = end($order);
        }

        if(!isset($search) || empty($search))
        {
            return new MessageBag([
                    'messages' => [
                        //@TODO: lets add more clarity here later, for now this will do.
                        'No search parameters set.' 
                    ]
                ]);
        }

        $validator = Validator::make($search, $this->genRules($search),$this->genMessages($search));

        //break if there are problems with the input
        if($validator->fails())
        {
            return $validator->errors();
        }

        //Right, we need know theres items in search now, then its time to use them.
        if(is_array($search) && count($search) >= 1)
        $results = array();
        foreach($search as $search_item){

            //grab the values for entity and criteria, nothing there? then its a null to have a break on in a moment.
            $entity = ( isset($search_item['entity']) ? $search_item['entity'] : null );
            $criteria = ( isset($search_item['criteria']) ? $search_item['criteria'] : null );

            //break on missing values, see i told you we were going to take a break
            if($entity == null /*|| $criteria == null*/)
            {
                return new MessageBag([
                        'messages' => [
                            //@TODO: lets add more clarity here later, for now this will do.
                            'One or more of your search parameters is empty.' 
                        ]
                    ]);
            }

            $used_entity = $this->getEntityClass($entity);

            if($used_entity == null)
            {
                return new MessageBag([
                        'messages' => [
                            //@TODO: lets add more clarity here later, for now this will do.
                            'Entity does not exist.' 
                        ]
                    ]);
            }


            $query = (new $used_entity); //create new instance of the element, we need something to query on, so here it is

            if(!(method_exists($query, 'isSearchable') && $query->isSearchable()))
            {
                return new MessageBag([
                    'code' => 401,
                    'messages' => [
                        //@TODO: lets add more clarity here later, for now this will do.
                        'You do not have permission to view this resource.' 
                    ]
                ]);
            }

            //Ok, not broken yet? good, lets continue and start building our query. First we just need to make sure theres something there.
            $first = true;
            if(is_array($criteria) && count($criteria) >= 1)
            foreach($criteria as $criteria_item) {

                $field = (isset($criteria_item['field']) ? 
                                $criteria_item['field'] : 
                                (isset($criteria_item[0]) ?
                                        $criteria_item[0] :
                                        null));

                $where = (isset($criteria_item['where']) ? 
                                $criteria_item['where'] : 
                                (isset($criteria_item[1]) ?
                                        $criteria_item[1] :
                                        null));

                $value = (isset($criteria_item['value']) ? 
                                $criteria_item['value'] : 
                                (isset($criteria_item[2]) ?
                                        $criteria_item[2] :
                                        ''));
                $isOr = (isset($criteria_item['or']) ? 
                            $criteria_item['or'] : 
                            ( isset($criteria_item[3]) ? true : false ) 
                        );
                
                if($first){ $isOr = false;$first = false; }//overwrite OR if first element, cannot OR an initial search query param  
                              

                if($field == null || $where == null)
                {//break from nulls.
                    return new MessageBag([
                            'messages' => [
                                //@TODO: lets add more clarity here later, for now this will do.
                                'Cannot have blank criteria attributes' 
                            ]
                        ]);
                }


                if(strpos($field, '.') === false)
                {
                    //Nothing special go about your business...
                    if($where == 'in')
                    {
                        $value = (is_array($value) ? $value : explode(',', $value));
                        $query = $query->whereIn($field, $value);
                    }
                    else
                    {
                        if($isOr == true)
                        {
                            $query = $query->orWhere($field, $where, $value);
                        }
                        else
                        {
                            $query = $query->where($field, $where, $value);
                        }
                        
                    }
                }
                else
                {
                    //here is where the fun starts... relations...
                    //first, split the sting...
                    $relations = explode('.', $field);
                    $field = array_pop($relations);
                    $last = end($relations);

                    

                    $query = $this->whereHasBuilder($query, $relations, $field, $where, $value, $last, null, $isOr);

                }

                
            }
            
            // Order the query as stated
            $this->orderQuery($query, $entity, $order, $orderField, $orderDir);
            
            // Need to ensure the select statement is at least table-prefixed
            // @TODO: needs more testing as to why it returns incorrect rows when not prefixed... since tables are being aliased.
            $query = $query->selectRaw($query->getModel()->getTable().'.*');

            $results = $query;
        }

        if(!empty($results))
        {
            return $results;
        }
        else
        {
            return new MessageBag([
                'messages' => [
                    //@TODO: lets add more clarity here later, for now this will do.
                    'Search parameters do not exist' 
                ]
            ]);
        }

    }

    /**
     * SearchPattern interpreter that will search recurseively through model relations as defined.
     *
     * @var #Ref::\Illuminate\Database\Query\Builder
     * @var #Ref::array
     * @var #Ref::String
     * @var #Ref::String
     * @var #Ref::String
     * @var #Ref::String
     *
     * @return \Illuminate\Database\Query\Builder[]|array
     */
    private function whereHasBuilder(&$query, &$relations, &$field, &$where, &$value, &$last, $lastHashToModel, &$isOr)
    {
        //grab the relations parsed
        $rel = array_shift($relations);

        $runComplex = true;
        if(method_exists($rel, 'isSearchable'))
        {
            $runComplex = $rel->isSearchable();
        }
        //return a built query segment based on the criteria sent through
        if($runComplex)
        {
            if($isOr == true)
            {
                $query = $query->orWhereHas($rel, 
                    function ($inner) use($query, $field, $where, $value, $rel, $relations, $last, $lastHashToModel, &$isOr) {
                        // Get a hash for the sub table names
                        $hashtomodel = $this->hashTableNames(( $lastHashToModel ? $lastHashToModel['model'] : $query ), $inner, $rel, ( $lastHashToModel['table'] ? $lastHashToModel['table'] : null ));
                        //make sure that the relation is not the last one in the list.
                        if($rel == $last) {
                            /**
                            *   !IMPORTANT
                            *   Ensure that the field entered is not in the list of hidden fields on the model, 
                            *   this ensures that the hidden fields are not searchable, since that would open it up to brute force attempts.
                            */
                            if(!in_array($field, $inner->getModel()->getHidden()))
                            {
                                //if the condition is an 'IN' statement, there needs to be a seperate syntax so we catch it here
                                if($where == 'in')
                                { 
                                    $value = (is_array($value) ? $value : explode(',', $value));
                                    $inner->whereIn($hashtomodel['hash'].'.'.$field, $value);
                                }
                                else
                                {
                                    $inner->where($hashtomodel['hash'].'.'.$field, $where, $value);
                                }
                            }
                        }
                        else
                        {
                            $this->whereHasBuilder($inner, $relations, $field, $where, $value, $last, $hashtomodel, $isOr);
                        }
                    }
                );
            }
            else
            {
                $query = $query->whereHas($rel, 
                    function ($inner) use($query, $field, $where, $value, $rel, $relations, $last, $lastHashToModel, &$isOr) {
                        // Get a hash for the sub table names
                        $hashtomodel = $this->hashTableNames(( $lastHashToModel ? $lastHashToModel['model'] : $query ), $inner, $rel, ( $lastHashToModel['table'] ? $lastHashToModel['table'] : null ));
                        //make sure that the relation is not the last one in the list.
                        if($rel == $last) {
                            /**
                            *   !IMPORTANT
                            *   Ensure that the field entered is not in the list of hidden fields on the model, 
                            *   this ensures that the hidden fields are not searchable, since that would open it up to brute force attempts.
                            */
                            if(!in_array($field, $inner->getModel()->getHidden()))
                            {
                                //if the condition is an 'IN' statement, there needs to be a seperate syntax so we catch it here
                                if($where == 'in')
                                { 
                                    $value = (is_array($value) ? $value : explode(',', $value));
                                    $inner->whereIn($hashtomodel['table'].'.'.$field, $value);
                                }
                                else
                                {
                                    $inner->where($hashtomodel['table'].'.'.$field, $where, $value);
                                }
                            }
                        }
                        else
                        {
                            $this->whereHasBuilder($inner, $relations, $field, $where, $value, $last, $hashtomodel, $isOr);
                        }
                    }
                );
            }
            
        }
        return $query;
    }


    private function hashTableNames($model, &$inner, $rel, $priorTable = null) {
        $relation = \Illuminate\Database\Eloquent\Relations\Relation::noConstraints(function () use ($model, $rel) {
            return $model->{$rel}();
        });
        $hash = $this->getWhereHasCountHash();
        $related = $relation->getRelated();
        $table = $related->getTable();
        $related->el_table_hash = $hash;

        $inner->from($table.' as '.$hash);
        array_walk($inner->getQuery()->wheres, function(&$item) use ($table, $hash, $model, $priorTable) {
            $item['first'] = str_replace($table.'.', $hash.'.', $item['first']);
            $item['second'] = str_replace($table.'.', $hash.'.', $item['second']);
            if($priorTable) {
                $item['first'] = str_replace($model->getTable().'.', $priorTable.'.', $item['first']);
                $item['second'] = str_replace($model->getTable().'.', $priorTable.'.', $item['second']);
            }
        });

        return [
            'table' => $hash,
            'model' => $related
        ];
    }

    protected $whereHasHashCount = 0;
    protected function getWhereHasCountHash() {
        return "eloquent_builder_wherehas_".$this->whereHasHashCount++;
    }

}
