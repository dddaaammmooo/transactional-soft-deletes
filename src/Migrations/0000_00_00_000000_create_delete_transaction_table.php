<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Class CreateDeleteTransactionTable
 *
 * The 'delete_transaction' table contains the 'delete_transaction_id' for all soft deletes in the database along
 * with the ID of the user the deleted the record(s) and the time/date at which the record(s) wer deleted
 */
class CreateDeleteTransactionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delete_transaction', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('deleted_by_id');
            $table->timestamp('deleted_at')->useCurrent();
            $table->timestamp('restored_at')->nullable();
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
        Schema::dropIfExists('delete_transaction');
    }
}
