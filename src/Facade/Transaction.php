<?php namespace Dddaaammmooo\TransactionalSoftDeletes\Facade;

use Illuminate\Support\Facades\Facade;

class Transaction extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'transaction';
    }
}
