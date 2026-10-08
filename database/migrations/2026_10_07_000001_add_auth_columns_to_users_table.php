<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_number', 20)->nullable()->unique()->after('email');
            $table->dateTime('whatsapp_verified_at')->nullable()->after('whatsapp_number');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->dateTime('locked_until')->nullable();
            $table->dateTime('terms_accepted_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['whatsapp_number']);
            $table->dropColumn([
                'whatsapp_number', 'whatsapp_verified_at', 'failed_login_attempts',
                'locked_until', 'terms_accepted_at', 'last_login_at',
            ]);
        });
    }
};
