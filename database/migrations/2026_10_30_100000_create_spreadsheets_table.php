<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spreadsheets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('google_id')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sheet_tabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spreadsheet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('header_row')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['spreadsheet_id', 'name']);
        });

        Schema::create('table_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_tab_id')->constrained()->cascadeOnDelete();
            $table->string('target_table', 64);
            $table->string('mode', 16)->default('replace');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('column_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_mapping_id')->constrained()->cascadeOnDelete();
            $table->string('source_column');
            $table->string('target_column', 64);
            $table->string('type', 16)->default('string');
            $table->timestamps();

            $table->unique(['table_mapping_id', 'target_column']);
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spreadsheet_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('rows_saved')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('column_mappings');
        Schema::dropIfExists('table_mappings');
        Schema::dropIfExists('sheet_tabs');
        Schema::dropIfExists('spreadsheets');
    }
};
