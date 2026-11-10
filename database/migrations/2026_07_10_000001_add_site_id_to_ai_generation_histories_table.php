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
        if (! Schema::hasTable(TableName::resolve('ai_generation_histories')) || Schema::hasColumn(TableName::resolve('ai_generation_histories'), 'site_id')) {
            return;
        }

        Schema::table(TableName::resolve('ai_generation_histories'), function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(TableName::resolve('ai_generation_histories')) || ! Schema::hasColumn(TableName::resolve('ai_generation_histories'), 'site_id')) {
            return;
        }

        Schema::table(TableName::resolve('ai_generation_histories'), function (Blueprint $table): void {
            $table->dropColumn('site_id');
        });
    }
};
