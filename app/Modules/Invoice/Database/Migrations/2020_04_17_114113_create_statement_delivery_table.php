<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStatementDeliveryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'id';
    
        if (!Schema::hasTable('statement_delivery')) {
            Schema::create(
                'statement_delivery',
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing('statement_delivery');

        Schema::table(
            'statement_delivery',
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }
                if (!in_array('company_person_id', $columns)) {
                    $table->integer('company_person_id')->unsigned()->index();
                }
                if (!in_array('method', $columns)) {
                    $table->string('method', 30)->index();
                }
                if (!in_array('method_detail', $columns)) {
                    $table->string('method_detail', 100)->nullable()->index();
                }
                if (!in_array('success', $columns)) {
                    $table->boolean('success')->default(0)->index();
                }
                if (!in_array('status', $columns)) {
                    $table->text('status')->nullable();
                }
                if (!in_array('status_timestamp', $columns)) {
                    $table->timestamp('status_timestamp')->index();
                }
                if (!in_array('created_at', $columns)) {
                    $table->timestamp('created_at');
                }
                if (!in_array('updated_at', $columns)) {
                    $table->timestamp('updated_at');
                }
            }
        );
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down()
    {
        /* we need to assume everything could exist so cannot reverse it */
    }
}
