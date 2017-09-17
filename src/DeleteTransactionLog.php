<?php

namespace App\Cto\Database\TransactionalSoftDeletes;

use Eloquent;

/**
 * App\Cto\Database\TransactionalSoftDeletes\DeleteTransactionLog
 *
 * @property int $id
 * @property int $deleteTransactionId
 * @property string $modelClass
 * @property int $rowId
 * @property string|null $restoredAt
 * @property int|null $restoredById
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cto\Database\TransactionalSoftDeletes\DeleteTransactionLog whereDeleteTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cto\Database\TransactionalSoftDeletes\DeleteTransactionLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cto\Database\TransactionalSoftDeletes\DeleteTransactionLog whereModelClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cto\Database\TransactionalSoftDeletes\DeleteTransactionLog whereRestoredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cto\Database\TransactionalSoftDeletes\DeleteTransactionLog whereRestoredById($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cto\Database\TransactionalSoftDeletes\DeleteTransactionLog whereRowId($value)
 * @mixin \Eloquent
 */
class DeleteTransactionLog extends Eloquent
{
    // Linking back to the delete_transaction table the delete_transaction_log table identifies the individual models
    // that were deleted as part of the transaction. This is done to facilitate the easy recovery of bulk deletions.

    /** @var string $table */
    protected $table = 'delete_transaction_log';

    /** @var bool $timestamps */
    public $timestamps = false;

    /** @var array $guarded */
    protected $guarded = [
        'id',
    ];

    // Allow for camelCased attribute access

    public function getAttribute($key)
    {
        return parent::getAttribute(snake_case($key));
    }

    public function setAttribute($key, $value)
    {
        return parent::setAttribute(snake_case($key), $value);
    }
}
