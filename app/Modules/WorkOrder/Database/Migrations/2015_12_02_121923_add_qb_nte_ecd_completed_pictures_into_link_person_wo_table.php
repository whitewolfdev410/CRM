<?php

use App\Core\Crm;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddQbNteEcdCompletedPicturesIntoLinkPersonWoTable extends Migration
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
            $primaryKeyName = 'link_person_wo_id';

            if (!Schema::hasTable('link_person_wo')) {
                Schema::create(
                    'link_person_wo',
                    function (Blueprint $table) use ($primaryKeyName) {
                        $table->increments($primaryKeyName);
                    }
                );
            }

            $columns = Schema::getColumnListing('link_person_wo');

            Schema::table(
                'link_person_wo',
                function (Blueprint $table) use ($columns, $primaryKeyName) {
                    if (!in_array($primaryKeyName, $columns)) {
                        $table->increments($primaryKeyName);
                    }
                    if (!in_array('qb_nte', $columns)) {
                        $table->decimal('qb_nte', 8, 2)->nullable()
                            ->default(null)->after('qb_info');
                    }
                    if (!in_array('qb_ecd', $columns)) {
                        $table->date('qb_ecd')->nullable()
                            ->default(null)->after('qb_nte');
                    }
                    if (!in_array('completed_pictures_received', $columns)) {
                        $table->enum(
                            'completed_pictures_received',
                            ['yes', 'no']
                        )->nullable()->default('no');
                    }
                    if (!in_array('completed_pictures_required', $columns)) {
                        $table->enum(
                            'completed_pictures_required',
                            ['yes', 'no']
                        )->default('no');
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
