<?php

return [

    // Update the following to the name of the table column to use when marking records as deleted

    'deleted_at_column' => 'delete_transaction_id',

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
