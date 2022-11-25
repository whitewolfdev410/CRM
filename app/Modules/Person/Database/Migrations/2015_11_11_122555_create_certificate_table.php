<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCertificateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $primaryKeyName = 'certificate_id';
    
        if (!Schema::hasTable('certificate')) {
            Schema::create(
                'certificate',
                function (Blueprint $table) use ($primaryKeyName) {
                    $table->increments($primaryKeyName);
                }
            );
        }

        $columns = Schema::getColumnListing('certificate');

        Schema::table(
            'certificate',
            function (Blueprint $table) use ($columns, $primaryKeyName) {
                if (!in_array($primaryKeyName, $columns)) {
                    $table->increments($primaryKeyName);
                }
                if (!in_array('person_id', $columns)) {
                    $table->integer('person_id')->unsigned();
                    $table->foreign('person_id')->references('person_id')->on('person');
                }
                if (!in_array('type_id', $columns)) {
                    $table->integer('type_id')->unsigned()->index();
                }
                if (!in_array('expiration_date', $columns)) {
                    $table->date('expiration_date')->nullable();
                }
                if (!in_array('amount', $columns)) {
                    $table->double('amount');
                }
                if (!in_array('additional_insured_wording', $columns)) {
                    $table->tinyInteger('additional_insured_wording')->nullable();
                }
                if (!in_array('waiver_of_subrogation', $columns)) {
                    $table->tinyInteger('waiver_of_subrogation')->nullable();
                }
                if (!in_array('expired_creates_activity', $columns)) {
                    $table->tinyInteger('expired_creates_activity')->nullable();
                }
                if (!in_array('issue', $columns)) {
                    $table->tinyInteger('issue')->nullable();
                }
                if (!in_array('payment', $columns)) {
                    $table->tinyInteger('payment')->nullable();
                }
                if (!in_array('created_at', $columns) && !in_array('updated_at', $columns)) {
                    $table->timestamps();
                }
            }
        );
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
