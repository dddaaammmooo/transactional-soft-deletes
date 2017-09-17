<?php

namespace Dddaaammmooo\TransactionalSoftDeletes;

use Closure;
use Eloquent;
use Exception;

/**
 * Class TransactionalSoftDeletes
 *
 * This work was based off the Laravel SoftDeletes trait
 *
 * @package Dddaaammmooo\TransactionalSoftDeletes
 */
class Model extends Eloquent
{
    use ConfigTraits;

    /**
     * Indicates if the model is currently force deleting rather than soft deleting.
     *
     * @var bool
     */
    protected $forceDeleting = false;

    /**
     * Extend the parent boot method and add this to scope
     *
     * @throws Exception
     */
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new Scope());
    }

    /**
     * Force a hard delete on a soft deleted model.
     *
     * @return bool|null
     * @throws Exception
     */
    public function forceDelete()
    {
        $this->forceDeleting = true;
        $deleted = $this->delete();
        $this->forceDeleting = false;

        return $deleted;
    }

    /**
     * Deletion of the model has been requested, call the appropriate method depending on if we are soft or hard
     * deleting the model
     */
    protected function performDeleteOnModel()
    {
        if ($this->forceDeleting)
        {
            $this->newQueryWithoutScopes()->where($this->getKeyName(), $this->getKey())->forceDelete();
        }
        else
        {
            $this->runSoftDelete();
        }
    }

    /**
     * Perform soft deletion of the model
     *
     * @return bool
     */
    protected function runSoftDelete(): bool
    {
        // Grab the models without the global scopes attached

        $models = $this->newQueryWithoutScopes()->where($this->getKeyName(), $this->getKey())->get();

        // Wrap the restoration inside a transaction in case something goes wrong

        try
        {
            $this->getConnection()->beginTransaction();
        }
        catch (Exception $e)
        {
            // Unable to wrap deletion in a database transaction, cannot proceed for safety reasons

            return false;
        }

        // Keep a log of the models deletion and the row ID that was removed

        foreach ($models as $model)
        {
            /** @noinspection PhpUndefinedClassInspection */
            $deleteTransactionLog = new DeleteTransactionLog(
                [
                    $this->getDeletedAtColumn() => Transaction::getDeleteTransactionId(),
                    self::column('model_class') => get_class($model),
                    self::column('row_id')      => $this->{self::column('id')},
                ]
            );

            if ($deleteTransactionLog->save())
            {
                // Mark the model as deleted by inserting the delete transaction ID

                $this->{$this->getDeletedAtColumn()} = Transaction::getDeleteTransactionId();

                if (!$this->save())
                {
                    $this->getConnection()->rollBack();

                    return false;
                }
            }
        }

        // Persist the changes to the database

        $this->getConnection()->commit();

        return true;
    }

    /**
     * Restore a soft-deleted model instance
     *
     * @return bool
     */
    public function restore(): bool
    {
        // Set mutex on the model to ensure we have any conflicting action

        if ($this->fireModelEvent('restoring') === false)
        {
            return false;
        }

        // Wrap the restoration inside a transaction in case something goes wrong

        try
        {
            $this->getConnection()->beginTransaction();
        }
        catch (Exception $e)
        {
            // Unable to wrap deletion in a database transaction, cannot proceed for safety reasons

            $this->fireModelEvent('restored', true);

            return false;
        }

        // Grab the current delete transaction ID

        $deleteTransactionId = $this->{$this->getDeletedAtColumn()};

        // Restore the current record

        $this->{$this->getDeletedAtColumn()} = null;
        $this->exists = true;

        if (!$this->save())
        {
            // Something went wrong, rollback the action

            $this->fireModelEvent('restored', true);

            return false;
        }

        // Mark the same record as restored in the transaction log

        /** @var DeleteTransactionLog $deleteTransactionLog */

        $deleteTransactionLog = DeleteTransactionLog::where(self::column('model_class)'), '=', get_called_class())
                                                    ->where(self::column('row_id)'), '=', $this->id)
                                                    ->first();

        $deleteTransactionLog->setColumn('restored_by_id', self::getUserId());
        $deleteTransactionLog->setColumn('restored_at', Transaction::getTimestamp());

        if (!$deleteTransactionLog->save())
        {
            $this->fireModelEvent('restored', true);

            return false;
        }

        // If everything in the transaction has been restored mark the whole transaction as restored

        Transaction::deleteIfEmpty($deleteTransactionId);

        // Commit changes to the database

        $this->getConnection()->commit();
        $this->fireModelEvent('restored', false);

        return true;
    }

    /**
     * Determine if the model instance has been soft-deleted
     *
     * @return bool
     */
    public function trashed(): bool
    {
        return !is_null($this->{$this->getDeletedAtColumn()});
    }

    /**
     * Register a restoring model event with the dispatcher
     *
     * @param  Closure|string $callback
     */
    public static function restoring($callback): void
    {
        static::registerModelEvent('restoring', $callback);
    }

    /**
     * Register a restored model event with the dispatcher
     *
     * @param  Closure|string $callback
     */
    public static function restored($callback): void
    {
        static::registerModelEvent('restored', $callback);
    }

    /**
     * Start a new delete transaction
     *
     * @return self
     */
    public function newDeleteTransaction(): self
    {
        Transaction::newDeleteTransactionId();

        return $this;
    }

    /**
     * Determine if the model is currently force deleting.
     *
     * @return bool
     */
    public function isForceDeleting(): bool
    {
        return $this->forceDeleting;
    }

    /**
     * Get the name of the transactional deletion column
     *
     * This can be overridden by the programmer via the configuration file as a global method for all models, or as a
     * class 'DELETED_AT' constant for overriding individual models
     *
     * Priority:
     *
     *      - Individual Class override
     *      - User configuration file
     *      - Default package configuration
     *
     * @return string
     */
    public function getDeletedAtColumn(): string
    {
        /** @noinspection PhpUndefinedClassConstantInspection */
        return defined('static::DELETED_AT')
            ? static::DELETED_AT
            : config('TransactionalSoftDeletes.column_delete_transaction_id');
    }

    /**
     * Get the fully qualified name of the transactional deletion column
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn(): string
    {
        return "{$this->getTable()}.{$this->getDeletedAtColumn()}";
    }
}
