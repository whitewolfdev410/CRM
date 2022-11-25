<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InitTechStatusDateFromHistory extends Migration
{
    /**
     * Run the migrations.
     * @return void
     * @throws ErrorException
     */
    public function up()
    {
        $primaryKeyName = 'link_person_wo_id';

        if (!Schema::hasTable('link_person_wo')) {
            throw new ErrorException("link_person_wo not found");
        }

        DB::unprepared("
UPDATE LOW_PRIORITY `link_person_wo` 
SET `tech_status_date` = (SELECT `date_created`
                                FROM   history
                                WHERE  `tablename` = 'link_person_wo'
                                       AND `record_id` = `link_person_wo`.`link_person_wo_id`
                                       AND `columnname` = 'tech_status_type_id'
                                ORDER  BY `date_created` DESC
                                LIMIT  1)
WHERE `tech_status_date` IS NULL
     AND `tech_status_type_id` IS NOT NULL;");
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
