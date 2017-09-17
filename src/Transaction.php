<?php

namespace Dddaaammmooo\TransactionalSoftDeletes;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Connection;

/**
 * Class Transaction
 *
 * Provides methods to track the current delete transaction ID/timestamp and provides methods for the
 * later recovery of a delete transaction
 *
 * @package Dddaaammmooo\TransactionalSoftDeletes
 */
class Transaction
{
    /** @var int $deleteTransactionId The current delete transaction ID */
    private static $deleteTransactionId;

    /** @var Carbon $timestamp The current delete transaction timestamp */
    private static $timestamp;

    /** @var array $traits Cache of the traits used by models we are recovering */
    private static $traits = [];

    /** @var callable $callbackGetUserId Function to be executed to get the logged in user ID */
    private static $callbackGetUserId;

    /**
     * Return the current delete transaction ID, creating a new ID if none currently exists
     *
     * @return int
     */
    public static function getDeleteTransactionId(): int
    {
        if (isset(self::$deleteTransactionId))
        {
            return self::$deleteTransactionId;
        }

        return self::newDeleteTransactionId();
    }

    /**
     * Create a new delete transaction ID
     *
     * @return int
     */
    public static function newDeleteTransactionId(): int
    {
        // Create record of new transaction in the database

        $deleteTransaction = new DeleteTransaction();
        $deleteTransaction->deletedById = call_user_func(config('TransactionalSoftDeletes.callback_get_user_id'));
        $deleteTransaction->save();

        // Store the transaction ID for subsequent calls

        self::$deleteTransactionId = $deleteTransaction->id;

        // Since we are starting a new transaction we should generate a new timestamp to go with it

        self::newTimestamp();

        // Return the transaction ID

        return $deleteTransaction->id;
    }

    /**
     * Returns a timestamp common to the delete transaction
     *
     * @return Carbon
     */
    public static function getTimestamp(): Carbon
    {
        if (isset(self::$timestamp))
        {
            return self::$timestamp;
        }

        return self::newTimestamp();
    }

    /**
     * Generate a new timestamp
     *
     * @return Carbon
     */
    public static function newTimestamp(): Carbon
    {
        self::$timestamp = new Carbon;

        return self::$timestamp;
    }

    /**
     * Purge all transactions from the database tables
     */
    public static function truncate(): void
    {
        DeleteTransaction::truncate();
        DeleteTransactionLog::truncate();
    }

    /**
     * Restore all records deleted by a transaction in bulk
     *
     * @param int $deleteTransactionId
     * @return bool
     */
    public static function restoreTransaction(int $deleteTransactionId): bool
    {
        /** @var Connection $connection */
        $connection = DeleteTransaction::getModel()->getConnection();

        // Wrap the restoration inside a transaction in case something goes wrong

        try
        {
            $connection->beginTransaction();
        }
        catch (Exception $e)
        {
            // Unable to wrap deletion in a database transaction, cannot proceed for safety reasons

            return false;
        }

        // Identify all records that were deleted in the same transaction

        $deleteTransactionLogs = DeleteTransactionLog::whereDeleteTransactionId($deleteTransactionId)->get();

        // Iterate through all of the deleted items

        foreach ($deleteTransactionLogs as $deleteTransactionLog)
        {
            // Because the object type is read from the database it is possible to get replaced by something
            // we can't actually restore from. For safety I'm going to ensure the model is actually capable of
            // restoration before using it

            if (!in_array($deleteTransactionLog->modelClass, self::$traits))
            {
                // We haven't already checked if this class uses the trait, perform the search

                $traits = class_uses_recursive($deleteTransactionLog->modelClass);

                if (in_array(Model::class, $traits))
                {
                    self::$traits[] = $deleteTransactionLog->modelClass;
                }
                else
                {
                    // It didn't use the trait, so we cannot guarantee it will work- fail gracefully

                    $connection->rollBack();

                    return false;
                }
            }

            // Restore each item by using the model class and row ID stored in the `delete_transaction_log` table

            /** @var Model $model */
            $model = call_user_func([$deleteTransactionLog->modelClass, 'withTrashed'])
                ->whereId($deleteTransactionLog->rowId)
                ->first();

            // If something went wrong, rollback the action

            if (!$model->restore())
            {
                $connection->rollBack();

                return false;
            }
        }

        // Now that everything has been restored, mark the whole transaction as restored

        $deleteTransaction = DeleteTransaction::whereId($deleteTransactionId)->first();
        $deleteTransaction->restored_at = Transaction::getTimestamp();

        // If something went wrong, rollback the action

        if (!$deleteTransaction->save())
        {
            $connection->rollBack();

            return false;
        }

        // Persist the changes to the database

        $connection->commit();

        return true;
    }

    /**
     * Delete transaction from database if there are no remaining records associated with it
     *
     * @param int $deleteTransactionId
     * @return int The number of records remaining in the transaction
     */
    public static function deleteIfEmpty(int $deleteTransactionId): int
    {
        $countRemaining = DeleteTransactionLog::whereDeleteTransactionId($deleteTransactionId)
                                              ->whereNull('restored_at')
                                              ->count();

        if ($countRemaining == 0)
        {
            // There were no remaining records, we can delete the transaction

            $deleteTransaction = DeleteTransaction::whereId($deleteTransactionId)->first();

            if ($deleteTransaction)
            {
                $deleteTransaction->restoredById = call_user_func(config('TransactionalSoftDeletes.callback_get_user_id'));
                $deleteTransaction->restoredAt = self::getTimestamp();
                $deleteTransaction->save();
            }
        }

        return $countRemaining;
    }

    protected static function getUserId()
    {
        if (!isset(self::$callbackGetUserId))
        {
            self::$callbackGetUserId = config('TransactionalSoftDeletes.callback_get_user_id');

            // If no callback is defined we will because to storing null in the deleted_by_id column

            if (!is_callable(self::$callbackGetUserId))
            {
                self::$callbackGetUserId = function ()
                {
                    return null;
                };
            }
        }

        return call_user_func(self::$callbackGetUserId);
    }
}
