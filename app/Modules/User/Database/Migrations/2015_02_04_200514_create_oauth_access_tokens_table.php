<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOauthAccessTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('oauth_access_tokens')) {
            Schema::create('oauth_access_tokens', function (Blueprint $table) {
                $table->string('id', 40)->primary();
                $table->integer('user_id')->unsigned();
                $table->integer('expire_time');
                $table->string('device_type', 16);
                $table->string('device_id', 256)->default('');
                $table->string('ip_address', 45)->default('');
    
                $table->timestamps();
    
                $table->unique(['id', 'user_id']);
                $table->index('user_id');
    
                $table->foreign('user_id')
                    ->references('id')->on('users')
                    ->onDelete('cascade');
            });
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
