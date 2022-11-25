<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCompletedDateColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $columns = Schema::getColumnListing('work_order');

        Schema::table('work_order', function (Blueprint $table) use ($columns) {
            if (!in_array('completed_date', $columns)) {
                $table->dateTime('completed_date')
                    ->nullable();
            }
        });

        DB::unprepared('DROP TRIGGER IF EXISTS update_pickup_date_BEFORE_INSERT');
        DB::unprepared('DROP TRIGGER IF EXISTS update_status_date_BEFORE_INSERT');
        DB::unprepared('
            CREATE TRIGGER `update_status_date_BEFORE_INSERT` BEFORE INSERT ON `work_order` FOR EACH ROW
            BEGIN
                SET @completedStatusTypeId = (SELECT type_id FROM type WHERE type_key = "wo_status.completed" LIMIT 1);
            
                IF NEW.pickup_id > 0 THEN
                    set NEW.pickup_date = now();
                END IF;
                
                IF NEW.wo_status_type_id = @completedStatusTypeId THEN
                    set NEW.completed_date = now();
                END IF;
            END
        ');

        DB::unprepared('DROP TRIGGER IF EXISTS update_pickup_date_BEFORE_UPDATE');
        DB::unprepared('DROP TRIGGER IF EXISTS update_status_date_BEFORE_UPDATE');
        DB::unprepared('
            CREATE TRIGGER `update_status_date_BEFORE_UPDATE` BEFORE UPDATE ON `work_order` FOR EACH ROW
            BEGIN
                SET @completedStatusTypeId = (SELECT type_id FROM type WHERE type_key = "wo_status.completed" LIMIT 1);
            
                IF OLD.pickup_id = 0 AND NEW.pickup_id > 0 THEN
                    set NEW.pickup_date = now();
                END IF;
                
                IF NEW.wo_status_type_id = @completedStatusTypeId AND NEW.wo_status_type_id <> OLD.wo_status_type_id THEN
                    set NEW.completed_date = now();
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
