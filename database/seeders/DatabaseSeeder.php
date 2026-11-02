<?php

namespace Database\Seeders;

use App\Models\Spreadsheet;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Example configuration based on the public sample spreadsheet from the
     * Google Sheets API docs, so the sync can be tried right after setup.
     */
    public function run(): void
    {
        $spreadsheet = Spreadsheet::firstOrCreate(
            ['google_id' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms'],
            ['name' => 'Google sample - Class Data'],
        );

        $tab = $spreadsheet->tabs()->firstOrCreate(['name' => 'Class Data'], ['header_row' => 1]);

        if ($tab->mappings()->exists()) {
            return;
        }

        $students = $tab->mappings()->create(['target_table' => 'students', 'mode' => 'replace']);
        $students->columns()->createMany([
            ['source_column' => 'Student Name', 'target_column' => 'name', 'type' => 'string'],
            ['source_column' => 'Gender', 'target_column' => 'gender', 'type' => 'string'],
            ['source_column' => 'Class Level', 'target_column' => 'class_level', 'type' => 'string'],
            ['source_column' => 'Major', 'target_column' => 'major', 'type' => 'string'],
        ]);

        // Same tab saved into a second table with a different structure
        $activities = $tab->mappings()->create(['target_table' => 'student_activities', 'mode' => 'append']);
        $activities->columns()->createMany([
            ['source_column' => 'Student Name', 'target_column' => 'student', 'type' => 'string'],
            ['source_column' => 'Home State', 'target_column' => 'state', 'type' => 'string'],
            ['source_column' => 'Extracurricular Activity', 'target_column' => 'activity', 'type' => 'string'],
        ]);
    }
}
