<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
        {
            Schema::create('usuario', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->boolean('activo')->default(true); // <-- ¡Esta es la columna que nos pedía el error!
                $table->timestamps();
            });
        }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('usuario');
    }
};
