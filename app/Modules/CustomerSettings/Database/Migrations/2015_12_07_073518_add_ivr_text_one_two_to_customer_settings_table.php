<?php

use App\Core\Crm;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIvrTextOneTwoToCustomerSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /** @var Crm $crm */
        $crm = App::make(Crm::class);

        // at the moment those columns should be only for GFS
        if ($crm->is('gfs')) {
            $primaryKeyName = 'customer_settings_id';

            if (!Schema::hasTable('customer_settings')) {
                Schema::create(
                    'customer_settings',
                    function (Blueprint $table) use ($primaryKeyName) {
                        $table->increments($primaryKeyName);
                    }
                );
            }

            $columns = Schema::getColumnListing('customer_settings');

            Schema::table(
                'customer_settings',
                function (Blueprint $table) use ($columns, $primaryKeyName) {
                    if (!in_array($primaryKeyName, $columns)) {
                        $table->increments($primaryKeyName);
                    }
                    if (!in_array('ivr_text_one', $columns)) {
                        $table->binary('ivr_text_one')->nullable()
                            ->default(null)
                            ->after('accept_work_order_invitation');
                    }
                    if (!in_array('ivr_text_two', $columns)) {
                        $table->binary('ivr_text_two')->nullable()
                            ->default(null)->after('ivr_text_one');
                    }
                }
            );
        }
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
