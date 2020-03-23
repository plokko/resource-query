<?php

namespace plokko\ResourceQuery;


class FilterBuilder implements \ArrayAccess
{
    /**@var FilterCondition[] */
    private $filters = [];

    function remove($name)
    {
        unset($this[$name]);
    }

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
        return empty($this->filters[$name]) ?
            $this->add($name) :
            $this->filters[$name];
    }

    function add($name, $condition = null, $field = null): FilterCondition
    {
        $cnd = new FilterCondition($name);
        if ($condition)
            $cnd->condition($condition);
        if ($field)
            $cnd->field($field);
        $this->filters[$name] = $cnd;
        return $cnd;
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
