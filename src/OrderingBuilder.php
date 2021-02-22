<?php

namespace plokko\ResourceQuery;

class OrderingBuilder implements \ArrayAccess
{
    private
        /**@var OrderParameter[] */
        $parameters = [],
        /** @var array|null */
        $defaultOrder = null;

    /** @var string Query parameter used for ordering */
    public $orderField='order_by';

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
     * @param array|null $defaultOrder
     */
    function defaultOrder($defaultOrder)
    {
        $this->defaultOrder = $defaultOrder;
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
     * @param string $name
     * @param string|null $field
     * @return OrderParameter
     */
    function add($name, $field = null, $direction = null): OrderParameter
    {
        if(!isset($this->parameters[$name])){
            $this->parameters[$name] = new OrderParameter($name);
        }
        if ($field !== null)
            $this->parameters[$name]->field($field);
        if ($direction !== null)
            $this->parameters[$name]->direction($field);
        return $this->parameters[$name];
    }

    /**
     * @param string $name
     * @param string|null $field
     * @return OrderParameter
     */
    function set($name, $field = null, $direction = null): OrderParameter
    {
        $cnd = new OrderParameter($name);
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


    public function applyConditions($query, array $orderData, array &$appliedOrdering = [])
    {
        foreach ([$orderData, $this->defaultOrder] as $i => $data) {
            // Default order
            if ($i > 0) {
                if (!$data || count($appliedOrdering) > 0) {
                    break;
                }
            }
            //
            foreach ($data as $order) {
                $field = $dir = null;
                if (is_array($order)) {
                    if (!isset($order[0]))
                        continue;
                    $field = $order[0];
                    if (isset($order[1]))
                        $dir = $order[1];
                } else {
                    $startsWith = substr($order, 0, 1);
                    if ($startsWith == '+' || $startsWith == '-') {
                        $field = substr($order, 1);
                        $dir = $startsWith == '+' ? 'asc' : 'desc';
                    } else {
                        $field = $order;
                    }
                }
                ///
                if (isset($this->parameters[$field])) {
                    $applied = $this->parameters[$field]->apply($query, $dir);
                    if ($applied) {
                        $appliedOrdering[] = $applied;
                    }
                }
            }
        }
    }
}
