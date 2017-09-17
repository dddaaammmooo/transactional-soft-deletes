<?php

namespace Dddaaammmooo\TransactionalSoftDeletes;

/**
 * Trait DatabaseTrait
 *
 * Defines model methods to make dealing with dynamically configured column names easier when dealing with the
 * database. Purely syntactical candy stuff.
 *
 * @package Dddaaammmooo\TransactionalSoftDeletes
 */
trait DatabaseTrait
{
    /**
     * Set a value using the column names from the configuration file
     *
     * @param string $columnName The name of the database column
     * @param mixed  $value      The value to set
     * @return self
     */
    public function setColumn(string $columnName, $value): self
    {
        $this->{config("transactional-soft-deletes.column_{$columnName}")} = $value;

        return $this;
    }

    /**
     * Return a value using the column names from the configuration file
     *
     * @param string $columnName The name of the database column
     * @return mixed
     */
    public function getColumn(string $columnName): string
    {
        return $this->{config("transactional-soft-deletes.column_{$columnName}")} ?: '';
    }

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
