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
    $parameters = [],
    /** @var null|string[] */
    $defaultOrder = null;

    /** @var string Query parameter used for ordering */
    public $orderField = 'order_by';

    /** @var ResourceQuery*/
    private $parent;

    function __construct($parent)
    {
        $this->parent = $parent;
    }

    /**
     * Set query parameter used for ordering
     * @param null|string $param
     * @return $this
     */
    public function setOrderParameter($param = null)
    {
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
        if (!isset($this->parameters[$name])) {
            $this->parameters[$name] = new OrderParameter($name, $this->parent);
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
        $cnd = new OrderParameter($name, $this->parent);
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
        return !isset($this->parameters[$offset]) ?
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
     * Set default order (applied if no order parameter are specified in the query)
     * @param array|null $order
     * @return $this
     */
    public function setDefaultOrder(array $order = null)
    {
        $this->defaultOrder = $order;
        return $this;
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
        $orders = array_filter($orderData, function ($order) {
            $field = $order[0];
            return isset($this->parameters[$field]);
        });

        if (count($orders) == 0) {
            //Apply default conditions
            if ($this->defaultOrder) {
                //Apply default sort

                foreach ($this->defaultOrder as $k => $v) {
                    $field = $v;
                    $direction = 'asc';
                    if (!is_int($k)) {
                        $field = $k;
                        $direction = $v === 'desc' ? 'desc' : 'asc';
                    }
                    $param = $this->parameters[$field];
                    /** @var OrderParameter $param */

                    $applied = $param->apply($query, $direction, true);
                    if ($applied) {
                        $appliedOrdering[] = $applied;
                    }
                }
            }

        } else {
            //apply conditions
            foreach ($orders as $order) {
                $field = $order[0];
                $dir = $order[1] ?? 'asc';

                $applied = $this->parameters[$field]->apply($query, $dir);
                if ($applied) {
                    $appliedOrdering[] = $applied;
                }
            }
        }
    }
}
