<?php

namespace Dddaaammmooo\TransactionalSoftDeletes;

use Carbon\Carbon;
use Dddaaammmooo\TransactionalSoftDeletes\Models\DeleteTransaction;
use Dddaaammmooo\TransactionalSoftDeletes\Models\DeleteTransactionLog;
use Exception;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

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
    use ConfigTraits;

    /** @var int $deleteTransactionId The current delete transaction ID */
    private static $deleteTransactionId;

    /** @var Carbon $timestamp The current delete transaction timestamp */
    private static $timestamp;

    /** @var array $modelInheritance Cache of the models that have been validate as inheriting correctly */
    private static $modelInheritance = [];

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
        $deleteTransaction->setColumn('deleted_by_id', self::getUserId());
        $deleteTransaction->save();

        // Store the transaction ID in static variable for use in subsequent delete actions

        self::$deleteTransactionId = $deleteTransaction->id;

        // Since we are starting a new transaction we should generate a new timestamp to go with it

        self::newTimestamp();

        // Return the transaction ID

        return $deleteTransaction->getColumn('id');
    }

    /**
     * Returns a timestamp common to the delete transaction
     *
     * @return Carbon
     */
    public static function getTimestamp(): Carbon
    {
        // If no timestamp exists, or the automatic update of timestamps is enabled in the configuration, generate
        // a new timestamp

        if (isset(self::$timestamp) || config("transactional-soft-deletes.update_timestamp"))
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

        $deleteTransactionLogs = DeleteTransactionLog
            ::where(self::column('delete_transaction_id'), '=', $deleteTransactionId)
            ->whereNull(self::column('restored_at'))
            ->get();

        // Iterate through all of the deleted items

        foreach ($deleteTransactionLogs as $deleteTransactionLog)
        {
            // Because the object type is read from the database it is possible to get replaced by something
            // we can't actually restore from. For safety I'm going to ensure the model is actually capable of
            // restoration before using it

            $modelClass = $deleteTransactionLog->getColumn('model_class');

            if (!in_array($modelClass, self::$modelInheritance))
            {
                // We haven't already checked if this class uses the trait, perform the search

                if (is_subclass_of($modelClass, Model::class))
                {
                    self::$modelInheritance[] = $modelClass;
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
            $model = call_user_func(
                [$modelClass, 'withTrashed']
            )->whereId($deleteTransactionLog->getColumn('row_id'))
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
        $deleteTransaction->setColumn('restored_at', Transaction::getTimestamp());
        $deleteTransaction->setColumn('restored_by_id', self::getUserId());

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
     * Mark transaction as restored if there are no remaining deleted records associated with it
     *
     * @param int $deleteTransactionId
     * @return int The number of deleted records still in the transaction
     */
    public static function restoreTransactionContainerIfEmpty(int $deleteTransactionId): int
    {
        $countDeleted = self::getDeletedItemsCount($deleteTransactionId);

        if ($countDeleted == 0)
        {
            // There were no remaining records, we can mark the transaction as restored

            $deleteTransaction = DeleteTransaction::whereId($deleteTransactionId)->first();

            if ($deleteTransaction)
            {
                $deleteTransaction->setColumn('restored_by_id', self::getUserId());
                $deleteTransaction->setColumn('restored_at', self::getTimestamp());
                $deleteTransaction->save();
            }
        }

        return $countDeleted;
    }

    /**
     * Return the number of deleted items in the transaction
     *
     * @param int $deleteTransactionId
     * @return int
     */
    public static function getDeletedItemsCount(int $deleteTransactionId): int
    {
        return DeleteTransactionLog
            ::where(self::column('delete_transaction_id'), '=', $deleteTransactionId)
            ->whereNull(self::column('restored_at'))
            ->count();
    }

    /**
     * Returns a count of deleted items in the requested transaction split by their model class
     *
     * @param int $deleteTransactionId
     * @return array
     */
    public static function getDeletedItemsCountByClass(int $deleteTransactionId): array
    {
        $records = [];

        $arrangedCollection = self::getArrangedCollection($deleteTransactionId);

        // Iterate this array and retrieve the actual models that were deleted

        foreach ($arrangedCollection as $modelClass => $ids)
        {
            $records[$modelClass] = count($ids);
        }

        return $records;
    }

    /**
     * Returns an array of deleted objects indexed by their class as stored in the database
     *
     * Not-Hydrated
     *
     * [
     *      'App\Cat' => [1, 2, 3, 4],
     *      'App\Dog' => [1, 2]
     * ]
     *
     * Hydrated
     *
     * [
     *      'App\Cat' => [object(Cat)#1, object(Cat)#2, object(Cat)#3, object(Cat)#4],
     *      'App\Dog' => [object(Dog)#5, object(Dog)#6],
     * ]
     *
     * @param int  $deleteTransactionId
     * @param bool $hydrate
     * @return array
     */
    public static function getDeletedItems(int $deleteTransactionId, bool $hydrate = true): array
    {
        $records = [];

        // Grab the row ID and class of all deleted items in this transaction from the database

        $deletedCollection =
            DeleteTransactionLog::select(self::column('id'), self::column('model_class'))
                                ->groupBy(self::column('model_class'))
                                ->groupBy(self::column('id'))
                                ->where(self::column('delete_transaction_id'), '=', $deleteTransactionId)
                                ->get();

        // Convert the collection into an array of row ID's indexed by their model class

        $arrangedCollection = $deletedCollection->groupBy('model_class')
                                                ->transform(
                                                    function ($item, $key)
                                                    {
                                                        return $item->pluck(self::column('id'))->toArray();
                                                    }
                                                )
                                                ->toArray();

        // Iterate this array and retrieve the actual models that were deleted

        foreach ($arrangedCollection as $modelClass => $ids)
        {
            if ($hydrate)
            {
                /** @var Builder $builder */
                $builder = call_user_func([$modelClass, 'withTrashed']);
                $builder->whereIn(self::column('id'), $ids);
                $records[$modelClass] = $builder->get();
            }
            else
            {
                $records[$modelClass] = $ids;
            }
        }

        return $records;
    }

    /**
     * Create an array containing all row IDs deleted in the requested transaction indexed by their model class
     *
     * @param int $deleteTransactionId
     * @return array
     */
    private static function getArrangedCollection(int $deleteTransactionId): array
    {
        // Grab the row ID and class of all deleted items in this transaction from the database

        $deletedCollection =
            DeleteTransactionLog::select(self::column('id'), self::column('model_class'))
                                ->groupBy(self::column('model_class'))
                                ->groupBy(self::column('id'))
                                ->where(self::column('delete_transaction_id'), '=', $deleteTransactionId)
                                ->get();

        // Convert the collection into an array of row ID's indexed by their model class

        return $deletedCollection->groupBy('model_class')
                                 ->transform(
                                     function ($item, $key)
                                     {
                                         return $item->pluck(self::column('id'))->toArray();
                                     }
                                 )
                                 ->toArray();
    }
}
