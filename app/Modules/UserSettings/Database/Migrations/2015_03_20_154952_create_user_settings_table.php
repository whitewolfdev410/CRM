<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('user_settings')) {
            Schema::create('user_settings', function (Blueprint $table) {
                $table->bigIncrements('user_settings_id');
            });
        }

        $columns = Schema::getColumnListing('user_settings');

        Schema::table(
            'user_settings',
            function (Blueprint $table) use ($columns) {
                if (!in_array('user_settings_id', $columns)) {
                    $table->bigIncrements('user_settings_id');
                }
                if (!in_array('person_id', $columns)) {
                    $table->integer('person_id')->unsigned();
                }
                if (!in_array('field_name', $columns)) {
                    $table->string('field_name', 50)->nullable();
                }
                if (!in_array('value', $columns)) {
                    $table->string('value', 200)->nullable();
                }

                if (!in_array('created_at', $columns) && !in_array('updated_at', $columns)) {
                    $table->timestamps();
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
    }
}
