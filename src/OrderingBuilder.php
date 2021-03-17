<?php

namespace plokko\ResourceQuery;

/**
 * ResourceQuery helper class for building and managing sorting
 * @package plokko\ResourceQuery
 */
class OrderingBuilder implements \ArrayAccess
{
    private
        /**@var OrderParameter[] Defined sorting conditions with field_name as key*/
        $parameters = [];

    /** @var string Query parameter used for ordering */
    public $orderField='order_by';

    /** @var ResourceQuery*/
    private $parent;

    function __construct($parent){
        $this->parent = $parent;
    }

    /**
     * Set query parameter used for ordering
     * @param null|string $param
     * @return $this
     */
    public function setOrderParameter($param=null){
        $this->orderField = $param;
        return $this;
    }


    /**
     * Remove a filter condition by name
     * @param string $name
     */
    function remove($name)
    {
        unset($this->parameters[$name]);
    }

    /**
     * Remove all ordering conditions
     */
    function removeAll()
    {
        $this->parameters = [];
    }

    function __get($offset)
    {
        return $this->add($offset);
    }

    /**
     * Called as name([field][,direction]), ex: ->my_field('=','field')
     * @param $name
     * @param $arguments
     * @return OrderParameter
     */
    function __call($name, $arguments): OrderParameter
    {
        return $this->add(
            $name,
            optional($arguments[0]),
            optional($arguments[1])
        );
    }

    /**
     * Add or updates a sorting field
     * @param string $name
     * @param string|null $field
     * @return OrderParameter
     */
    function add($name, $field = null, $direction = null): OrderParameter
    {
        if(!isset($this->parameters[$name])){
            $this->parameters[$name] = new OrderParameter($name,$this->parent);
        }
        if ($field !== null)
            $this->parameters[$name]->field($field);
        if ($direction !== null)
            $this->parameters[$name]->direction($field);
        return $this->parameters[$name];
    }

    /**
     * Add or replaces a sorting field
     * @param string $name
     * @param string|null $field
     * @return OrderParameter
     */
    function set($name, $field = null, $direction = null): OrderParameter
    {
        $cnd = new OrderParameter($name,$this->parent);
        if ($field !== null)
            $cnd->field($field);
        if ($direction !== null)
            $cnd->direction($field);
        $this->parameters[$name] = $cnd;
        return $cnd;
    }


    public function offsetExists($offset)
    {
        return isset($this->parameters[$offset]);
    }

    public function offsetGet($offset)
    {
        return empty($this->parameters[$offset]) ?
            $this->add($offset)
            : $this->parameters[$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new \BadFunctionCallException("Ordering conditions are read only");
    }

    public function offsetUnset($offset)
    {
        unset($this->parameters[$offset]);
    }

    /**
     * Get default sorting conditions
     * @internal
     * @return OrderParameter[] Sorting conditions applied by default
     */
    protected function getDefaultSortingParameters(){
        return array_filter($this->parameters,function(OrderParameter $e){ return $e->default!==false;});
    }

    /**
     * Apply sorting conditions to the query
     * @internal
     * @param $query
     * @param array $orderData
     * @param array $appliedOrdering
     */
    public function applyConditions($query, array $orderData, array &$appliedOrdering = [])
    {
        //Filter order data with defined conditions
        $orders = array_filter($orderData,function ($order){
            $field = $order[0];
            return isset($this->parameters[$field]);
        });

        if(count($orders)==0){
            //Apply default conditions
            $defaultCnds = $this->getDefaultSortingParameters();
            foreach($defaultCnds AS $cnd){
                $applied = $cnd->apply($query,null,true);
                if ($applied) {
                    $appliedOrdering[] = $applied;
                }
            }
        }else{
            //apply conditions
            foreach($orders AS $order){
                $field = $order[0];
                $dir = $order[1];

                $applied = $this->parameters[$field]->apply($query, $dir);
                if ($applied) {
                    $appliedOrdering[] = $applied;
                }
            }
        }
    }
}
