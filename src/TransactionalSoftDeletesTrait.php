<?php

namespace App\Cto\Database\TransactionalSoftDeletes;

use App\Cto\Database\TransactionalSoftDeletes\Scope as TransactionalDeleteScope;
use Closure;
use Exception;

/**
 * App\Cto\Database\TransactionalSoftDelete\TransactionalSoftDeleteModel
 *
 * @mixin \Eloquent
 */
trait TransactionalSoftDeletesTrait
{
    /**
     * Indicates if the model is currently force deleting rather than soft deleting.
     *
     * @var bool
     */
    protected $forceDeleting = false;

    /**
     * These sheningans took me an eternity to work out. There is a boot method buried in Laravel that looks
     * for a method on every trait included in a class called bootClassName() and executes them. This is
     * required in order to wire up the soft deletes properly on the Laravel query builder.
     *
     * @return void
     */
    public static function bootTransactionalSoftDeletesTrait()
    {
        static::addGlobalScope(new TransactionalDeleteScope());
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
     */
    protected function runSoftDelete()
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
                    'modelClass'                => get_class($model),
                    'rowId'                     => $this->id,
                ]
            );

            if ($deleteTransactionLog->save())
            {
                // Mark the model as deleted by inserting the delete transaction ID

                /** @noinspection PhpUndefinedClassInspection */
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
     * Delete transaction from database if there are no remaining records associated with it
     *
     * @param $deleteTransactionId
     */
    public function deleteTransactionIfEmpty($deleteTransactionId)
    {
        // Check if there are any remaining records

        $countRemaining = DeleteTransactionLog::whereDeleteTransactionId($deleteTransactionId)
                                              ->whereNull('restored_at')
                                              ->count();

        if ($countRemaining == 0)
        {
            // There were no remaining records, we can delete the transaction

            $deleteTransaction = DeleteTransaction::whereId($deleteTransactionId)->first();

            if ($deleteTransaction)
            {
                $deleteTransaction->restoredById = 3;
                /** @noinspection PhpUndefinedClassInspection */
                $deleteTransaction->restoredAt = Transaction::getTimestamp();
                $deleteTransaction->save();
            }
        }
    }

    /**
     * Restore a soft-deleted model instance
     */
    public function restore()
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

        // Restore the current record

        $deleteTransactionId = $this->{$this->getDeletedAtColumn()};

        $this->{$this->getDeletedAtColumn()} = null;
        $this->exists = true;

        if (!$this->save())
        {
            // Something went wrong, rollback the action

            $this->fireModelEvent('restored', true);

            return false;
        }

        // Mark it as restored in the transaction log

        /** @var DeleteTransactionLog $deleteTransactionLog */
        /** @noinspection PhpUndefinedMethodInspection */
        $deleteTransactionLog = DeleteTransactionLog::getModel()
                                                    ->newQueryWithoutScopes()
                                                    ->whereModelClass(get_called_class())
                                                    ->whereRowId($this->id)
                                                    ->first();

        $deleteTransactionLog->restoredById = 3;

        /** @noinspection PhpUndefinedClassInspection */
        $deleteTransactionLog->restoredAt = Transaction::getTimestamp();
        $deleteTransactionLog->save();

        if (!$deleteTransactionLog->save())
        {
            // Something went wrong, rollback the action

            $this->fireModelEvent('restored', true);

            return false;
        }

        // If there are no other items associated with the delete transaction, mark the entire transaction as restored

        $this->deleteTransactionIfEmpty($deleteTransactionId);

        // Persist the changes to the database

        $this->getConnection()->commit();

        // Release mutex on the model

        $this->fireModelEvent('restored', false);

        return true;
    }

    /**
     * Determine if the model instance has been soft-deleted.
     *
     * @return bool
     */
    public function trashed()
    {
        return !is_null($this->{$this->getDeletedAtColumn()});
    }

    /**
     * Register a restoring model event with the dispatcher.
     *
     * @param  Closure|string $callback
     * @return void
     */
    public static function restoring($callback)
    {
        static::registerModelEvent('restoring', $callback);
    }

    /**
     * Register a restored model event with the dispatcher.
     *
     * @param  Closure|string $callback
     * @return void
     */
    public static function restored($callback)
    {
        static::registerModelEvent('restored', $callback);
    }

    /**
     * Start a new delete transaction
     *
     * @return self
     */
    public function newDeleteTransaction()
    {
        Transaction::newDeleteTransactionId();

        return $this;
    }

    /**
     * Determine if the model is currently force deleting.
     *
     * @return bool
     */
    public function isForceDeleting()
    {
        return $this->forceDeleting;
    }

    /**
     * Get the name of the transactional deletion column
     *
     * @return string
     */
    public function getDeletedAtColumn()
    {
        /** @noinspection PhpUndefinedClassConstantInspection */
        return defined('static::DELETED_AT') ? static::DELETED_AT : 'delete_transaction_id';
    }

    /**
     * Get the fully qualified name of the transactional deletion column
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn()
    {
        return "{$this->getTable()}.{$this->getDeletedAtColumn()}";
    }
}
