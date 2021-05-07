<?php
namespace plokko\ResourceQuery\Traits;

use plokko\ResourceQuery\ResourceQuery;
use  plokko\ResourceQuery\OrderParameter;
use  plokko\ResourceQuery\FilterCondition;

trait ResourceQueryFallbackTrait
{
    /**
     * @var ResourceQuery $parent
     */

    /**
     * Add a new filter or update an existing one
     * @param string $name
     * @param callable|string $condition
     * @param null $field
     * @return FilterCondition
     */
    function filter($name, $condition = null, $field = null): FilterCondition
    {
        return $this->parent->filter($name,$condition,$field);
    }

    /**
     * Add or updates a sorting setting
     * @param string $name
     * @param string|null $field
     * @return OrderParameter
     */
    function orderBy($name, $field = null, $direction = null): OrderParameter
    {
        return $this->parent->orderBy($name,$field,$direction);
    }

    function __call($fn,$args){
        if($fn === 'add' ||$fn === 'set'){
            return call_user_func_array([(($this instanceof OrderParameter)?$this->parent->orderBy:$this->parent->filters), $fn],$args);
        }
        return call_user_func_array([$this->parent,$fn],$args);
    }
}
