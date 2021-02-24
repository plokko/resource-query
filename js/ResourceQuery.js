/**
 * Flattern parameters to query string array
 * @param {Object|array} params
 * @param {string|null} prefix
 * @returns {{}}
 */
function flatternURLParams(params,prefix){
    let data= {};

    for(let k in params){
        let v = params[k];
        let key = prefix?`${prefix}[${k}]`:k;
        if(typeof v  !== 'object' && typeof v  !== 'array')
            data[key] = v;
        else{
            Object.assign(data,flatternURLParams(v,key));
        }
    }
    return data;
}

class ResourceQuery {
    /**
     *
     * @param {string} action Target URL, default '' (current page)
     * @param {string} method HTTP method to use (GET, POST, etc.), default GET
     * @param {Object} opt
     */
    constructor(action, method, opt) {
        this.filters = {};
        this.order_by = [];
        this.page = 1;
        this.filterParameter = 'filters';
        this.orderParameter = 'order_by';
        this.pageSize = null;
        if (opt) {
            Object.assign(this, opt);
        }
        this.action = action || '';
        this.method = method || 'get';
    }

    /**
     * Add ordering parameter
     * @param {string} field Field to order
     * @param {string} dir Sorting direction, "asc" or "desc"
     * @returns {ResourceQuery}
     */
    orderBy(field, dir) {
        this.order_by.push([
            field,
            dir || 'asc'
        ]);
        return this;
    }

    /**
     * Remove all sorting settings
     * @returns {ResourceQuery}
     */
    clearOrderBy() {
        this.order_by = [];
        return this;
    }

    /**
     * Remove all filters
     * @returns {ResourceQuery}
     */
    clearFilters() {
        this.filters = {};
        return this;
    }

    /**
     * Add a filter
     * @param {string} name
     * @param {string} value
     * @returns {ResourceQuery}
     */
    filter(name, value) {
        this.filters[name] = value;
        return this;
    }

    /**
     * Add filters as key-value object
     * @param {Object} filters
     * @returns {ResourceQuery}
     */
    addFilters(filters) {
        Object.assign(this.filters, filters);
        return this;
    }

    /**
     * Clear all filters, ordering and page number
     * @returns {ResourceQuery}
     */
    resetQuery() {
        this.filters = {};
        this.order_by = [];
        this.page = 1;
        return this;
    }

    static get cancelToken() {
        return ResourceQuery.getCancelToken();
    }

    static getCancelToken() {
        return axios.CancelToken.source()
    }

    static isCancel(e) {
        return axios.isCancel(e);
    }

    /**
     * Executes the query and return the query data
     * @returns {Promise<Object>}
     */
    get(opt) {
        // Filters
        let data = flatternURLParams(this.filters,this.filterParameter);

        // OrderBy
        if (this.order_by && this.order_by.length > 0) {
            data[this.orderParameter] = (this.order_by.map(v => Array.isArray(v) ? v.join(':') : v)).join(',');
        }
        data.page = this.page;

        let method = this.method.toLowerCase();

        let params = null;
        let cancelToken = opt && opt.cancelToken && opt.cancelToken.token;

        let cfg = Object.assign({},
            opt||{},
            {
                url: this.action,
                cancelToken,
            }
        );

        if (method === 'get') {
            cfg.method = 'get';
            cfg.params = data;
        } else {
            cfg.method = 'post';
            data['_method'] = this.method;
            cfg.data = data;
        }

        // Execute query
        return new Promise((resolve, reject) => {
            let rq = axios(cfg)
                .then(r => {
                    if (r && r.data) {
                        resolve(r.data);
                    } else {
                        let e = new Error("Unexpected value encountered")
                        e.response = r;
                        reject(e);
                    }
                })
                .catch(e => {
                    reject(e);
                })
        });
    }

    /**
     * Executes the query and returns a QueryResult
     * @returns {Promise<QueryResult>}
     */
    getResult(opt) {
        return new Promise((resolve, reject) => {
            this.get(opt).then(r => {
                resolve(new QueryResult(r, this.clone()));
            })
                .catch(e => {
                    reject(e);
                });
        })
    }

    /**
     * Return paginator
     * @param {boolean} prefetch If true start fetching after call
     * @returns {Paginator}
     */
    paginate(prefetch = true) {
        return new Paginator(this.clone(), prefetch);
    }

    /**
     * Clone item
     * @returns {ResourceQuery}
     */
    clone() {
        let e = new ResourceQuery(this.action, this.method);
        Object.assign(e, this)
        return e
    }
}

class QueryResult {
    constructor(responseData, query) {
        this._response = responseData;
        this._query = query;

        this.data = responseData.data;
        if (responseData.meta) {
            this.current_page = responseData.meta.current_page;
            this.last_page = responseData.meta.last_page;
            this.per_page = responseData.meta.per_page;
            this.total = responseData.meta.total;
        }
    }

    hasPrevPage() {
        return this.current_page && this.current_page > 1;
    }

    hasNextPage() {
        return this.current_page && this.current_page < this.last_page;
    }

    getPrevPage() {
        if (!this.hasPrevPage())
            return false;
        let q = this._query.clone();
        q.page = this.page - 1;
        return q.get();
    }

    getNextPage() {
        if (!this.hasNextPage())
            return false;
        let q = this._query.clone();
        q.page = this.page + 1;
        return q.get();
    }
}

/**
 * Helper class to read paginated data by fetching page by page in an incrementing manner
 * @property {Array} data Data read
 * @property {Boolean} loading true if loading
 * @property {Integer} current_page Current page number
 * @property {Integer} last_page Last page
 */
class Paginator {

    constructor(query, prefetch = false) {
        this.data = [];
        this._query = query;
        this.loading = false;
        this._lastMeta = null;
        //this._loadData(responseData);
        if (prefetch)
            this.loadMore();
    }

    /**
     * Return true if there are more item to fetch, false otherwise
     * @returns {boolean}
     */
    hasMore() {
        return this._lastMeta===null || (this._lastMeta && this._lastMeta.current_page && this._lastMeta.current_page < this._lastMeta.last_page);
    }

    /**
     * @private
     */
    _loadData(r) {
        if(r.data)
            this.data = this.data.concat(r.data);
        this._lastMeta = r.meta;
        this.current_page = r.meta && r.meta.current_page;
        this.last_page = r.meta && r.meta.last_page;
    }

    /**
     * Fetch next page
     * @returns {Promise}
     */
    loadMore() {
        if (!this.loading) {
            this.loading = true;
            this._query.page = this.current_page + 1;

            this._lastQuery = new Promise((resolve, reject) => {
                if (!this.hasMore()) {
                    this.loading = false;
                    resolve(this.data);
                }

                this._lastQuery = this._query.get()
                    .then(r => {
                        this._loadData(r);
                        resolve(this.data);
                    })
                    .catch(e => {
                        reject(e);
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            });
        }
        return this._lastQuery;
    }

}

export default ResourceQuery
