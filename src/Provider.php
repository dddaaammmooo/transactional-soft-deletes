<?php

namespace Dddaaammmooo\TransactionalSoftDeletes;

use Illuminate\Foundation\Console\PackageDiscoverCommand;
use Illuminate\Support\ServiceProvider;

class Provider extends ServiceProvider
{

    /**
     * Register configuration file
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes(
            [
                __DIR__ . '/Config/TransactionalSoftDeletes.php.php' => config_path('TransactionalSoftDeletes.php'),
            ], 'config'
        );
    }

    /**
     * Register service
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/TransactionalSoftDeletes.php',
            'TransactionalSoftDeletes'
        );

        if (!is_callable(config('TransactionalSoftDeletes.callback_get_user_id'))) {
            config(['TransactionalSoftDeletes.callback_get_user_id' => function() { return -1; }]);
        }

        $this->app->singleton(
            'transaction', function ()
        {
            return new Transaction;
        }
        );
    }
}
