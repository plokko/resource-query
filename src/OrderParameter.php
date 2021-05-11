<?php
namespace plokko\ResourceQuery;

use plokko\ResourceQuery\Traits\ResourceQueryFallbackTrait;

/**
 * Class OrderParameter
 * @package plokko\ResourceQuery
 * @property-read string $name
 * @property-read string $field
 */
class OrderParameter
{
    use ResourceQueryFallbackTrait;

    private $name,
        $field,
        $direction = null,
        /** @var ResourceQuery */
        $parent;

    public
        $inverted = false;

    function __construct($name, ResourceQuery $parent)
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
     * Set forced sorting direction
     * @param string|null $direction 'asc', 'desc' or null if user specified
     * @return $this
     */
    public function direction($direction): OrderParameter
    {
        $this->direction = $direction;
        return $this;
    }

    /**
     * Invert sorting direction: if set 'asc' will be applied as descending order and vice versa
     * @param bool $invert True if should be inverted
     * @return $this
     */
    function invert($invert=true){
        $this->inverted = $invert;
        return $this;
    }

    /**
     * Apply condition to the query
     * @private
     * @param $query Query
     * @param null|string $dir Direction ('asc' or 'desc')
     * @return array|null return applied sorting or null if was not applied
     */
    function apply($query, $dir,$asDefault=false)
    {
        $direction = $dir==='desc' ||($asDefault && $this->default === 'desc')?'desc':'asc';

        //Force specified direction
        if ($this->direction) {
            $direction = $this->direction;
        }

        if ($this->shouldApply()) {
            $dir = $this->inverted?($direction==='asc'?'desc':'asc'):$direction;
            if (is_callable($this->field)) {
                //callback implementation
                ($this->field)($query, $dir);
                return [$this->name, $direction];
            } else {
                $query->orderBy($this->field, $dir);
                return [$this->name, $direction];
            }
        }
        return null;
    }

    /**
     * Check conditions and tells if the sorting should be applied
     * @protected
     * @return bool
     */
    protected function shouldApply()
    {
        //TODO
        return true;
    }


    /**
     * Remove itself from ordering parameters
     * @return OrderingBuilder
     */
    function remove(){
        $this->parent->remove($this->name);
        return $this->parent;
    }
}
