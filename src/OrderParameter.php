<?php

namespace plokko\ResourceQuery;

/**
 * Class OrderParameter
 * @package App\ResourceQuery
 * @property-read string $name
 * @property-read string $field
 * @property-read string $defaultOrder
 */
class OrderParameter
{
    private $name,
        $field,
        $direction = null,
        $defaultOrder = 'asc',
        $use_default = false;

    function __construct($name, $field = null, $order = null)
    {
        $this->name = $name;
        $this->field = $field;
        $this->direction = $order;
    }

    function __get($name)
    {
        switch ($name) {
            case 'field':
            case 'name':
            case 'order':
                return $this->$name;
            default:
                return null;
        }
    }

    /**
     * @param string|callable $name Table field to use or a callback to sort
     * @return $this
     */
    public function field($name): OrderParameter
    {
        $this->field = $name;
        return $this;
    }

    /**
     * @param string $order 'asc' or 'desc'
     * @return $this
     */
    public function defaultOrder($order): OrderParameter
    {
        $this->defaultOrder = $order == 'desc' ? 'desc' : 'asc';
        return $this;
    }

    /**
     * @param string|null $direction 'asc', 'desc' or null if
     * @return $this
     */
    public function direction($direction): OrderParameter
    {
        $this->direction = $direction;
        return $this;
    }

    function apply($query, $dir)
    {
        $direction = ($dir === 'asc' || $dir === 'desc') ?
            $dir :
            $this->defaultOrder;
        //Force specified direction
        if ($this->direction) {
            $direction = $this->direction;
        }

        if ($this->shouldApply()) {
            if (is_callable($this->field)) {
                //callback implementation
                ($this->field)($query, $direction);
                return [$this->name, $direction];
            } else {
                $query->orderBy($this->field, $direction);
                return [$this->name, $direction];
            }
        }
        return null;
    }

    protected function shouldApply()
    {
        //TODO
        return true;
    }
}
