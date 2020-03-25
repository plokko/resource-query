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
        this.filtersRoot = null;
        this.orderField = 'order_by';
        this.pageSize = null;
        if (opt) {
            Object.assign(this, opt);
        }
        this.action = action || '';
        this.method = method || 'get';
    }

    orderBy(field, dir) {
        this.order_by.push([
            field,
            dir || 'asc'
        ]);
        return this;
    }

    clearOrderBy() {
        this.order_by = [];
        return this;
    }

    clearFilters() {
        this.filters = {};
        return this;
    }

    filter(name, value) {
        this.filters[name] = value;
        return this;
    }

    addFilters(filters) {
        Object.assign(this.filters, filters);
        return this;
    }

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
        let data = {};
        // Filters
        if (this.filtersRoot) {
            data[this.filtersRoot] = this.filters;
        } else {
            Object.assign(data, this.filters);
        }
        // OrderBy
        if (this.order_by.length > 0) {
            data[this.orderField] = (this.order_by.map(v => Array.isArray(v) ? v.join(':') : v)).join(',');
        }
        data.page = this.page;

        let method = this.method.toLowerCase();

        let params = null;


        let cancelToken = opt && opt.cancelToken && opt.cancelToken.token;

        let cfg = {
            url: this.action,
            cancelToken,
        };

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
    getResult() {
        return new Promise((resolve, reject) => {
            this.get().then(r => {
                resolve(new QueryResult(r, this.clone()));
            })
                .catch(e => {
                    reject(e);
                });
        })
    }

    paginate(prefetch = true) {
        return new Paginator(this.clone(), prefetch);
    }

    clone() {
        let e = new ResourceQuery(this.action, this.method);
        Object.assign(e, this)
        return e
    }
}

class CancellablePromise {

    constructor(executor, onCancel) {
        this.promise = new Promise(executor);
        this._onCancel = onCancel;
    }

    then() {
        this.promise.then.apply(this.promise, arguments);
        return this;
    }

    catch() {
        this.promise.catch.apply(this.promise, arguments);
        return this;
    }

    finally() {
        this.promise.finally.apply(this.promise, arguments);
        return this;
    }

    cancel() {
        console.log({oncancel: this._onCancel, e: this})
        this._onCancel && this._onCancel();
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
 * @property {Array} data
 * @property {Boolean} loading
 * @property {Integer} current_page
 * @property {Integer} last_page
 */
class Paginator {

    constructor(query, prefetch = false) {
        this.data = [];
        this._query = query;
        this.loading = false;

        //this._loadData(responseData);
        if (prefetch)
            this.loadMore();
    }

    hasMore() {
        return this._lastMeta && this._lastMeta.current_page && this._lastMeta.current_page < this._lastMeta.last_page;
    }

    _loadData(r) {
        this.data = this.data.concat(r.data);
        this._lastMeta = r.meta;
        this.current_page = r.meta && r.meta.current_page;
        this.last_page = r.meta && r.meta.last_page;
    }

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
