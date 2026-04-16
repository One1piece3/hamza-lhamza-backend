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
    Schema::create('products', function (Blueprint $table) {
        $table->id();

        $table->string('name'); // nom du produit
        $table->text('description')->nullable(); // description
        $table->decimal('price', 10, 2); // prix
        $table->string('image')->nullable(); // image
        $table->string('category')->nullable(); // categorie (veste, jogging...)
        $table->integer('stock')->default(0); // stock
        $table->string('size')->nullable(); // taille (S, M, L...)
        $table->string('color')->nullable(); // couleur
        $table->boolean('is_featured')->default(false); // produit en avant

        $table->timestamps();
    });
}
};
