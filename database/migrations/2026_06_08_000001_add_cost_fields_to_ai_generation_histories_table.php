<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_generation_histories')) {
            return;
        }

        Schema::table('ai_generation_histories', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_generation_histories', 'cost_micros')) {
                $table->unsignedBigInteger('cost_micros')->default(0)->after('total_tokens');
            }

            if (! Schema::hasColumn('ai_generation_histories', 'cost_currency')) {
                $table->char('cost_currency', 3)->default('USD')->after('cost_micros');
            }

            if (! Schema::hasColumn('ai_generation_histories', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('language_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_generation_histories')) {
            return;
        }

        Schema::table('ai_generation_histories', function (Blueprint $table): void {
            if (Schema::hasColumn('ai_generation_histories', 'created_by_user_id')) {
                $table->dropColumn('created_by_user_id');
            }

            if (Schema::hasColumn('ai_generation_histories', 'cost_currency')) {
                $table->dropColumn('cost_currency');
            }

            if (Schema::hasColumn('ai_generation_histories', 'cost_micros')) {
                $table->dropColumn('cost_micros');
            }
        });
    }
};
