<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            // restrict — isti obrazac kao order_items.product_id: istorija zaliha se ne briše sa proizvodom
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('delta'); // negativno = izlaz, pozitivno = ulaz
            $table->string('reason', 20); // 'order' | 'cancel' | 'payment_failed' | 'restock' | 'manual'
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            // nullOnDelete — korisnik može sam da obriše nalog (Breeze profil), a zapis ostaje
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
