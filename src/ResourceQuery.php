<?php

namespace plokko\ResourceQuery;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use IteratorAggregate;
use JsonSerializable;

/**
 * Class ResourceQuery
 * @package App\ResourceQuery
 *
 * @property-read FilterBuilder $filters
 * @property-read OrderingBuilder $orderBy
 * @property-read int $page
 * @property-read string|null $filtersRoot
 * @property-read string|null $orderField
 * @property-read int|null|array $paginate
 * @property-read string|null $userResource
 */
abstract class ResourceQuery implements JsonSerializable, Responsable, IteratorAggregate, Arrayable
{
    protected
        /** @var null|int|int[] */
        $pagination = 10,
        /** @var string */
        $paginationField = 'per_page',
        /** @var string|null Base parameter used for filtering */
        $filtersRoot = null,
        /** @var string Parameter used for ordering */
        $orderField = 'order_by',
        /** @var null|string */
        $userResource = null;
    private
        /** @var FilterBuilder */
        $filters,
        /** @var OrderingBuilder */
        $orderBy,
        /** @var int current page */
        $page = 1;

    function __construct()
    {
        $this->filters = new FilterBuilder();
        $this->orderBy = new OrderingBuilder();
    }

    function __get($name)
    {
        switch ($name) {
            case 'defaultOrderBy':
            case 'filters':
            case 'orderBy':
            case 'page':
            case 'filtersRoot':
            case 'pagination':
                return $this->$name;
            default:
                return null;
        }
    }

    /**
     * Set filters root
     * @param string|null $name filters root
     */
    function setFiltersRoot($name)
    {
        $this->filtersRoot = $name;
    }

    function useResource($resourceClass)
    {
        $this->userResource = $resourceClass;
    }

    /**
     * Set pagination
     * @param int|null|array $pagination
     */
    final function setPagination($pagination)
    {
        $this->paginate = $pagination;
    }

    /**
     * Set default order
     * @param string|null $field
     * @param string $direction
     */
    final function setDefaultOrderBy($field, $direction = 'asc')
    {
        $this->defaultOrderBy = $field ? [$field, $direction] : null;
    }

    final function removeFilters()
    {
        $this->filters->removeAll();
    }

    /**
     * @return string
     */
    public function toSql()
    {
        return $this->query()->toSql();
    }

    /**
     * Builds the query applying filers and sorting rules
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    function query(Request $request = null, array &$opts = [])
    {
        if ($request == null) {
            $request = request();
        }
        $query = $this->getQuery();

        $filterData = $this->getFilterData($request);
        $orderData = $this->getOrderData($request);

        $appliedFilters = [];
        $ordersBy = [];
        $this->applyFilters($query, $filterData, $appliedFilters);
        $this->applyOrdering($query, $orderData, $ordersBy);

        $opts['applied_filters'] = $appliedFilters;
        $opts['order_by'] = $ordersBy;

        return $query;
    }

    /**
     * Return the base query
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder Base query
     */
    abstract protected function getQuery();

    protected function getFilterData(Request $request): array
    {
        $data = $request->input($this->filtersRoot);
        return $data;
    }

    protected function getOrderData(Request $request)
    {
        $data = $request->input($this->orderField);
        if ($data) {
            if (!is_array($data))
                $data = explode(',', $data);
            $data = array_map(function ($v) {
                $e = explode(':', $v);
                return count($e) > 1 ? $e : $e[0];
            }, $data);
        }
        return $data;
    }

    /**
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param array[] $filters Filters to array
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    protected function applyFilters($query, array $filterData, array &$appliedFilters = [])
    {
        return $this->filters->applyConditions($query, $filterData, $appliedFilters);
    }

    /**
     * Apply ordering filters to the query
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param string[][] $orderBy array of sorting order (field,direction)
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder Query with sorting filters applied
     */
    protected function applyOrdering($query, ?array $orderBy = [], array &$ordersBy = [])
    {
        if ($orderBy) {
            $this->orderBy->applyConditions($query, $orderBy, $ordersBy);
        }
        return $query;
    }

    public function getIterator()
    {
        return collect($this->toArray());
    }

    public function toArray(Request $request = null)
    {
        return $this->toResource($request)->toArray($request ?: request());
    }

    /**
     * @param Request|null $request
     * @return JsonResource
     */
    final public function toResource(Request $request = null)
    {
        $opts = [];
        $result = $this->get($request, $opts);
        $resource = call_user_func([$this->useResource ?: JsonResource::class, 'collection'], $result);
        /** @var JsonResource $resource */
        $resource->additional([
            //'active_filters' => array_unique($opts['applied_filters']),
            'order_by' => $opts['order_by'],
        ]);
        /*
        $resource->additional([
            'active_filters' => array_unique($opts['applied_filters']),
        ]);
        /*
        $resource->additional(['orderBy' => $orderBy]);
        //*/
        return $resource;
    }

    function get(Request $request = null, array &$opts = [])
    {
        if (!$request)
            $request = request();

        $query = $this->query($request, $opts);
        $pageSize = $this->getPageSize($request);

        $result = null;
        if ($pageSize) {
            $result = $query->paginate($pageSize);
        } else {
            $result = $query->get();
        }

        return $result;
    }

    /**
     * Return the page size to use, if null no pagination is used
     * @param Request $request
     * @return int|null
     */
    protected function getPageSize(Request $request)
    {
        if (!is_array($this->pagination))
            return $this->pagination;
        $n = $request->input($this->paginationField);
        return in_array($n, $this->pagination) ? $n : current($this->pagination);
    }

    function jsonSerialize()
    {
        return $this->getData();
    }

    function getData()
    {
        //TODO!
        $query = $this->getQuery();

    }

    public function toResponse($request)
    {
        return $this->toResource($request)->toResponse($request);
    }
}
