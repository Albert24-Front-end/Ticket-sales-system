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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained("users")->restrictOnDelete();
            $table->foreignId("event_id")->constrained("events")->restrictOnDelete();
            $table->foreignId("ticket_type_id")->constrained("ticket_types")->restrictOnDelete();
            $table->foreignId("order_id")->constrained("orders")->restrictOnDelete();
            $table->integer("ticket_index"); // Порядковый номер билета внутри конкретного TicketType этого заказа
            $table->string("number")->unique(); // публичный уникальный номер билета
            $table->string("status")->default("active"); // для EventCancelled
            $table->boolean("refund_required")->default(false);

            $table->unique(["order_id", "ticket_type_id", "ticket_index"]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
