<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPickupDateColumn extends Migration
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
            if (!in_array('pickup_date', $columns)) {
                $table->dateTime('pickup_date')
                    ->after('pickup_id')
                    ->nullable();
            }
        });

        DB::unprepared('DROP TRIGGER IF EXISTS update_pickup_date_BEFORE_INSERT');
        DB::unprepared('
            CREATE TRIGGER `update_pickup_date_BEFORE_INSERT` BEFORE INSERT ON `work_order` FOR EACH ROW
            BEGIN
                IF NEW.pickup_id > 0 THEN
                    set NEW.pickup_date = now();
                END IF;
            END
        ');

        DB::unprepared('DROP TRIGGER IF EXISTS update_pickup_date_BEFORE_UPDATE');
        DB::unprepared('
            CREATE TRIGGER `update_pickup_date_BEFORE_UPDATE` BEFORE UPDATE ON `work_order` FOR EACH ROW
            BEGIN
                IF OLD.pickup_id = 0 AND NEW.pickup_id > 0 THEN
                    set NEW.pickup_date = now();
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
