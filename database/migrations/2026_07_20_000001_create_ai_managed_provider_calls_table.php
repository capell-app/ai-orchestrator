<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_managed_provider_calls')) {
            return;
        }

        Schema::create('ai_managed_provider_calls', function (Blueprint $table): void {
            $table->id();
            $table->char('call_key_digest', 64)->unique();
            $table->char('authorization_digest', 64);
            $table->char('request_digest', 64);
            $table->string('state', 20)->index();
            $table->uuid('lease_token')->nullable()->unique();
            $table->timestamp('lease_expires_at')->nullable()->index();
            $table->longText('attempts');
            $table->longText('terminal_result')->nullable();
            $table->timestamp('terminal_at')->nullable();
            $table->dateTime('recovery_expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_managed_provider_calls');
    }
};
