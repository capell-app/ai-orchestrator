<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const array ENCRYPTED_COLUMNS = [
        'payload',
        'generated',
        'failures',
        'error_message',
    ];

    /** @contract-migration-approved Widens encrypted storage before rewriting existing values. */
    public function up(): void
    {
        Schema::table('ai_generation_requests', function (Blueprint $table): void {
            $table->mediumText('payload')->change();
            $table->mediumText('generated')->nullable()->change();
            $table->mediumText('failures')->nullable()->change();
            $table->mediumText('error_message')->nullable()->change();
        });

        $this->transform(encrypt: true);
    }

    public function down(): void
    {
        $this->transform(encrypt: false);

        Schema::table('ai_generation_requests', function (Blueprint $table): void {
            $table->json('payload')->nullable(false)->change();
            $table->json('generated')->nullable()->change();
            $table->json('failures')->nullable()->change();
            $table->text('error_message')->nullable()->change();
        });
    }

    private function transform(bool $encrypt): void
    {
        DB::table('ai_generation_requests')->orderBy('id')->each(function (object $request) use ($encrypt): void {
            $values = [];

            foreach (self::ENCRYPTED_COLUMNS as $column) {
                $value = $request->{$column};
                $values[$column] = is_string($value)
                    ? ($encrypt ? Crypt::encryptString($value) : Crypt::decryptString($value))
                    : null;
            }

            DB::table('ai_generation_requests')->where('id', $request->id)->update($values);
        });
    }
};
