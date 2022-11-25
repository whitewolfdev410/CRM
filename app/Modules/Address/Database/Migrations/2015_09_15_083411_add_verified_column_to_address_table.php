<?php

use App\Modules\Address\Models\AddressVerifyStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVerifiedColumnToAddressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('address')) {
            Schema::table('address', function (Blueprint $table) {
                if (!Schema::hasColumn('address', 'verified')) {
                    $table->tinyInteger('verified')
                        ->default(AddressVerifyStatus::NOT_VERIFIED);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // don't remove column - it might existed before migration
    }
}
