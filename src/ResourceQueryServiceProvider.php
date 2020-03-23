<?php

namespace plokko\ResourceQuery;

use Illuminate\Support\ServiceProvider;

class ResourceQueryServiceProvider extends ServiceProvider
{
    protected $defer = true;

    public function boot()
    {

        // Publish default config //
        /*
        $this->publishes([
            __DIR__.'/config/default.php' => config_path('ResourceQuery.php'),
        ]);
        //*/
        //$this->loadViewsFrom(__DIR__.'/views', 'resourcequery');
    }

    public function register()
    {
        //$this->mergeConfigFrom(__DIR__.'/config/default.php','ResourceQuery');
        /*
        $this->app->singleton('FormBuilderProvider',function ($app){
            return new FormBuilderProvider(config('resourcequery'));//config('page.config')
        });
        */
    }

    public function provides()
    {
        return [];
    }
}
