<?php

// database/migrations/xxxx_xx_xx_create_v2_ticket_messages.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('v2_ticket_messages', function (Blueprint $t) {
            $t->id();
            $t->string('client_referenceNo', 20)->index();
            $t->enum('sender_type', ['client','staff'])->index();
            $t->string('sender_name')->nullable();
            $t->text('message');
            $t->timestamps();

            $t->index(['client_referenceNo','id']);
        });
    }
    public function down(): void { Schema::dropIfExists('v2_ticket_messages'); }
};
