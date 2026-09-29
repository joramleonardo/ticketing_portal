<?php
// database/migrations/xxxx_xx_xx_xxxxxx_make_ticket_type_nullable_on_v2_ticket_details.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('v2_ticket_details', 'ticket_type')) {
            try {
                Schema::table('v2_ticket_details', function (Blueprint $table) {
                    $table->string('ticket_type', 191)->nullable()->change();
                });
            } catch (\Throwable $e) {
                // Fallback for enum columns
                DB::statement("ALTER TABLE v2_ticket_details
                               MODIFY COLUMN ticket_type ENUM('Technical Support','Livestream') NULL");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('v2_ticket_details', 'ticket_type')) {
            try {
                Schema::table('v2_ticket_details', function (Blueprint $table) {
                    $table->string('ticket_type', 191)->nullable(false)->default('Technical Support')->change();
                });
            } catch (\Throwable $e) {
                DB::statement("ALTER TABLE v2_ticket_details
                               MODIFY COLUMN ticket_type ENUM('Technical Support','Livestream')
                               NOT NULL DEFAULT 'Technical Support'");
            }
        }
    }
};
