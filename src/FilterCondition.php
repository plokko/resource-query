<?php

namespace plokko\ResourceQuery;

/**
 * Class FilterCondition
 * @package App\ResourceQuery
 *
 * @property-read string $name
 * @property-read $field
 * @property-read string|callable $condition
 * @property-read mixed $defaultValue
 */
class FilterCondition
{
    private
        $name = null,
        $field = null,
        $condition = '=',
        $defaultValue = null,
        $applyIf = null,
        /** @var Callable|null */
        $valueFormatter = null;

    /**
     * FilterCondition constructor.
     * @param string $name Field name
     */
    function __construct(string $name)
    {
        $this->name = $name;
        $this->field = $name;
    }

    function __get($name)
    {
        switch ($name) {
            case 'name':
            case 'field':
            case 'condition':
            case 'defaultValue':
                return $this->$name;
            default:
                return null;
        }
    }

    /**
     * @param string $field
     * @return FilterCondition
     */
    function field($field): FilterCondition
    {

        $this->field = $field;
        return $this;
    }

    /**
     * @param string|callable $condition
     * @return FilterCondition
     */
    function condition($condition): FilterCondition
    {
        $this->condition = $condition;
        return $this;
    }

    /**
     * Set filter default value (used if none are specified)
     * @param $value
     * @return FilterCondition
     */
    function defaultValue($value): FilterCondition
    {
        $this->defaultValue = $value;
        return $this;
    }

    /**
     * Format the filtered value
     * @param callable|null $formatter
     * @return FilterCondition
     */
    function formatValue($formatter)
    {
        $this->valueFormatter = $formatter;
        return $this;
    }

    /**
     * Only apply this condition if another filter is present
     * @param string $name,... Required field name/names
     * @return FilterCondition
     */
    function applyIfPresent(...$name): FilterCondition
    {
        $this->applyIf(function ($filters, $condition) use ($name) {
            foreach ($name as $k) {
                if (empty($filters[$k]))
                    return false;
            }
            return true;
        });
        return $this;
    }

    /**
     * Apply this filter only if condition is met
     * @param callable $cnd condition, return boolean, parameters:
     * @return FilterCondition
     */
    function applyIf($cnd): FilterCondition
    {
        $this->applyIf = $cnd;
        return $this;
    }

    /**
     * Only apply this condition if another filter is NOT present
     * @param string $name filtered field name
     * @return FilterCondition
     */
    function applyIfNotPresent($name): FilterCondition
    {
        $name = func_get_args();
        $this->applyIf(function ($filters, $condition) use ($name) {
            foreach ($name as $k) {
                if (!empty($filters[$k]))
                    return false;
            }
            return true;
        });
        return $this;
    }

    /**
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param array $filterData
     */
    function apply($query, array $filterData, array &$appliedFilters = [])
    {
        if ($this->shouldBeApplied($filterData)) {
            $value = empty($filterData[$this->name]) ? $this->defaultValue : $filterData[$this->name];
            if ($this->valueFormatter) {
                $value = ($this->valueFormatter)($value);
            }

            $appliedFilters[] = $this->name;

            switch ($this->condition) {
                case '%like%':
                    return $query->where($this->field, 'like', "%$value%");
                case '%like':
                    return $query->where($this->field, 'like', "%$value");
                case 'like%':
                    return $query->where($this->field, 'like', "$value%");
                case 'like':
                case '!=':
                case '<>':
                case '>=':
                case '<=':
                case '=':
                    return $query->where($this->field, $this->condition, $value);
                case 'in':
                    if (!is_array($value))
                        $value = explode(';', $value);
                    return $query->whereIn($this->field, $value);
                default:
                    if (is_callable($this->condition)) {
                        return ($this->condition)($query, $value, $this);
                    } else {
                        //throw new UnexpectedValueException("Unapplicable field condition ".$this->condition);
                    }
            }
        }
        return $query;
    }

    /**
     * Return true if the filter can be applied to the query
     * @param array $filterData
     * @return bool
     */
    function shouldBeApplied(array $filterData): bool
    {
        return (!empty($filterData[$this->name]) || $this->defaultValue != null) && $this->isApplicable($filterData);
    }

    /**
     * Return true if apply conditions are met
     * @param array $filterData
     * @return bool
     */
    function isApplicable(array $filterData)
    {
        return !$this->applyIf || ($this->applyIf)($filterData, $this);
    }
}
