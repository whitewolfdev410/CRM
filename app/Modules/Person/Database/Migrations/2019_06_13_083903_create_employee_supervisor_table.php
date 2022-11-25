<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmployeeSupervisorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'person_employee_supervisor_id';
    
        if (!Schema::hasTable('person_employee_supervisor')) {
            Schema::create(
                'person_employee_supervisor',
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing('person_employee_supervisor');

        Schema::table(
            'person_employee_supervisor',
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }

                if (!in_array('employee_id', $columns)) {
                    $table
                        ->integer('employee_id')
                        ->index();
                }
                
                if (!in_array('supervisor_id', $columns)) {
                    $table
                        ->integer('supervisor_id')
                        ->default(null)
                        ->nullable()
                        ->index();
                }

                if (!in_array('depth', $columns)) {
                    $table
                        ->integer('depth')
                        ->default(0)
                        ->index();
                }
                
                if (!in_array('date_created', $columns)) {
                    $table
                        ->dateTime('date_created')
                        ->nullable()
                        ->default('0000-00-00 00:00:00');
                }

                if (!in_array('date_modified', $columns)) {
                    $table
                        ->dateTime('date_modified')
                        ->nullable()
                        ->default('0000-00-00 00:00:00');
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
