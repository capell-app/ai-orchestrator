<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @contract-migration-approved Widens encrypted storage before rewriting existing values. */
    public function up(): void
    {
        Schema::table('ai_generation_histories', function (Blueprint $table): void {
            $table->mediumText('input')->nullable()->change();
            $table->mediumText('output')->nullable()->change();
            $table->mediumText('error_message')->nullable()->change();
            $table->mediumText('metadata')->nullable()->change();
        });

        DB::table('ai_generation_histories')->orderBy('id')->each(function (object $history): void {
            DB::table('ai_generation_histories')->where('id', $history->id)->update([
                'input' => $this->encrypt($history->input),
                'output' => $this->encrypt($history->output),
                'error_message' => $this->encrypt($history->error_message),
                'metadata' => $this->encrypt($history->metadata),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('ai_generation_histories')->orderBy('id')->each(function (object $history): void {
            DB::table('ai_generation_histories')->where('id', $history->id)->update([
                'input' => $this->decrypt($history->input),
                'output' => $this->decrypt($history->output),
                'error_message' => $this->decrypt($history->error_message),
                'metadata' => $this->decrypt($history->metadata),
            ]);
        });

        Schema::table('ai_generation_histories', function (Blueprint $table): void {
            $table->text('input')->nullable()->change();
            $table->text('output')->nullable()->change();
            $table->text('error_message')->nullable()->change();
            $table->json('metadata')->nullable()->change();
        });
    }

    private function encrypt(mixed $value): ?string
    {
        return is_string($value) ? Crypt::encryptString($value) : null;
    }

    private function decrypt(mixed $value): ?string
    {
        return is_string($value) ? Crypt::decryptString($value) : null;
    }
};
