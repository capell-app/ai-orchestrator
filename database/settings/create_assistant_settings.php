<?php

declare(strict_types=1);
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('assistant.page_content_generator')) {
            $this->migrator->add('assistant.page_content_generator', true);
        }

        if (! $this->migrator->exists('assistant.page_title_suggestions')) {
            $this->migrator->add('assistant.page_title_suggestions', true);
        }
    }
};
