# Capell Assistant

![Assistant Hero Banner](./HERO_BANNER.svg)

OpenAI-powered content drafting for Capell. Helps editors generate page titles, meta descriptions, and long-form content from the admin panel, with rate limiting, an audit log, and a usage dashboard widget.

**[Full documentation →](https://docs.capell.app/packages/assistant/)**

## Overview

- AI-assisted drafting for titles, meta descriptions, and page content
- Rate limiting per user and per workspace
- Audit log table (`ai_generation_histories`) for tracking usage
- Filament usage widget for monitoring AI activity
- Admin utilities for testing the OpenAI connection and managing the cache

## Features

- Filament form actions
    - Generate title suggestion on page create/edit
    - Generate meta description suggestion on page create/edit
    - Generate long-form content draft via TinyMCE integration
- Rate limiting
    - Configurable per-user and global limits in `config/capell-assistant.php`
- Audit log
    - Every generation is logged to `ai_generation_histories` with user, action, tokens used, and result
- Filament widget
    - `AiUsageWidget` displays a summary of AI usage per workspace
- Commands
    - `capell:assistant-install` — publish config and migrations, run migrations
    - `capell:admin-test-openai` — verify your OpenAI API key is working
    - `capell:admin-clear-ai-cache` — clear cached AI responses
    - `capell:admin-monitor-ai-usage` — output a usage summary to the console

## Requirements

- Capell Admin and Frontend packages installed
- `openai-php/laravel` package (`composer require openai-php/laravel`)
- An OpenAI API key in your `.env`: `OPENAI_API_KEY=sk-...`

## Installation

1. Install the package dependency:

    ```bash
    composer require openai-php/laravel
    ```

2. Run the Capell Assistant installer:

    ```bash
    php artisan capell:assistant-install
    ```

    This will:
    - Publish `config/capell-assistant.php`
    - Publish and run the `ai_generation_histories` migration

3. Add your OpenAI key to `.env`:

    ```env
    OPENAI_API_KEY=sk-...
    ```

4. (Optional) Verify the connection:

    ```bash
    php artisan capell:admin-test-openai
    ```
