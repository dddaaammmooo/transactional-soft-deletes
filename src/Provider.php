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
                __DIR__ . '/Config/TransactionalSoftDeletes.php' => config_path('TransactionalSoftDeletes.php'),
            ], 'config'
        );

        // Publish migrations

        $this->publishes(
            [
                __DIR__ . '/Migrations/2017_09_16_013221_create_delete_transaction_table.php' => database_path('migrations'),
                __DIR__ . '/Migrations/2017_09_16_015207_create_delete_transaction_log_table.php' => database_path('migrations'),
            ], 'migrations'
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

        if (!is_callable(config('TransactionalSoftDeletes.callback_get_user_id')))
        {
            config(
                ['TransactionalSoftDeletes.callback_get_user_id' => function ()
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
