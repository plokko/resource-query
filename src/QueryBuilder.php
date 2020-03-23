<?php

namespace plokko\ResourceQuery;


class QueryBuilder extends ResourceQuery
{
    protected $query;

    /**
     * QueryBuilder constructor.
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     */
    function __construct($query)
    {
        parent::__construct();
        $this->query = $query;
    }

    /**
     * Return the base query
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder Base query
     */
    protected function getQuery()
    {
        return $this->query;
    }

}
