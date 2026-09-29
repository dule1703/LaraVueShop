<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot adrese isporuke u trenutku porudžbine — isti obrazac kao
     * order_items.product_name/product_price: kasnija izmena ili brisanje
     * sačuvane adrese NIKAD ne sme da promeni kako istorijska porudžbina izgleda.
     * Sve kolone su nullable — postojeće porudžbine ostaju NULL.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_recipient_name')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->string('shipping_line1')->nullable();
            $table->string('shipping_line2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->string('shipping_country')->nullable();
            // ISKLJUČIVO audit trag (koja sačuvana adresa je korišćena) — nikad se
            // ne čita za prikaz porudžbine; izvor istine su shipping_* kolone iznad.
            $table->foreignId('shipping_address_id')->nullable()->constrained('addresses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_address_id');
            $table->dropColumn([
                'shipping_recipient_name',
                'shipping_phone',
                'shipping_line1',
                'shipping_line2',
                'shipping_city',
                'shipping_postal_code',
                'shipping_country',
            ]);
        });
    }
};
