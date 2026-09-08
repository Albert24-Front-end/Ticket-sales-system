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
        Schema::create("events", function (Blueprint $table) {
            $table->id();
            $table->foreignId("organizer_id")->constrained("users");
            $table->string("name");
            $table->text("description")->nullable();
            $table->dateTime("started_at");
            $table->dateTime("ended_at");
            $table->foreignId("venue_id")->constrained("venues");
            $table->foreignId("category_id")->constrained("categories");
            $table->string("status");
            $table->timestamps();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("events");
    }
};
