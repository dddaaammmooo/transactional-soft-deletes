<?php

namespace App\Cto\Database\TransactionalSoftDeletes;
use Eloquent;

/**
 * App\Cto\Database\TransactionalSoftDeletes\DeleteTransaction
 *
 * @property int $id
 * @property int $deletedById
 * @property string $deletedAt
 * @property string|null $restoredAt
 * @property int|null $restoredById
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cto\Database\TransactionalSoftDeletes\DeleteTransaction whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cto\Database\TransactionalSoftDeletes\DeleteTransaction whereDeletedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cto\Database\TransactionalSoftDeletes\DeleteTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cto\Database\TransactionalSoftDeletes\DeleteTransaction whereRestoredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cto\Database\TransactionalSoftDeletes\DeleteTransaction whereRestoredById($value)
 * @mixin \Eloquent
 */
class DeleteTransaction extends Eloquent
{
    // Along with the date/time of the action, the delete_transaction table stores the identity of the person that
    // initiated the deletion

    /** @var string $table */
    protected $table = 'delete_transaction';

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
