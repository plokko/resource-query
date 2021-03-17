<?php

namespace plokko\ResourceQuery;

/**
 * ResourceQuery helper class for building and managing filters
 * @package plokko\ResourceQuery
 */
class FilterBuilder implements \ArrayAccess
{
    /**@var FilterCondition[] */
    private $filters = [];
    /** @var null|string Filters query parameter*/
    public $filterParameter='filters';
    /** @var ResourceQuery*/
    private $parent;

    function __construct($parent){
        $this->parent = $parent;
    }
    /**
     * Set filters query parameter
     * @param null|string $field
     * @return $this
     */
    public function setFiltersParameter($field=null){
        $this->filtersRoot = $field;
        return $this;
    }

    /**
     * Remove a filter by name
     * @param string $name
     * @return $this
     */
    function remove($name)
    {
        unset($this->filters[$name]);
        return $this;
    }

    /**
     * Remove all defined filters
     */
    function removeAll()
    {
        $this->filters = [];
    }

    /**
     * Return a filter by name if set, null otherwise
     * @param string $name Filter label
     * @return FilterCondition|null
     */
    function get($name){
        return isset($this->filters[$name])?$this->filters[$name]:null;
    }

    /**
     * Add a new filter or update an existing one
     * @param string $name
     * @param callable|string $condition
     * @param null $field
     * @return FilterCondition
     */
    function add($name, $condition = null, $field = null): FilterCondition
    {
        if(!isset($this->filters[$name])){
            $this->filters[$name] = new FilterCondition($name,$this->parent);
        }
        if ($condition)
            $this->filters[$name]->condition($condition);
        if ($field)
            $this->filters[$name]->field($field);

        return $this->filters[$name];
    }

    /**
     * Set a new filter, if exists overwrite
     * @param string $name
     * @param callable|string $condition
     * @param null $field
     * @return FilterCondition
     */
    function set($name, $condition = null, $field = null): FilterCondition
    {
        $cnd = new FilterCondition($name,$this->parent);
        if ($condition)
            $cnd->condition($condition);
        if ($field)
            $cnd->field($field);
        $this->filters[$name] = $cnd;
        return $cnd;
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
        return $this->add($name,$condition,$field);
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

    public function offsetExists($offset)
    {
        return isset($this->filters[$offset]);
    }

    /**
     * @param mixed $offset
     * @return FilterCondition
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        throw new \BadFunctionCallException("Filter conditions are read only");
    }

    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * @internal
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param array $filterData
     * @param array $appliedFilters
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function applyConditions($query, array $filterData, array &$appliedFilters = [])
    {
        foreach ($this->filters as $cnd) {
            $cnd->apply($query, $filterData, $appliedFilters);
        }
        return $query;
    }
}
