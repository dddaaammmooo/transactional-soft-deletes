<?php

namespace Dddaaammmooo\TransactionalSoftDeletes;

/**
 * Trait ConfigTraits
 *
 * This defines a couple of methods designed to make the syntax easier to read when dealing with functions which
 * may be affected by changed in the configuration file
 *
 * @package Dddaaammmooo\TransactionalSoftDeletes
 */
trait ConfigTraits
{
    /** @var callable $callbackGetUserId */
    protected static $callbackGetUserId;

    /**
     * This is just syntactical candy to make the file easier to read. As all of the column names can be changed
     * by overriding the configuration file we need an easy to read syntax that returns the full name
     *
     * @param $columnName
     * @return string
     */
    protected static function column($columnName): string
    {
        return config("transactional-soft-deletes.column_{$columnName}") ?: '';
    }

    /**
     * Retrieve the ID of the currently logged in user
     *
     * @return int
     */
    protected static function getUserId(): int
    {
        if (!isset(self::$callbackGetUserId))
        {
            self::$callbackGetUserId = config('transactional-soft-deletes.callback_get_user_id');
        }

        return call_user_func(self::$callbackGetUserId);
    }
}
