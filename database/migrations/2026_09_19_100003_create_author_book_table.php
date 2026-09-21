<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('author_book', function (Blueprint $table) {
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            // restrict: autor koji ima knjige ne može da se obriše tako da knjige tiho ostanu bez autora
            $table->foreignId('author_id')->constrained()->restrictOnDelete();
            $table->string('role', 20); // 'author' | 'translator' | 'illustrator' | 'editor'
            $table->unsignedSmallInteger('position')->default(0);

            // Isti autor može imati više uloga na istoj knjizi (npr. autor i ilustrator).
            $table->primary(['book_id', 'author_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_book');
    }
};
