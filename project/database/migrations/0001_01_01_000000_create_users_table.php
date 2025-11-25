<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('fullname');
            $table->string('username')->unique()->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('profile_pic')->nullable();
            $table->string('phone_number')->unique()->nullable();
            $table->text('verification_otp')->nullable();
            $table->timestamp('otp_created_at')->nullable();
            $table->boolean('email_v_status')->default(false);
            $table->boolean('phone_v_status')->default(false);
            $table->string('password')->nullable();
            $table->string('bvn', 11)->nullable();
            $table->boolean('bvn_v_status')->default(false)->nullable();
            $table->decimal('balance', 20, 2)->default(0)->nullable();
            $table->decimal('income', 20, 2)->default(0)->nullable();
            $table->decimal('expenses', 20, 2)->default(0)->nullable();
            $table->string('device_id')->nullable();
            $table->string('pin', 4)->nullable();
            $table->text('token')->nullable();
            $table->text('fcm_token')->nullable();
            $table->text('device_type')->nullable();
            $table->boolean('receive_transaction_emails')->default(true);
            $table->boolean('receive_push_notifications')->default(true);
            $table->boolean('verified')->default(false);
            $table->boolean('weekly_newsletters')->default(true);
            $table->enum('account_type', ['client', 'artisan', 'select'])->default("select");
            $table->string('dob')->nullable();
            $table->integer('years_of_experience')->nullable();
            $table->string('service')->nullable();
            $table->text('bio')->nullable();
            $table->string('location')->nullable();
            $table->string('lga')->nullable();
            $table->string('state')->nullable();
            $table->integer('tier')->default(1);
            $table->string('promo_code')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users');
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('socket_id')->nullable();
            $table->timestamp('password_changed_at')->nullable()->after('password');
            
            // New fields to match Flutter pattern
            $table->decimal('rating', 3, 1)->default(0.0);
            $table->string('service_icon')->nullable();
            $table->string('service_icon_bg')->nullable(); // Store as hex color
            $table->enum('status', ['Available', 'Busy', 'Offline'])->default('Available');
            $table->string('experience')->nullable(); // "3 years" format
            
            $table->index('is_online');
            $table->index('last_seen_at');
            $table->index('socket_id');
            $table->index('account_type');
            $table->index('service');
            $table->index('rating');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};