<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDollarCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('dollar_customers')) {
            Schema::create('dollar_customers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('provider'); // 'sudo' or 'xixapay'
                $table->string('customer_id')->nullable(); // provider-side ID
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email');
                $table->string('phone');
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('date_of_birth')->nullable();
                $table->string('id_type')->nullable();
                $table->string('id_number')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();

                $table->unique(['user_id', 'provider']);
            });
        } else {
            // Add missing columns if table already exists
            if (!Schema::hasColumn('dollar_customers', 'city')) {
                Schema::table('dollar_customers', function (Blueprint $table) {
                    $table->string('city')->nullable()->after('address');
                });
            }
            if (!Schema::hasColumn('dollar_customers', 'state')) {
                Schema::table('dollar_customers', function (Blueprint $table) {
                    $table->string('state')->nullable()->after('city');
                });
            }
            if (!Schema::hasColumn('dollar_customers', 'date_of_birth')) {
                Schema::table('dollar_customers', function (Blueprint $table) {
                    $table->string('date_of_birth')->nullable()->after('state');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dollar_customers');
    }
}
