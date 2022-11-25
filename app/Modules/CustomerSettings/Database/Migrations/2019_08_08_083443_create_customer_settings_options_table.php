<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCustomerSettingsOptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'id';

        if (!Schema::hasTable('customer_settings_options')) {
            Schema::create(
                'customer_settings_options',
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing('customer_settings_options');

        Schema::table(
            'customer_settings_options',
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }
                if (!in_array('key', $columns)) {
                    $table->string('key', 255)
                        ->index('customer_settings_options_key');
                }
                if (!in_array('label', $columns)) {
                    $table->string('label', 255);
                }
                if (!in_array('type', $columns)) {
                    $table->string('type', 30)
                        ->default('text');
                }
                if (!in_array('options', $columns)) {
                    $table->text('options')
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
