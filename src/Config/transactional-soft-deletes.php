<?php

return [

    // The following defines the default column names used by the supplied database migration script, if any of
    // the column names are updated these values must be updated

    'column_id' => 'id',
    'column_delete_transaction_id' => 'delete_transaction_id',
    'column_deleted_at' => 'deleted_at',
    'column_deleted_by_id' => 'deleted_by_id',
    'column_restored_at' => 'restored_at',
    'column_restored_by_id' => 'restored_by_id',
    'column_row_id' => 'row_id',
    'column_model_class' => 'model_class',

    // If you want to automatically generate a new timestamp for each deletion change the option below

    'update_timestamp' => false,

    // Callback function used to retrieve the logged in users ID. If the value defined is not callable
    // the value will default to -1

    /**
     *  Examples:
     *
     *  'callback_get_user_id' => Auth::getUserId()
     *  'callback_get_user_id' => function() { return $userId; }
     */

    'callback_get_user_id' => null,
];
