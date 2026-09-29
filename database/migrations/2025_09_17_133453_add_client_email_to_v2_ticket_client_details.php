<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_client_email_to_v2_ticket_client_details.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('v2_ticket_client_details', function (Blueprint $table) {
            $table->string('client_email', 191)->nullable()->after('client_empID');
            $table->index('client_email');
        });
    }
    public function down(): void {
        Schema::table('v2_ticket_client_details', function (Blueprint $table) {
            $table->dropIndex(['client_email']);
            $table->dropColumn('client_email');
        });
    }
};
