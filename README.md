# ResourceQuery


## Initialization
You can create your own class exteinding ResourceQuery abstract class
```php
use \App\ResourceQuery\ResourceQuery;

class ExampleResourceQuery extends ResourceQuery{

    protected function getQuery():Builder {
        // Return the base query
        return MyModel::select('id','a','b')
                ->where('fixed_condition',1);
    }
    
}
```

Or by defining it in-place with `QueryBuilder`
```php
use \App\ResourceQuery\QueryBuilder;
$query = MyModel::select('id','etc');
//Add the base query
$resource =  new QueryBuilder($query);
```
In either case you have to define or pass a Query where filters and/or ordering rules will be applied.


## Example usage

```php
class MyController extends Controller {
    //...
    public function example1(Request $request){
        $resource = new ExampleResourceQuery();
        if($request->ajax()){
            return $resource;
        }
        view('example',compact('resource'));
    }
    public function example2(Request $request){
        $query = MyModel::select('id','etc');
        $resource =  new QueryBuilder($query);

        if($request->ajax()){
            return $resource;
        }
        view('example',compact('resource'));
    }
    //...
}
```

## Adding filters
Filters are composed of a filter name (request query parameter associated with the filter), 
a condition used to parse the filters and a (optional) field name used to specify the table field to apply the filter to.

The condition can be either a base query condition (like `=`,`!=`,`<>`,`like`,`in`, etc.), 
a shortand helper like `like%`,`%like` or `%like%` that will add a "%" character before, after or at both ends of the input
or a Callable function that will resolve the filter.
To the Callable filter will be passed 3 parameters:
 0. \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder query - The query where to apply the filter
 1. mixed $value - The filtered value
 2. FilterCondition $condition - The current condition, used to retrive condition or field name, etc. 

You can add the filter in many ways:
```
// With the add function (default)
$resource->filters->add(<FILTER_NAME:string>,[<CONDITION:string|callable>],[<FIELD_NAME:string>]);
// Called as a parameter
$resource->filters-><FILTER_NAME>;
// Accessed as an array
$resource->filters['<FILTER_NAME>'];
// Called as a function
$resource->filters-><FILTER_NAME>([<CONDITION:string|callable>],[<FIELD_NAME:string>]);
```

You can also define or modifiy filter conditions or field name with the `condition` or `field` functions, ex:
```php
$resource->filters->add('filter1')->condition('=')->field('fieldA');
$resource->filters->filter2->condition('like')->field('fieldB');
```

You can remove a filter with 
```php $resource->filters->remove('<FILTER_NAME>');```
or with unset function
```php unset($resource->filters['<FILTER_NAME>']);```

If you wish to remove all filters use
```php $resource->removeFilters();```

### Filter definition
Filters can be added either during class inizialization (if extended ResourceQuery)
```php
class MyClassResource extends ResourceQuery{
    //...
    function __construct() {
        //Remember to call parent constructor for inizialization
        parent::__construct();
        // Adding filters
        $this->filters->add('filter1','=','fieldA');
        $this->filters->add('filter2','like','fieldB');
        //...
    }
    //...
}
```

Or by directly calling the resource
```php
$query = MyModel::select('id','etc');
$resource =  new QueryBuilder($query);
// Note: this works also with already defined classes by adding or replacing existing filters
// Ex. replace lines above with: $resource = new ExampleResourceQuery();
$resource->filters->add('filter1','=','fieldA');
$resource->filters->add('filter2','like','fieldB');
```

### Filter root name
If you want to encapsulate all your filter in an array (ex. "url?filter[filter-name]=value" ) you can define it with `setFiltersRoot`

```php
$resource->setFiltersRoot('filter');//<-- 'filter' will be used as the root query parameter 
```
or by setting `$filtersRoot` in the class definition:
```php
class ExampleResourceQuery extends ResourceQuery{
    protected $filtersRoot = 'filter';
    //...
}
```

### Additional filter rules

#### Default filter value
If you want to apply a default value if the field is not present you can use defaultValue function:

```php
$resource->filters->add('filter1','=','fieldA')->defaultValue('1234');//If filter "filter1" is not set it will be applied with value "123"
```

#### Value formatting
If you want to format the filter value (ex. escaping characters, capitalize, etc.) 
before the filter is applied you can add a callable function with the `formatValue` function.
The original filter value will be passed as a parameter and the return value will be used as the new filter value.

Example:
```php
$resource->filters->add('filter1','=','fieldA')->formatValue(function($value){ return trim($value); });
```

#### Apply only if another filter is present
If you want to apply a filter ONLY if one or more filters are present you can use `applyIfPresent` function
```php
$resource->filters->add('filter1','=','fieldA');
$resource->filters->add('filter2','like','fieldB');
// If fitler1 or filter2 are empty fitler3 will be ignored
$resource->filters->add('filter3','=','fieldC')->applyIfPresent('filter1','filter2');
```

#### Apply only if another filter is absent
If you want to apply a filter ONLY if one or more filters are absent you can use `applyIfNotPresent` function
```php
$resource->filters->add('filter1','=','fieldA');
$resource->filters->add('filter2','like','fieldB');
// If fitler1 or filter2 are not empty fitler3 will be ignored
$resource->filters->add('filter3','=','fieldC')->applyIfNotPresent('filter1','filter2');
```


## Sorting
You can also add sorting capabilities to the query by declaring some soring parameters:

```php
$resource->orderBy->add('<SORTING_PARAMETER>');
$resource->orderBy->add('<SORTING_PARAMETER>');
```


## TODO:

 * Sorting
 * pagination
 * resource casting
 * filter advanced options
 
