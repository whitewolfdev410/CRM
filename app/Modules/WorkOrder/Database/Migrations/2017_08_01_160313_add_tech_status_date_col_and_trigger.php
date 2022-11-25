<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTechStatusDateColAndTrigger extends Migration
{
    /**
     * Run the migrations.
     * @return void
     * @throws ErrorException on error
     */
    public function up()
    {
        $primaryKeyName = 'link_person_wo_id';
    
        if (!Schema::hasTable('link_person_wo')) {
            throw new ErrorException("link_person_wo not found");
        }

        $columns = Schema::getColumnListing('link_person_wo');

        // Add a column
        Schema::table('link_person_wo', function (Blueprint $table) use ($columns, $primaryKeyName) {
            if (!in_array($primaryKeyName, $columns)) {
                $table->increments($primaryKeyName);
            }

            if (!in_array('tech_status_date', $columns)) {
                $table->dateTime('tech_status_date')
                    ->nullable()
                    ->comment('Date of the last tech_status_type_id change')
                    ->index('tech_status_date_i');
            }
        });

        // Add a trigger
        DB::unprepared('DROP TRIGGER IF EXISTS `update_tech_status_date`');
        DB::unprepared('CREATE TRIGGER update_tech_status_date BEFORE UPDATE ON `link_person_wo` FOR EACH ROW
BEGIN 
    IF NOT (NEW.tech_status_type_id <=> OLD.tech_status_type_id) THEN 
        SET NEW.tech_status_date = NOW(); 
    END IF; 
END');
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
