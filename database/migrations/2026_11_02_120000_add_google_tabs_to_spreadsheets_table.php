<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spreadsheets', function (Blueprint $table) {
            // tab names of the Google file, in the file's order, fetched on request and on every sync
            $table->json('google_tabs')->nullable()->after('google_id');
            $table->timestamp('tabs_fetched_at')->nullable()->after('google_tabs');
        });
    }

    public function down(): void
    {
        Schema::table('spreadsheets', function (Blueprint $table) {
            $table->dropColumn(['google_tabs', 'tabs_fetched_at']);
        });
    }
};
