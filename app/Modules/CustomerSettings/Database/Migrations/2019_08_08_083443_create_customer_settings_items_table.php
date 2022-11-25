<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCustomerSettingsItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'id';

        if (!Schema::hasTable('customer_settings_items')) {
            Schema::create(
                'customer_settings_items',
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing('customer_settings_items');

        Schema::table(
            'customer_settings_items',
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }
                if (!in_array('customer_settings_id', $columns)) {
                    $table->integer('customer_settings_id')
                        ->unsigned()
                        ->index('customer_settings_id');
                }
                if (!in_array('key', $columns)) {
                    $table->string('key', 255)
                        ->index('customer_settings_items_key');
                }
                if (!in_array('value', $columns)) {
                    $table->string('value', 255)
                        ->nullable();
                }
                if (!in_array('created_at', $columns)) {
                    $table->dateTime('created_at')
                        ->nullable();
                }
                if (!in_array('updated_at', $columns)) {
                    $table->dateTime('updated_at')
                        ->nullable();
                }

                $table->foreign('customer_settings_id')
                    ->references('customer_settings_id')->on('customer_settings')
                    ->onUpdate('NO ACTION')
                    ->onDelete('CASCADE');
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
