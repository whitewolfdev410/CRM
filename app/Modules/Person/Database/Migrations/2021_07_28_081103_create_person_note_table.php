<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePersonNoteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'person_note_id';
    
        if (!Schema::hasTable('person_note')) {
            Schema::create('person_note', function (Blueprint $table) use ($primaryKeyName) {
                $table->increments($primaryKeyName);
            });
        }

        $columns = Schema::getColumnListing('person_note');

        Schema::table('person_note', function (Blueprint $table) use ($columns, $primaryKeyName) {
            if (!in_array($primaryKeyName, $columns)) {
                $table->increments($primaryKeyName);
            }

            if (!in_array('person_id', $columns)) {
                $table
                    ->integer('person_id')
                    ->index();
            }

            if (!in_array('note', $columns)) {
                $table->string('note');
            }

            if (!in_array('created_by', $columns)) {
                $table->integer('created_by');
            }
            
            if (!in_array('created_at', $columns)) {
                $table->dateTime('created_at');
            }

            if (!in_array('updated_at', $columns)) {
                $table->dateTime('updated_at')->nullable();
            }
        });
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
