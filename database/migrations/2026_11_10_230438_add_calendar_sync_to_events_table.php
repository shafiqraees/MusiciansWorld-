<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'calendar_event_id')) {
                $table->string('calendar_event_id')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('events', 'calendar_sync_status')) {
                $table->string('calendar_sync_status', 30)->default('pending')->after('calendar_event_id');
            }
            if (! Schema::hasColumn('events', 'calendar_sync_error')) {
                $table->text('calendar_sync_error')->nullable()->after('calendar_sync_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['calendar_event_id', 'calendar_sync_status', 'calendar_sync_error']);
        });
    }
};
