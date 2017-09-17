<?php

namespace Dddaaammmooo\TransactionalSoftDeletes;

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
        // Publish configuration

        $this->publishes(
            [
                __DIR__ . '/Config/transactional-soft-deletes.php' => config_path('transactional-soft-deletes.php'),
            ], 'config'
        );

        // Load migrations

        $this->loadMigrationsFrom(__DIR__.'/Migrations');
    }

    /**
     * Register service
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/transactional-soft-deletes.php',
            'transactional-soft-deletes'
        );

        if (!is_callable(config('transactional-soft-deletes.callback_get_user_id')))
        {
            config(
                ['transactional-soft-deletes.callback_get_user_id' => function ()
                {
                    return -1;
                }]
            );
        }

        $this->app->singleton(
            'transaction', function ()
        {
            return new Transaction;
        }
        );
    }
}
