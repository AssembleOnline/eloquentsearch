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
     * @var String
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
            // $messages[$key.'.criteria.required'] = 'Criteria List at #'.$key.' Must be present';
            // $messages[$key.'.criteria.0.required'] = 'Criteria List at #'.$key.' Must have at least 1 constructed criteria';

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
            // $rules[$key.'.criteria'] = 'required';
            // $rules[$key.'.criteria.0'] = 'required';


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


            $query = (new $used_entity);//create new instance of the element, we need something to query on, so here it is

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

                    


                    $query = $this->whereHasBuilder($query, $relations, $field, $where, $value, $last, $order, $orderField, $orderLast, $orderDir, $isOr);

                }

                
            }

            // $final = $query->get()->each(function($item) use($entity){
            //     $item['entity'] = $entity;
            // });

            // if(empty($results))
            // {
            //     $results = $final;
            // }
            // else
            // {
            //     $final->each(function($item) use($results){
            //         $results->add($item);
            //     });
            // }
            
            if(!$this->subJoined){

                
                // if(count($order) > 0)
                // {
                //     $last_order = null;
                //     foreach($order as $curOrder)
                //     {
                //         if($last_order == null){$last_order = $curOrder;}
                //         $ent_key = $this->getEntityClass($curOrder);

                //         if($ent_key == null)
                //         {
                //             return new MessageBag([
                //                     'messages' => [
                //                         //@TODO: lets add more clarity here later, for now this will do.
                //                         'Entity does not exist: '.$curOrder 
                //                     ]
                //                 ]);
                //         }
                //         $ent = (new $ent_key);
                //         $query = $query->join($ent->getTable(), $query->getModel()->getTable().'.'.$ent->getTable().'_id', '=', $ent->getTable().'.id')->orderBy($ent->getTable().'.'.$orderField, $orderDir);
                //         $last_order = $curOrder;
                //     }
                // }
                // elseif(isset($orderField) && isset($orderDir))
                // {
                //     $query = $query->orderBy($orderField, $orderDir);
                // }
                // $this->subJoined = false;


                // order by query builder
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
                        $query = $query->joinsToModel($curOrder, get_class($ent), ($key == 1));
                        $ent = new $rel;
                    }
                    $query = $query->orderBy($query->priorJoinToModelParents[$ent->getTable()].'.'.$orderField, $orderDir);
                }
                elseif(isset($orderField) && isset($orderDir))
                {
                    $query = $query->orderBy($orderField, $orderDir);
                }
            }

            $results = $query;
        }

        if(!empty($results))
        {
            // print_r($results->toSql());die;
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
    private function whereHasBuilder(&$query, &$relations, &$field, &$where, &$value, &$last, &$order, &$orderField, &$orderLast, &$orderDir, &$isOr)
    {
        //grab the relations parsed
        $rel = array_shift($relations);

        // $curOrder = null;
        // if(count($order) > 0 && $rel == $order[0])
        // {
        //     $curOrder = array_shift($order);
        // }

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
                    function ($inner) use($field, $where, $value, $rel, $relations, $last, $curOrder, $order, &$orderField, $orderLast, $orderDir, &$isOr) {
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
                                    $inner->whereIn($inner->getModel()->getTable().'.'.$field, $value);
                                }
                                else
                                {
                                    $inner->where($inner->getModel()->getTable().'.'.$field, $where, $value);
                                }
                            }
                        }
                        else
                        {
                            $this->whereHasBuilder($inner, $relations, $field, $where, $value, $last, $order, $orderField, $orderLast, $orderDir, $isOr);
                        }
                    }
                );
            }
            else
            {
                $query = $query->whereHas($rel, 
                    function ($inner) use($field, $where, $value, $rel, $relations, $last, $curOrder, $order, &$orderField, $orderLast, $orderDir, &$isOr) {
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
                                    $inner->whereIn($inner->getModel()->getTable().'.'.$field, $value);
                                }
                                else
                                {
                                    $inner->where($inner->getModel()->getTable().'.'.$field, $where, $value);
                                }
                            }
                        }
                        else
                        {
                            $this->whereHasBuilder($inner, $relations, $field, $where, $value, $last, $order, $orderField, $orderLast, $orderDir, $isOr);
                        }
                    }
                );
            }
            
        }

        /*
        * This is a workaround for ordering a result set by an associated value in another table, 
        * since the way we construct the query is not compatible with an orderby sub table, that needs a join, 
        * so we join here based on the standardisation assumption... 
        * which is needing to me migrated to configurations within models and use this as a fallback.
        */
        // if(!empty($curOrder)){
        //     $ent_key = $this->getEntityClass($curOrder);

        //     if($ent_key == null)
        //     {
        //         return new MessageBag([
        //                 'messages' => [
        //                     //@TODO: lets add more clarity here later, for now this will do.
        //                     'Entity does not exist: '.$curOrder 
        //                 ]
        //             ]);
        //     }
        //     $ent = (new $ent_key);
        //     $query = $query->join($ent->getTable(), $query->getModel()->getTable().'.'.$ent->getTable().'_id', '=', $ent->getTable().'.id')->orderBy($ent->getTable().'.'.$orderField, $orderDir);
        //     $this->subJoined = true;
        // }
        return $query;
    }

}
