<?php
namespace plokko\ResourceQuery;

/**
 * Class OrderParameter
 * @package plokko\ResourceQuery
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
        $default = false,
        /** @var OrderingBuilder */
        $parent;

    function __construct($name, OrderingBuilder $parent)
    {
        $this->name = $name;
        $this->field = $name;
        $this->parent = $parent;
        $this->direction = null;
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
     * Set as default sorting
     * @param bool $default
     * @return $this
     */
    function default($default=true){
        $this->default=$default;
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


    /**
     * @param string $name
     * @param string|null $field
     * @return OrderParameter
     */
    function add($name, $field = null, $direction = null): OrderParameter
    {
        return $this->parent->add($name,$field,$direction);
    }

    /**
     * @param string $name
     * @param string|null $field
     * @return OrderParameter
     */
    function set($name, $field = null, $direction = null): OrderParameter
    {
        return $this->parent->set($name,$field,$direction);
    }

    /**
     * Remove itself from ordering parameters
     * @return OrderingBuilder
     */
    function remove(){
        $this->parent->remove($this->name);
        return $this->parent;
    }
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
        return $this->parent->add($name,$field,$direction);
    }
}
