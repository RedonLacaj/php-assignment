<?php

namespace Tests\Feature;

use App\Models\SheetTab;
use App\Models\Spreadsheet;
use App\Models\TableMapping;
use App\Services\SheetReader;
use App\Services\SheetSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fakes\FakeSheetReader;
use Tests\TestCase;

class SheetSyncTest extends TestCase
{
    use RefreshDatabase;

    private FakeSheetReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new FakeSheetReader;
        $this->app->instance(SheetReader::class, $this->reader);
    }

    protected function tearDown(): void
    {
        // tables created by the sync are not covered by RefreshDatabase
        Schema::dropIfExists('sales');
        Schema::dropIfExists('products');

        parent::tearDown();
    }

    public function test_it_creates_table_and_imports_mapped_columns(): void
    {
        $this->reader->set('file-1', 'Sales', [
            ['date', 'product_name', 'price', 'amount'],
            ['2025-01-10', 'Chair', '$1,250.50', 3],
            ['2025-01-11', 'Table', 99, 1],
        ]);

        $tab = $this->tab('file-1', 'Sales');
        $this->mapping($tab, 'sales', [
            'date' => ['date', 'date'],
            'product_name' => ['name', 'string'],
            'price' => ['product_price', 'decimal'],
            'amount' => ['amount', 'integer'],
        ]);

        $log = app(SheetSyncer::class)->sync($tab->spreadsheet);

        $this->assertSame(2, $log->rows_saved);
        $this->assertTrue(Schema::hasColumns('sales', ['date', 'name', 'product_price', 'amount']));
        $this->assertDatabaseHas('sales', ['name' => 'Chair', 'product_price' => 1250.5, 'amount' => 3, 'date' => '2025-01-10']);
    }

    public function test_columns_are_matched_by_header_not_position(): void
    {
        $this->reader->set('file-1', 'Sales', [
            ['date', 'product_name', 'price'],
            ['2025-01-10', 'Chair', 10],
        ]);
        $this->reader->set('file-2', 'Sales', [
            ['Price', 'extra column', 'Product_Name', 'Date'],
            [20, 'ignored', 'Lamp', '2025-02-01'],
        ]);

        foreach (['file-1', 'file-2'] as $file) {
            $tab = $this->tab($file, 'Sales');
            $this->mapping($tab, 'sales', [
                'date' => ['date', 'date'],
                'product_name' => ['name', 'string'],
                'price' => ['product_price', 'decimal'],
            ]);
            app(SheetSyncer::class)->sync($tab->spreadsheet);
        }

        $this->assertDatabaseHas('sales', ['name' => 'Chair', 'product_price' => 10]);
        $this->assertDatabaseHas('sales', ['name' => 'Lamp', 'product_price' => 20, 'date' => '2025-02-01']);
    }

    public function test_one_tab_can_be_saved_into_several_tables(): void
    {
        $this->reader->set('file-1', 'Sales', [
            ['date', 'product_name', 'price', 'amount'],
            ['2025-01-10', 'Chair', 10, 2],
        ]);

        $tab = $this->tab('file-1', 'Sales');
        $this->mapping($tab, 'sales', ['date' => ['sold_on', 'date'], 'amount' => ['qty', 'integer']]);
        $this->mapping($tab, 'products', ['product_name' => ['title', 'string'], 'price' => ['price', 'decimal']]);

        app(SheetSyncer::class)->sync($tab->spreadsheet);

        $this->assertDatabaseHas('sales', ['sold_on' => '2025-01-10', 'qty' => 2]);
        $this->assertDatabaseHas('products', ['title' => 'Chair', 'price' => 10]);
    }

    public function test_new_column_in_config_is_added_to_existing_table(): void
    {
        $this->reader->set('file-1', 'Sales', [
            ['product_name', 'price', 'region'],
            ['Chair', 10, 'EU'],
        ]);

        $tab = $this->tab('file-1', 'Sales');
        $mapping = $this->mapping($tab, 'sales', ['product_name' => ['name', 'string']]);
        app(SheetSyncer::class)->sync($tab->spreadsheet);

        $this->assertFalse(Schema::hasColumn('sales', 'region'));

        $mapping->columns()->create(['source_column' => 'region', 'target_column' => 'region', 'type' => 'string']);
        $log = app(SheetSyncer::class)->sync($tab->spreadsheet->fresh());

        $this->assertTrue(Schema::hasColumn('sales', 'region'));
        $this->assertDatabaseHas('sales', ['name' => 'Chair', 'region' => 'EU']);
        $this->assertStringContainsString('added columns to sales: region', $log->message);
        $this->assertSame('success', $log->status);
    }

    public function test_replace_mode_refreshes_and_append_mode_keeps_history(): void
    {
        $this->reader->set('file-1', 'Sales', [['product_name'], ['Chair'], ['Lamp']]);

        $tab = $this->tab('file-1', 'Sales');
        $replace = $this->mapping($tab, 'sales', ['product_name' => ['name', 'string']]);
        $append = $this->mapping($tab, 'products', ['product_name' => ['name', 'string']], 'append');

        app(SheetSyncer::class)->sync($tab->spreadsheet);
        app(SheetSyncer::class)->sync($tab->spreadsheet);

        $this->assertSame(2, DB::table('sales')->where('table_mapping_id', $replace->id)->count());
        $this->assertSame(4, DB::table('products')->where('table_mapping_id', $append->id)->count());
    }

    public function test_missing_tab_is_logged_as_failure(): void
    {
        $tab = $this->tab('file-1', 'Nope');
        $this->mapping($tab, 'sales', ['a' => ['a', 'string']]);

        $log = app(SheetSyncer::class)->sync($tab->spreadsheet);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('[Nope]', $log->message);
    }

    public function test_renamed_tab_is_found_by_google_id(): void
    {
        $this->reader->set('file-1', 'Sheet1', []);
        $this->reader->set('file-1', 'Sales', [['product_name'], ['Chair']]);

        $tab = $this->tab('file-1', 'Sales');
        $this->mapping($tab, 'sales', ['product_name' => ['name', 'string']]);

        app(SheetSyncer::class)->sync($tab->spreadsheet);
        $this->assertSame(1001, $tab->fresh()->google_sheet_id);

        $this->reader->rename('file-1', 'Sales', 'Sales 2025');
        $log = app(SheetSyncer::class)->sync($tab->spreadsheet);

        $this->assertSame('success', $log->status);
        $this->assertSame(1, $log->rows_saved);
        $this->assertSame('Sales 2025', $tab->fresh()->name);
        $this->assertStringContainsString('renamed from "Sales"', $log->message);
    }

    public function test_deleted_tab_is_reported(): void
    {
        $this->reader->set('file-1', 'Sheet1', []);

        $tab = $this->tab('file-1', 'Sales');
        $tab->update(['google_sheet_id' => 555]);
        $this->mapping($tab, 'sales', ['product_name' => ['name', 'string']]);

        $log = app(SheetSyncer::class)->sync($tab->spreadsheet);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('[Sales] Tab was deleted', $log->message);
    }

    public function test_spreadsheet_without_tabs_or_mappings_is_a_warning(): void
    {
        $spreadsheet = Spreadsheet::create(['name' => 'Empty', 'google_id' => 'file-1']);

        $log = app(SheetSyncer::class)->sync($spreadsheet);
        $this->assertSame('warning', $log->status);
        $this->assertStringContainsString('No active tabs', $log->message);

        $spreadsheet->tabs()->create(['name' => 'Sales']);

        $log = app(SheetSyncer::class)->sync($spreadsheet);
        $this->assertSame('warning', $log->status);
        $this->assertStringContainsString('[Sales] no active table mappings', $log->message);
    }

    public function test_protected_tables_cannot_be_targets(): void
    {
        $this->reader->set('file-1', 'Sales', [['name'], ['x']]);

        $tab = $this->tab('file-1', 'Sales');
        $this->mapping($tab, 'users', ['name' => ['name', 'string']]);

        $log = app(SheetSyncer::class)->sync($tab->spreadsheet);

        $this->assertSame('failed', $log->status);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_artisan_command_syncs_active_spreadsheets(): void
    {
        $this->reader->set('file-1', 'Sales', [['product_name'], ['Chair']]);
        $tab = $this->tab('file-1', 'Sales');
        $this->mapping($tab, 'sales', ['product_name' => ['name', 'string']]);

        $this->artisan('sheets:sync')->assertSuccessful();

        $this->assertDatabaseHas('sales', ['name' => 'Chair']);
        $this->assertNotNull($tab->spreadsheet->fresh()->last_synced_at);
    }

    private function tab(string $googleId, string $name): SheetTab
    {
        $spreadsheet = Spreadsheet::firstOrCreate(['google_id' => $googleId], ['name' => $googleId]);

        return $spreadsheet->tabs()->create(['name' => $name]);
    }

    private function mapping(SheetTab $tab, string $table, array $columns, string $mode = 'replace'): TableMapping
    {
        $mapping = $tab->mappings()->create(['target_table' => $table, 'mode' => $mode]);

        foreach ($columns as $source => [$target, $type]) {
            $mapping->columns()->create(['source_column' => $source, 'target_column' => $target, 'type' => $type]);
        }

        return $mapping;
    }
}
