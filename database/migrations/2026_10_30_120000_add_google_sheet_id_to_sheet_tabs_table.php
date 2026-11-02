<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sheet_tabs', function (Blueprint $table) {
            // Google's id of the tab (gid in the URL), doesn't change when the tab is renamed
            $table->unsignedBigInteger('google_sheet_id')->nullable()->after('spreadsheet_id');
        });
    }

    public function down(): void
    {
        Schema::table('sheet_tabs', function (Blueprint $table) {
            $table->dropColumn('google_sheet_id');
        });
    }
};
