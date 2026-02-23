<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
                Schema::table('orders', function (Blueprint $table) {
                $table->string('first_name')->nullable()->after('user_id');
                $table->string('last_name')->nullable()->after('first_name');
                $table->string('address')->nullable()->after('last_name');
                $table->string('city')->nullable()->after('address');
                $table->string('postal_code')->nullable()->after('city');
                $table->string('phone')->nullable()->after('postal_code');
                $table->text('notes')->nullable()->after('phone');                
                $table->string('customer_email')->nullable()->change();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'address',
                'city',
                'postal_code',
                'phone',
                'notes',
            ]);
        });
    }
};
