<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Betriebshöfe: mehrere je Firma, höchstens einer ist der Standard (is_default).
        // Die Koordinaten stehen nicht hier, sondern im verknüpften Ort (places).
        Schema::create('depots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('address');                                      // so eingegeben, daraus wird der Ort gesucht
            $table->foreignUuid('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depots');
    }
};
