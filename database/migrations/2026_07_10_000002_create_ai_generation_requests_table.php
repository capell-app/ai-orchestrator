<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (TableName::existsAndIsOwned('ai_generation_requests', basename(__FILE__, '.php'))) {
            return;
        }

        Schema::create(TableName::resolve('ai_generation_requests'), function (Blueprint $table): void {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->json('payload');
            $table->json('generated')->nullable();
            $table->json('failures')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableName::resolve('ai_generation_requests'));
    }
};
