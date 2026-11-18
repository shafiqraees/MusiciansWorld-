<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('sms_consent')->default(false)->after('customer_phone');
            $table->dateTime('sms_consent_at')->nullable()->after('sms_consent');
            $table->dateTime('sms_confirmation_sent_at')->nullable()->after('sms_consent_at');
            $table->dateTime('sms_reminder_sent_at')->nullable()->after('sms_confirmation_sent_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['sms_reminder_sent_at']);
            $table->dropColumn([
                'sms_consent',
                'sms_consent_at',
                'sms_confirmation_sent_at',
                'sms_reminder_sent_at',
            ]);
        });
    }
};
