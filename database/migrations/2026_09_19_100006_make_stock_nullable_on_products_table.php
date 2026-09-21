<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * products.stock: NULL = neograničene zalihe (buduće e-knjige).
 *
 * Kolona je već kreirana kao `integer NULL DEFAULT 0` (2025_12_24_115233), pa je
 * ova migracija namerno idempotentna: eksplicitno fiksira željenu definiciju
 * (tip, nullable, default 0), bez obzira na to kakvo je stanje na serveru.
 * Default ostaje 0, ne NULL — proizvod kreiran bez eksplicitnih zaliha ne sme
 * slučajno postati "neograničen".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock')->nullable()->default(0)->change();
        });
    }

    /**
     * Namerno prazno: originalna kolona je takođe bila nullable, a vraćanje na
     * NOT NULL bi puklo čim postoji ijedan proizvod sa neograničenim zalihama.
     */
    public function down(): void
    {
        //
    }
};
