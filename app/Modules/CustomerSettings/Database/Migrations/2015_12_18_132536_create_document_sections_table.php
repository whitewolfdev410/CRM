<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDocumentSectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('document_sections')) {
            Schema::create('document_sections', function (Blueprint $table) {
                $table->increments('id');
            });
        }

        $columns = Schema::getColumnListing('document_sections');

        Schema::table(
            'document_sections',
            function (Blueprint $table) use ($columns) {
                if (!in_array('id', $columns)) {
                    $table->increments('id');
                }

                if (!in_array('customer_setting_id', $columns)) {
                    $table->integer('customer_setting_id')->unsigned()
                        ->index('customer_setting_id');
                }

                if (!in_array('document', $columns)) {
                    $table->string('document', 32);
                }

                if (!in_array('section', $columns)) {
                    $table->string('section', 32);
                }

                if (!in_array('label', $columns)) {
                    $table->string('label', 128);
                }

                if (!in_array('content', $columns)) {
                    $table->string('content')->nullable();
                }

                if (!in_array('ordering', $columns)) {
                    $table->integer('ordering')->unsigned();
                }

                if (!in_array('created_date', $columns)) {
                    $table->timestamp('created_date')->nullable();
                }
                if (!in_array('updated_at', $columns)) {
                    $table->timestamp('updated_at');
                }
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
