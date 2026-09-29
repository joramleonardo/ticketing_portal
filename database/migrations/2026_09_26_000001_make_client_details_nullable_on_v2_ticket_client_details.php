<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE `v2_ticket_client_details`
            MODIFY `client_type` ENUM('Internal', 'External') NULL,
            MODIFY `client_name` VARCHAR(255) NULL,
            MODIFY `client_division_agency` VARCHAR(255) NULL,
            MODIFY `client_sex` VARCHAR(50) NULL
        ");
    }

    public function down(): void
    {
        // Keep nullable on rollback to avoid failing if null rows exist
    }
};
