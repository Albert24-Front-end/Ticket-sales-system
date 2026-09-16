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
        Schema::create("ticket_types", function (Blueprint $table) {
            $table->id();
            $table->foreignId("event_id")->constrained("events");
            $table->string("name");
            $table->text("description");
            $table->bigInteger("price");
            $table->integer("quantity");
            $table->integer("sold_quantity")->default(0);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("ticket_types");
    }
};
