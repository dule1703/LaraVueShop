<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            // Jedna knjiga po proizvodu; cena, zalihe, slika i aktivnost ostaju na products.
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('isbn13', 13)->nullable()->unique();
            $table->char('isbn10', 10)->nullable();
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subtitle')->nullable();
            $table->string('original_title')->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->unsignedSmallInteger('pages')->nullable();
            $table->string('language', 5);           // npr. 'sr', 'en'
            $table->string('script', 4)->nullable(); // 'Cyrl' | 'Latn' (ISO 15924)
            $table->string('format', 20);            // 'hardcover' | 'paperback' | 'ebook'
            $table->unsignedSmallInteger('weight_g')->nullable();
            // Normalizovan tekst za pretragu (ćirilica/latinica) — popunjava se u kasnijoj fazi.
            $table->text('search_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
