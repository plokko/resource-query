<?php

namespace plokko\ResourceQuery;

/**
 * ResourceQuery helper for in-place query definition
 * @package plokko\ResourceQuery
 */
class ResourceQueryBuilder extends ResourceQuery
{
    protected $query;

    /**
     * QueryBuilder constructor, base query must be passed here
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query Base query
     * @param Request|null $request optional request, if not set current request will be used
     */
    function __construct($query,Request $request=null)
    {
        parent::__construct($request);
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
