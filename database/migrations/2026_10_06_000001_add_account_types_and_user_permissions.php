<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'account_type')) {
                $table->string('account_type', 20)->default('customer')->index()->after('status');
            }
            if (!Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title', 120)->nullable()->after('account_type');
            }
            if (!Schema::hasColumn('users', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('job_title');
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            }
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            }
        });

        if (!Schema::hasTable('permission_user')) {
            Schema::create('permission_user', function (Blueprint $table) {
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->primary(['permission_id', 'user_id']);
            });
        }

        // Backfill account types from the roles users already hold.
        $roleIds = DB::table('roles')->pluck('id', 'name');
        $classify = function (array $roleNames, string $type) use ($roleIds): void {
            $ids = collect($roleNames)->map(fn ($name) => $roleIds[$name] ?? null)->filter()->values()->all();
            if ($ids === []) {
                return;
            }
            DB::table('users')
                ->whereIn('id', DB::table('role_user')->whereIn('role_id', $ids)->select('user_id'))
                ->update(['account_type' => $type]);
        };
        $classify(['operations', 'check_in', 'instructor'], 'staff');
        $classify(['super_admin', 'finance'], 'admin');
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['account_type', 'job_title', 'internal_notes', 'last_login_at', 'last_login_ip']);
        });
    }
};
