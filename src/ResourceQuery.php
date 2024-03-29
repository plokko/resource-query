<?php

namespace plokko\ResourceQuery;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use IteratorAggregate;
use JsonSerializable;
use plokko\ResourceQuery\Resources\BaseResource;

/**
 * Class ResourceQuery
 * @package  plokko\ResourceQuery
 *
 * @property-read FilterBuilder $filters
 * @property-read OrderingBuilder $orderBy
 * @property-read int $page
 * @property-read int|null|array $paginate
 * @property-read string|null $useResource
 */
abstract class ResourceQuery implements JsonSerializable, Responsable, IteratorAggregate, Arrayable
{
    protected
        /** @var null|int|int[] */
        $pagination = 10,
        /** @var string */
        $paginationField = 'per_page',
        /** @var null|string */
        $useResource = null,
        /** @var Request */
        $request;

    private
        /** @var FilterBuilder */
        $filters,
        /** @var OrderingBuilder */
        $orderBy,
        /** @var int current page */
        $page = 1;

    function __construct(Request $request=null)
    {
        $this->request = $request?:request();
        $this->filters = new FilterBuilder($this);
        $this->orderBy = new OrderingBuilder($this);
        $this->init();
    }

    /**
     * Initialization function, where filters and ordering are set
     */
    protected function init(){}

    function __get($name)
    {
        switch ($name) {
            case 'filters':
            case 'orderBy':
            case 'page':
            case 'pagination':
            case 'useResource':
                return $this->$name;
            default:
                return null;
        }
    }

    /**
     * Add a new filter or update an existing one
     * @param string $name Filter name
     * @param callable|string $condition Filter condition
     * @param null|string $field Query field, if null or not specified filter name will be used
     * @return FilterCondition
     */
    function filter($name, $condition = null, $field = null): FilterCondition
    {
        return $this->filters->add($name,$condition,$field);
    }

    /**
     * Remove a filter by it's name
     * @param string $name Filter name
     * @return $this
     */
    function removeFilter($name)
    {
        $this->filters->remove($name);
        return $this;
    }

    /**
     * Check if a filter is defined
     * @param string $name Filter name
     * @return boolean
     */
    function filterExists($name)
    {
        return isset($this->filters[$name]);
    }

    /**
     * Add or updates a sorting setting
     * @param string $name
     * @param string|null $field
     * @return OrderParameter
     */
    function orderBy($name, $field = null, $direction = null): OrderParameter
    {
        return $this->orderBy->add($name,$field,$direction);
    }

    /**
     * @param array|null $order
     * @return OrderingBuilder
     */
    public function setDefaultOrder(array $order=null){
        return $this->orderBy->setDefaultOrder($order);
    }

    /**
     * @param string|null $resourceClass
     * @return $this
     */
    function useResource($resourceClass)
    {
        $this->useResource = $resourceClass;
        return $this;
    }

    /**
     * Set pagination
     * @param int|null|array $pagination
     * @return $this
     */
    final function setPagination($pagination)
    {
        $this->pagination = $pagination;
        return $this;
    }

    /**
     * Remove all filters
     * @return $this
     */
    final function removeFilters()
    {
        $this->filters->removeAll();
        return $this;
    }

    /**
     * Return the query as a SQL string
     * @return string
     */
    public function toSql()
    {
        return $this->query()->toSql();
    }

    public function getBindings()
    {
        return $this->query()->getBindings();
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
     * Return the base query, must be implemented by the final class
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder Base query
     */
    abstract protected function getQuery();

    /**
     * Get filter parameters
     * @internal
     * @param Request $request
     * @return array
     */
    protected function getFilterData(Request $request): array
    {
        $data = $request->input($this->filters->filterParameter,[]);
        return is_array($data)?$data:[$data];
    }

    /**
     * Get order parameters
     * @internal
     * @param Request $request
     * @return array|false[]|mixed|string[]|\string[][]
     */
    protected function getOrderData(Request $request)
    {
        $data = $request->input($this->orderBy->orderField,[]);
        if ($data) {
            if (!is_array($data))
                $data = explode(',', $data);
            $data = array_map(function ($v) {
                switch($v[0]){
                    case '-':
                        return [substr($v,1),'desc'];
                    case '^':
                        return [substr($v,1),'asc'];
                    default:
                        $e = explode(':', $v);
                        return $e;
                }

            }, $data);
        }
        return $data;
    }

    /**
     * Apply the filters to the base query
     * @internal
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param array[] $filters Filters to array
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    protected function applyFilters($query, array $filterData, array &$appliedFilters = [])
    {
        return $this->filters->applyConditions($query, $filterData, $appliedFilters);
    }

    /**
     * Apply ordering filters to the base query
     * @internal
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param string[][] $orderBy array of sorting order (field,direction)
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder Query with sorting filters applied
     */
    protected function applyOrdering($query, ?array $orderBy = [], array &$ordersBy = [])
    {
        $this->orderBy->applyConditions($query, $orderBy, $ordersBy);
        return $query;
    }

    /**
     * Make the class iterable
     * @return \Illuminate\Support\Collection|\Traversable
     */
    public function getIterator()
    {
        return collect($this->toArray());
    }

    /**
     * Executes the query and returns the data as an array
     * @return array
     */
    public function toArray(Request $request = null)
    {
        return $this->toResource($request)->toArray($this->request);
    }

    /**
     * Executes the query and returns the result as a JsonResource or cast to the specified API Resource
     * @see https://github.com/plokko/resource-query/wiki/Resource-casting
     * @param Request|null $request
     * @return JsonResource
     */
    final public function toResource(Request $request = null)
    {
        $opts = [];
        $result = $this->get($request, $opts);
        $resource = call_user_func([($this->useResource ?: BaseResource::class), 'collection'], $result);
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

    /**
     * Executes the query
     * @param Request|null $request
     * @param array $opts
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */
    public function get(Request $request = null, array &$opts = [])
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

    /**
     * Cast to Json
     * @return mixed
     */
    public function jsonSerialize()
    {
        return $this->getData();
    }

    public function getData()
    {
        return $this->toResource($this->request);
    }

    /**
     * Cast to response
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        return $this->toResource($request)->toResponse($request);
    }

    public function __toString(){
        return json_encode($this->jsonSerialize());
    }
}
