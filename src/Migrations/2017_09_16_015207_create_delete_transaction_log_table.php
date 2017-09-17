<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Class CreateDeleteTransactionLogTable
 *
 * The 'delete_transaction_log' table contains the detail about which Models were deleted as part of a delete
 * transaction. This is designed to speed up the recovery process.
 */
class CreateDeleteTransactionLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delete_transaction_log', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('delete_transaction_id');
            $table->string('model_class');
            $table->integer('row_id');
            $table->dateTime('restored_at')->nullable();
            $table->integer('restored_by_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('delete_transaction_log');
    }
}
