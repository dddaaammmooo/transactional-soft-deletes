<?php

namespace Dddaaammmooo\TransactionalSoftDeletes;

trait CamelCaseAttributesTrait
{
    /**
     * Allow camelCased magic attributes
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        /** @noinspection PhpUndefinedClassInspection */
        return parent::getAttribute(snake_case($key));
    }

    /**
     * Allow camelCased magic attributes
     *
     * @param string $key
     * @param mixed  $value
     * @return self
     */
    public function setAttribute($key, $value)
    {
        /** @noinspection PhpUndefinedClassInspection */
        $model = parent::setAttribute(snake_case($key), $value);

        /** @var self $model */
        return $model;
    }
}
