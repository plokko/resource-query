# Resource-query
Automatic filtering and sorting for Laravel queries with Ajax capabilities.

## Scope of this package
This package adds classes that help automate the creation of back-end defined user queries (Ajax) with advanced functions like filtering, sorting, pagination, resource casting and smart casting.
The query will be based on a pre-defined Eloqent query and the user may customize the query by applying pre-defined query parameters. 
All the query parameters are easly defined and customized in the back-end allowing strict control on what the user can see or do.

## Installation
Install it via composer
`composer require plokko\resource-query`

## Initialization
To use this class you must extend the base `ResourceQuery` class or use a builder.
Exending `ResourceQuery` class is preferred if you plan to reutilize the same settings, the builder approach is quicker if you plan to use it one time only.

### Extending ResourceQuery
Create a new class that extends `plokko\ResourceQuery\ResourceQuery` and implement the function `getQuery()` that will return the base query

```php
use plokko\ResourceQuery\ResourceQuery;

class ExampleResourceQuery extends ResourceQuery{

    protected function getQuery():Builder {
        // Return the base query
        return MyModel::select('id','a','b')
                ->where('fixed_condition',1);
    }
    
}
```

### Using the builder
Or by defining it in-place with `QueryBuilder`
```php
use plokko\ResourceQuery\QueryBuilder;

$query = MyModel::select('id','etc');
//Add the base query
$resource =  new QueryBuilder($query);
```


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
```php
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
```php
$resource->filters->remove('<FILTER_NAME>');
```
or with unset function
```php
unset($resource->filters['<FILTER_NAME>']);
```

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
Like with filtering you can declare sorting query parameters via the `OrderBy` parameter in many ways:

```php
$resource->orderBy->add('<SORTING_PARAMETER>'[,<FIELD>][,<DIRECTION>]);
$resource->orderBy-><SORTING_PARAMETER>;
$resource->orderBy['<SORTING_PARAMETER>'];
$resource->orderBy-><SORTING_PARAMETER>([,<FIELD>][,<DIRECTION>]);
```
For each filter you can specify a related table field to order with the `field('<FIELD>')` function and a default sorting direction with `defaultOrder('<DIRECTION>')`.
If you want the sorting direction to be fixed and not modifiable by the user you can use the `direction('<DIRECTION>')` method.

If you want to customize the sorting you can pass a callback as the `field` parameter; the query where the sorting will be applied will be passed as first argument and the sorting direction as second argument.

Example:
```php
$resource->orderBy->email->field('email');
$resource->orderBy->name->field('username')->defaultOrder('desc');
// Forced ascending
$resource->orderBy->add('name-asc','username','asc');
// Example custom filter
$resource->orderBy->test1->field(function($query,$direction){
    $query->orderBy('email',$direction)
          ->orderBy('name',$direction)
          ->orderBy('id','asc');        
});
```

### Default sorting order
If you want to specify a default sorting order you can 

## Order query parameter
The filter query parameter can be set with the `$orderField` parameter of the ResourceQuery.
The filter can be specified as a key value with the sorting parameter as key and the direction as a value or by using one of the two short syntax (as string, comma separated):
* `<sorting_parameter>[:<direction>]`
* `[+|-]<sorting_parameter>`  where prepending "+" means ascending order and "-" descending order

Example:

    page?order_by[field1]=&order_by[field2]=asc&order_by[field3]=desc
Or

    page?order_by=field1,field2:asc,field3:desc
Or

    page?order_by=field1,+field2,-field3

## Javascript API
The package does include a Javascript counterpart to help query the back-end; 
the package is distributed with the composer package instead of a separate npm package as it's strictly related to the php implementation.

Include it in `resources/js/app.js`
```js
import ResourceQuery from "../../vendors/plokko/resource-query/js/ResourceQuery";
// Make it available in pages
window.ResourceQuery = ResourceQuery;
//...
```

### Usage
Instantiate a new ResourceQuery by specifying target URL and method (default get):
```js
let rq = new ResourceQuery('/url','get');
```

The back-end should be something as follow:
```php
class MyController extends Controller {
    //...
    function testPage(Request $request){
        $resource = new UserResourceQuery();
        //...add filters, etc.

        // if it's an Ajax resource return the resource directly 
        if($request->ajax()){
            return $resource;
        }

        // Or else return the HTML page
        return view('mypage');
    }
    //...
}
```

You can add or modify filters, sorting and options
```js
// Set filters root parameter (must be the same as the back-end)
rq.filtersRoot = 'filters';
// Set orderby parameter (must be the same as the back-end)
rq.orderField = 'order_by';

// Add a filter
rq.filters.test1 = 'a';
// or
rq.filter('test1','a');
// or
rq.addFilters({test1:'a',test2:'b'});

// Order
rq.order_by = ['field1',['field2','desc']];
// or
rq.orderBy('field1','asc');

// Clears all the filters
rq.clearFilters();
// Clears all the orderby options
rq.clearOrderBy();
//Clears all the filters and orderby options
rq.resetQuery();

// Set page (if pagination is enabled)
rq.page = 2;
// Set the page size (may be ignored by the back-end if not allowed)
rq.pageSize = 10; // 10 element per page

```
