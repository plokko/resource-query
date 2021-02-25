<?php

namespace plokko\ResourceQuery;


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
     */
    function remove($name)
    {
        unset($this[$name]);
    }

    /**
     * Remove all defined filters
     */
    function removeAll()
    {
        $this->filters = [];
    }

    /**
     * @param string $name
     * @return FilterCondition
     */
    function __get($name)
    {
        return $this->add($name);
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
            $this->filters[$name] = new FilterCondition($name,$this);
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
        $cnd = new FilterCondition($name,$this);
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

    /**
     * Called as name([condition][,field]), ex: ->my_field('=','field')
     * @param string $name
     * @param array $arguments
     * @return FilterCondition
     */
    function __call($name, $arguments): FilterCondition
    {
        return $this->add(
            $name,
            optional($arguments[0]),
            optional($arguments[1])
        );
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
        return $this->$offset;
    }

    public function offsetSet($offset, $value)
    {
        throw new \BadFunctionCallException("Filter conditions are read only");
    }

    public function offsetUnset($offset)
    {
        unset($this->filters[$offset]);
    }

    /**
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
