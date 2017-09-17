<?php

namespace App\Cto\Database\TransactionalSoftDeletes;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Implementation
 *
 * This class keeps track of the current transaction ID and timestamp
 *
 * @package App\Cto\Database\TransactionalSoftDelete
 */
class Transaction
{
    /** @var int $deleteTransactionId */
    private static $deleteTransactionId;

    /** @var Carbon $timestamp */
    private static $timestamp;

    /** @var array $traits */
    private static $traits;

    // ----------------------------------------------------------------------------------------------------------------
    // Delete Transaction ID
    // ----------------------------------------------------------------------------------------------------------------

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

        // No existing transaction ID

        return self::newDeleteTransactionId();
    }

    /**
     * Create a new delete transaction ID
     *
     * TODO: Populate deletedById
     *
     * @return int
     */
    public static function newDeleteTransactionId(): int
    {
        // Create record of new transaction in the database

        $deleteTransaction = new DeleteTransaction();
        $deleteTransaction->deletedById = 1;
        $deleteTransaction->save();

        // Store the transaction ID for subsequent calls

        self::$deleteTransactionId = $deleteTransaction->id;

        // Since we are starting a new transaction we should generate a new timestamp to go with it

        self::newTimestamp();

        // Return the transaction ID

        return $deleteTransaction->id;
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Timestamp
    // ----------------------------------------------------------------------------------------------------------------

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
        // Grab database connection

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

        /** @var DeleteTransactionLog $deleteTransactionLog */
        foreach ($deleteTransactionLogs as $deleteTransactionLog)
        {
            // Because the object type is taken from the database, for safety I'm going to ensure the model
            // is actually capable of restoration before doing it

            if (!isset(self::$traits[$deleteTransactionLog->modelClass]))
            {
                self::$traits[$deleteTransactionLog->modelClass] = class_uses_recursive($deleteTransactionLog->modelClass);
            }

            if (!in_array(TransactionalSoftDeletesTrait::class, self::$traits[$deleteTransactionLog->modelClass]))
            {
                // Something went wrong, rollback the action

                $connection->rollBack();

                return false;
            }

            // Restore each item by using the model class and row ID stored in the delete_transaciton_log table

            /** @var Model $model */
            $model = call_user_func([$deleteTransactionLog->modelClass, 'withTrashed'])
                ->whereId($deleteTransactionLog->rowId)
                ->first();

            if (!$model->restore())
            {
                // Something went wrong, rollback the action

                $connection->rollBack();

                return false;
            }
        }

        // Now that everything has been restored, mark the whole transaction as restored

        /** @var DeleteTransaction $deleteTransaction */
        $deleteTransaction = DeleteTransaction::whereId($deleteTransactionId)->first();
        $deleteTransaction->restored_at = Transaction::getTimestamp();

        if (!$deleteTransaction->save())
        {
            // Something went wrong, rollback the action

            $connection->rollBack();

            return false;
        }

        // Persist the changes to the database

        $connection->commit();

        return true;
    }
}
