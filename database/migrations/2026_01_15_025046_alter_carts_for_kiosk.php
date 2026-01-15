<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {

            // ❌ remove user-based logic
            if (Schema::hasColumn('carts', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }

            // ✅ add kiosk session fields
            $table->uuid('session_key')->unique()->after('id');
            $table->string('status')->default('ACTIVE')->after('session_key');
            $table->timestamp('last_activity_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn(['session_key', 'status', 'last_activity_at']);

            // optional rollback
            $table->foreignId('user_id')->nullable()->constrained('users');
        });
    }
};
