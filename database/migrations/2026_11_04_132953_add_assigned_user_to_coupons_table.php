<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('assigned_email')->constrained('users')->nullOnDelete();
        });

        DB::table('coupons')->whereNotNull('assigned_email')->orderBy('id')->chunkById(100, function ($coupons): void {
            foreach ($coupons as $coupon) {
                $userId = DB::table('users')->whereRaw('LOWER(email) = ?', [strtolower((string) $coupon->assigned_email)])->value('id');
                if ($userId) {
                    DB::table('coupons')->where('id', $coupon->id)->update(['assigned_user_id' => $userId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
        });
    }
};
