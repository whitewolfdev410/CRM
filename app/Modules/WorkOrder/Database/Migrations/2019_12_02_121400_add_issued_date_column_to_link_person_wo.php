<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIssuedDateColumnToLinkPersonWo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $columns = Schema::getColumnListing('link_person_wo');

        Schema::table('link_person_wo', function (Blueprint $table) use ($columns) {
            if (!in_array('completed_date', $columns)) {
                $table->dateTime('completed_date')
                    ->nullable();
            }
        });

        Schema::table('link_person_wo', function (Blueprint $table) use ($columns) {
            if (!in_array('issued_date', $columns)) {
                $table->dateTime('issued_date')
                    ->nullable();
            }
        });

        DB::unprepared('DROP TRIGGER IF EXISTS update_completed_date');
        DB::unprepared('DROP TRIGGER IF EXISTS update_status_date');
        DB::unprepared('
            CREATE TRIGGER `update_status_date` BEFORE UPDATE ON `link_person_wo` 
                FOR EACH ROW BEGIN 
                
                SET @completedStatusTypeId = (SELECT type_id FROM type WHERE type_key = "wo_vendor_status.completed" LIMIT 1);
                SET @issuedStatusTypeId = (SELECT type_id FROM type WHERE type_key = "wo_vendor_status.issued" LIMIT 1);
                
                IF IFNULL(NEW.status_type_id, 0) = @completedStatusTypeId AND IFNULL(NEW.status_type_id, 0) <> IFNULL(OLD.status_type_id, 0) THEN
                    SET NEW.completed_date = NOW();
                END IF;
                
                IF IFNULL(NEW.status_type_id, 0) = @issuedStatusTypeId AND IFNULL(NEW.status_type_id, 0) <> IFNULL(OLD.status_type_id, 0) THEN
                    SET NEW.issued_date = NOW();
                END IF;
            END
        ');
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
