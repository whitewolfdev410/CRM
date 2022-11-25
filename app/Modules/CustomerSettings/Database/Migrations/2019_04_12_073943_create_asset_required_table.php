<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAssetRequiredTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'asset_required_id';
    
        if (!Schema::hasTable('asset_required')) {
            Schema::create(
                'asset_required',
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing('asset_required');

        Schema::table(
            'asset_required',
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }
                if (!in_array('customer_settings_id', $columns)) {
                    $table->integer('customer_settings_id')->unsigned()
                        ->index('customer_settings_id');
                }
                if (!in_array('asset_system_type_id', $columns)) {
                    $table->integer('asset_system_type_id')->unsigned()
                        ->index('asset_system_type_id')->nullable();
                }
                if (!in_array('asset_required_type_id', $columns)) {
                    $table->integer('asset_required_type_id')->unsigned()
                        ->index('asset_required_type_id');
                }
                if (!in_array('color', $columns)) {
                    $table->string('color', 200)->nullable();
                }
                if (!in_array('created_at', $columns)) {
                    $table->dateTime('created_at')->nullable();
                }
                if (!in_array('updated_at', $columns)) {
                    $table->dateTime('updated_at')->nullable();
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
