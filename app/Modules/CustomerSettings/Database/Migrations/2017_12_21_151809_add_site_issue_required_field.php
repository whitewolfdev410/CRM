<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSiteIssueRequiredField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('customer_settings')) {
            $columns = Schema::getColumnListing('customer_settings');
            Schema::table(
                'customer_settings',
                function (Blueprint $table) use ($columns) {
                    if (!in_array('site_issue_required', $columns)) {
                        $table->boolean('site_issue_required')->default(0);
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
    }
}
