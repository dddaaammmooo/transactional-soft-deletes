<?php

namespace Dddaaammmooo\TransactionalSoftDeletes\Models;

use Carbon\Carbon;
use Dddaaammmooo\TransactionalSoftDeletes\DatabaseTrait;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class DeleteTransaction
 *
 * Model that stores the details of who and when a model(s) were deleted, along with the details of
 * who and when the model(s) were later recovered (if applicable)
 *
 * @property int         $id
 * @property int         $deletedById  ID of the user that deleted
 * @property Carbon      $deletedAt    Date/Time the deletion was made
 * @property Carbon|null $restoredAt   ID of the user that recovered the deleted items
 * @property int|null    $restoredById Date/Time the recovery was done
 *
 * @method static Builder|DeleteTransaction whereDeletedAt($value)
 * @method static Builder|DeleteTransaction whereDeletedById($value)
 * @method static Builder|DeleteTransaction whereId($value)
 * @method static Builder|DeleteTransaction whereRestoredAt($value)
 * @method static Builder|DeleteTransaction whereRestoredById($value)
 *
 * @mixin \Eloquent
 *
 * @package Dddaaammmooo\TransactionalSoftDeletes
 */
class DeleteTransaction extends Eloquent
{
    use DatabaseTrait;

    /** @var string $table */
    protected $table = 'delete_transaction';

    /** @var bool $timestamps */
    public $timestamps = false;

    /** @var array $guarded */
    protected $guarded = ['id',];
}
