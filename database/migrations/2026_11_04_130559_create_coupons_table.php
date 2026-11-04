<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('kind', 20)->default('coupon');
            $table->unsignedTinyInteger('percent');
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('max_uses');
            $table->boolean('once_per_customer')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('assigned_email')->nullable();
            $table->json('services');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('form_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('phone_digits', 20)->nullable();
            $table->string('service', 40);
            $table->unsignedTinyInteger('percent');
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->string('status', 20)->default('reserved')->index();
            $table->timestamps();
            $table->index(['coupon_id', 'status']);
            $table->index(['email', 'status']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('customer_phone_digits', 20)->nullable()->index()->after('customer_phone');
        });

        DB::table('bookings')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $digits = preg_replace('/\D+/', '', (string) $row->customer_phone) ?? '';
                if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                    $digits = substr($digits, 1);
                }
                $digits = strlen($digits) >= 10 ? substr($digits, -10) : $digits;
                DB::table('bookings')->where('id', $row->id)->update([
                    'customer_phone_digits' => $digits !== '' ? $digits : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('customer_phone_digits');
        });
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
