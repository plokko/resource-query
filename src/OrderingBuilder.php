<?php

namespace plokko\ResourceQuery;

class OrderingBuilder implements \ArrayAccess
{
    private
        /**@var OrderParameter[] */
        $parameters = [],
        /** @var array|null */
        $defaultOrder = null;


    /**
     * @param array|null $defaultOrder
     */
    function defaultOrder($defaultOrder)
    {
        $this->defaultOrder = $defaultOrder;
    }

    function remove($name)
    {
        unset($this->parameters[$name]);
    }

    function removeAll()
    {
        $this->parameters = [];
    }

    function __get($offset)
    {
        return empty($this->parameters[$offset]) ?
            $this->add($offset)
            : $this->parameters[$offset];
    }

    /**
     * @param string $name
     * @param string|null $field
     * @return OrderParameter
     */
    function add($name, $field = null, $direction = null): OrderParameter
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
