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
        $this->filters = new FilterBuilder();
        $this->orderBy = new OrderingBuilder();
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
        $this->paginate = $pagination;
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
        $data = $request->input($this->orderBy->orderField);
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
        if ($orderBy) {
            $this->orderBy->applyConditions($query, $orderBy, $ordersBy);
        }
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
