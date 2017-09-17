<?php

namespace Dddaaammmooo\TransactionalSoftDeletes;

use Carbon\Carbon;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class DeleteTransactionLog
 *
 * Model that stores the details of the individual rows that were deleted from the database. This id done to
 * facilitate the easier recovery of bulk deletions.
 *
 * @property int         $id
 * @property int         $deleteTransactionId ID of the corresponding transaction in DatabaseTransaction model
 * @property string      $modelClass          Fully qualified class name of model that was deleted
 * @property int         $rowId               ID of the database model that was deleted
 * @property Carbon|null $restoredAt          Date/Time the recovery was made (if any)
 * @property int|null    $restoredById        ID of the user that recovered the model
 *
 * @method static Builder|DeleteTransactionLog whereDeleteTransactionId($value)
 * @method static Builder|DeleteTransactionLog whereId($value)
 * @method static Builder|DeleteTransactionLog whereModelClass($value)
 * @method static Builder|DeleteTransactionLog whereRestoredAt($value)
 * @method static Builder|DeleteTransactionLog whereRestoredById($value)
 * @method static Builder|DeleteTransactionLog whereRowId($value)
 *
 * @mixin \Eloquent
 *
 * @package Dddaaammmooo\TransactionalSoftDeletes
 */
class DeleteTransactionLog extends Eloquent
{
    use CamelCaseAttributesTrait;

    /** @var string $table */
    protected $table = 'delete_transaction_log';

    /** @var bool $timestamps */
    public $timestamps = false;

    /** @var array $guarded */
    protected $guarded = [
        'id',
    ];
}
