<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddEmployeeSalaryColumnsToPersonTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'person_id';
        $tableName = 'person';

        if (!Schema::hasTable($tableName)) {
            Schema::create(
                $tableName,
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing($tableName);

        Schema::table(
            $tableName,
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                //employee_tariff_rate

                if (!in_array('employee_tariff_rate', $columns)) {
                    $table->decimal('employee_tariff_rate', 10)->nullable()->default(0);
                }

                //employee_tariff_type_id
                if (!in_array('employee_tariff_rate', $columns)) {
                    $table->integer('employee_tariff_type_id')->nullable()->default(0)->index('employee_tariff_type_id_i');
                }
                //employee_minimum_stops
                if (!in_array('employee_minimum_stops', $columns)) {
                    $table->integer('employee_minimum_stops')->nullable()->default(0);
                }
                //employee_stops_rate
                if (!in_array('employee_stops_rate', $columns)) {
                    $table->decimal('employee_stops_rate', 10)->nullable()->default(0);
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
