<?php

namespace Tests\Feature;

use App\Models\Spreadsheet;
use App\Models\TableMapping;
use App\Services\SheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FakeSheetReader;
use Tests\TestCase;

class ManagementUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_render(): void
    {
        $spreadsheet = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc']);
        $tab = $spreadsheet->tabs()->create(['name' => 'Sales']);

        $reader = new FakeSheetReader;
        $reader->set('abc', 'Sales', []);
        $reader->set('abc', 'Refunds', []);
        $this->app->instance(SheetReader::class, $reader);

        $this->get('/')->assertRedirect('/spreadsheets');

        $this->get('/spreadsheets')->assertInertia(fn (Assert $page) => $page
            ->component('spreadsheets/index')
            ->where('spreadsheets.0.name', 'Shop')
            ->where('spreadsheets.0.tabs_count', 1)
            ->where('spreadsheets.0.url', 'https://docs.google.com/spreadsheets/d/abc/edit')
            ->where('spreadsheets.0.latest_log', null)
            ->missing('spreadsheets.0.created_at')
            ->missing('spreadsheets.0.tabs'));

        $this->get("/spreadsheets/{$spreadsheet->id}")->assertInertia(fn (Assert $page) => $page
            ->component('spreadsheets/show')
            ->where('spreadsheet.tabs.0.name', 'Sales')
            ->where('spreadsheet.google_tabs', null)
            ->where('spreadsheet.tabs_fetched_at', null));

        $this->get("/tabs/{$tab->id}/mappings/create")->assertInertia(fn (Assert $page) => $page
            ->component('mappings/form')
            ->where('tab.name', 'Sales')
            ->where('mapping', null));

        $spreadsheet->syncLogs()->create(['status' => 'success', 'rows_saved' => 3, 'started_at' => now(), 'finished_at' => now()]);

        $this->get('/logs')->assertInertia(fn (Assert $page) => $page
            ->component('logs/index')
            ->where('logs.data.0.rows_saved', 3)
            ->where('logs.data.0.spreadsheet', ['id' => $spreadsheet->id, 'name' => 'Shop'])
            ->where('logs.meta.current_page', 1)
            ->has('logs.meta.links'));
    }

    public function test_tab_edit_page(): void
    {
        $spreadsheet = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc']);
        $tab = $spreadsheet->tabs()->create(['name' => 'Sales', 'header_row' => 1]);
        $tab->mappings()->create(['target_table' => 'sales', 'mode' => 'replace']);

        $this->get("/tabs/{$tab->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->component('tabs/edit')
            ->where('tab.name', 'Sales')
            ->where('tab.is_active', true)
            ->where('tab.mappings.0.target_table', 'sales')
            ->where('spreadsheet', ['id' => $spreadsheet->id, 'name' => 'Shop']));

        $this->from("/tabs/{$tab->id}/edit")
            ->put("/tabs/{$tab->id}", ['name' => 'Sales', 'header_row' => 3])
            ->assertRedirect("/tabs/{$tab->id}/edit")
            ->assertSessionHasNoErrors();
        $this->assertSame(3, $tab->fresh()->header_row);
        $this->assertFalse($tab->fresh()->is_active);

        $this->delete("/tabs/{$tab->id}")->assertRedirect("/spreadsheets/{$spreadsheet->id}");
        $this->assertDatabaseCount('sheet_tabs', 0);
    }

    public function test_spreadsheet_page_does_not_call_google(): void
    {
        $spreadsheet = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc']);
        $this->app->instance(SheetReader::class, new class extends FakeSheetReader
        {
            public function tabs(string $spreadsheetId): array
            {
                throw new \LogicException('Google must not be called when opening the page');
            }
        });

        $this->get("/spreadsheets/{$spreadsheet->id}")->assertOk();
    }

    public function test_fetching_tab_names_saves_them_and_picks_up_renames(): void
    {
        $reader = new FakeSheetReader;
        $reader->set('abc', 'Sales', []);
        $reader->set('abc', 'Refunds', []);
        $this->app->instance(SheetReader::class, $reader);

        $spreadsheet = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc']);
        $tab = $spreadsheet->tabs()->create(['name' => 'Sales', 'google_sheet_id' => 0]);
        $reader->rename('abc', 'Sales', 'Sales 2025');

        $this->post("/spreadsheets/{$spreadsheet->id}/fetch-tabs")
            ->assertSessionHas('success', 'Found 2 tabs in the spreadsheet.');

        $spreadsheet->refresh();
        $this->assertSame(['Sales 2025', 'Refunds'], $spreadsheet->google_tabs);
        $this->assertNotNull($spreadsheet->tabs_fetched_at);
        $this->assertSame('Sales 2025', $tab->fresh()->name);
    }

    public function test_fetching_tab_names_reports_google_errors(): void
    {
        $spreadsheet = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc']);
        $this->app->instance(SheetReader::class, new class extends FakeSheetReader
        {
            public function tabs(string $spreadsheetId): array
            {
                throw new \RuntimeException('No access to the spreadsheet.');
            }
        });

        $this->post("/spreadsheets/{$spreadsheet->id}/fetch-tabs")
            ->assertSessionHas('error', "Couldn't read the tabs from Google: No access to the spreadsheet.");
        $this->assertNull($spreadsheet->fresh()->google_tabs);
    }

    public function test_adding_a_spreadsheet_fetches_its_tab_names(): void
    {
        $reader = new FakeSheetReader;
        $reader->set('abc', 'Sheet1', []);
        $this->app->instance(SheetReader::class, $reader);

        $this->post('/spreadsheets', ['name' => 'Shop', 'google_id' => 'abc', 'is_active' => 1]);

        $this->assertSame(['Sheet1'], Spreadsheet::first()->google_tabs);
    }

    public function test_lookup_returns_title_or_problem(): void
    {
        $reader = new FakeSheetReader;
        $reader->set('abc', 'Sheet1', []);
        $this->app->instance(SheetReader::class, $reader);

        $this->getJson('/spreadsheets/lookup?google_id='.urlencode('https://docs.google.com/spreadsheets/d/abc/edit'))
            ->assertOk()
            ->assertExactJson(['google_id' => 'abc', 'title' => 'Spreadsheet abc', 'existing' => null]);

        $existing = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc']);
        $this->getJson('/spreadsheets/lookup?google_id=abc')
            ->assertJsonPath('existing', ['id' => $existing->id, 'name' => 'Shop']);

        $this->getJson('/spreadsheets/lookup?google_id=missing')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Spreadsheet not found, check the URL or ID.');

        $this->getJson('/spreadsheets/lookup?google_id=')->assertStatus(422);
    }

    public function test_spreadsheet_id_is_extracted_from_url(): void
    {
        $this->post('/spreadsheets', [
            'name' => 'Shop',
            'google_id' => 'https://docs.google.com/spreadsheets/d/1AbC_d-9/edit#gid=0',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('spreadsheets', ['google_id' => '1AbC_d-9']);
    }

    public function test_list_shows_next_daily_sync(): void
    {
        config(['sheets.sync_time' => '02:00']);

        $this->travelTo(now()->setTime(1, 0));
        $this->get('/spreadsheets')->assertInertia(fn (Assert $page) => $page
            ->where('schedule.time', '02:00')
            ->where('schedule.next_run', today()->setTime(2, 0)->toIso8601String()));

        $this->travelTo(now()->setTime(14, 0));
        $this->get('/spreadsheets')->assertInertia(fn (Assert $page) => $page
            ->where('schedule.next_run', today()->addDay()->setTime(2, 0)->toIso8601String()));
    }

    public function test_newest_spreadsheets_are_listed_first(): void
    {
        Spreadsheet::create(['name' => 'Older', 'google_id' => 'aaa'])->forceFill(['created_at' => now()->subDay()])->save();
        Spreadsheet::create(['name' => 'Newer', 'google_id' => 'bbb']);

        $this->get('/spreadsheets')->assertInertia(fn (Assert $page) => $page
            ->where('spreadsheets.0.name', 'Newer')
            ->where('spreadsheets.1.name', 'Older'));
    }

    public function test_deleting_spreadsheet_removes_config_but_keeps_imported_data(): void
    {
        $spreadsheet = Spreadsheet::create(['name' => 'Leads', 'google_id' => 'aaa']);
        $tab = $spreadsheet->tabs()->create(['name' => 'Sheet1']);
        $tab->mappings()->create(['target_table' => 'leads', 'mode' => 'replace']);
        Schema::create('leads', fn ($t) => $t->id());
        DB::table('leads')->insert(['id' => 1]);

        $this->delete("/spreadsheets/{$spreadsheet->id}")
            ->assertRedirect('/spreadsheets')
            ->assertSessionHas('success');

        $this->assertDatabaseCount('spreadsheets', 0);
        $this->assertDatabaseCount('sheet_tabs', 0);
        $this->assertDatabaseCount('table_mappings', 0);
        $this->assertDatabaseCount('leads', 1);

        Schema::drop('leads');
    }

    public function test_adding_existing_spreadsheet_goes_to_existing_entry(): void
    {
        $existing = Spreadsheet::create(['name' => 'Leads', 'google_id' => '1AbC_d-9']);

        $this->post('/spreadsheets', [
            'name' => 'Leads again',
            'google_id' => 'https://docs.google.com/spreadsheets/d/1AbC_d-9/edit#gid=0',
        ])
            ->assertRedirect("/spreadsheets/{$existing->id}")
            ->assertSessionHas('warning', fn ($message) => str_contains($message, 'already added as "Leads"'));

        $this->assertDatabaseCount('spreadsheets', 1);
    }

    public function test_editing_to_another_entrys_spreadsheet_is_rejected(): void
    {
        Spreadsheet::create(['name' => 'Leads', 'google_id' => 'aaa']);
        $other = Spreadsheet::create(['name' => 'Sales', 'google_id' => 'bbb']);

        $this->put("/spreadsheets/{$other->id}", ['name' => 'Sales', 'google_id' => 'aaa'])
            ->assertSessionHasErrors(['google_id' => 'Another entry already uses this spreadsheet. Each file can be added only once.']);

        $this->put("/spreadsheets/{$other->id}", ['name' => 'Sales renamed', 'google_id' => 'bbb'])
            ->assertSessionHasNoErrors();
    }

    public function test_mapping_is_saved_with_columns(): void
    {
        $tab = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc'])->tabs()->create(['name' => 'Sales']);

        $this->post("/tabs/{$tab->id}/mappings", [
            'target_table' => 'Sales',
            'mode' => 'replace',
            'is_active' => 1,
            'columns' => [
                ['source_column' => 'product_name', 'target_column' => 'name', 'type' => 'string'],
                ['source_column' => 'price', 'target_column' => 'product_price', 'type' => 'decimal'],
                ['source_column' => '', 'target_column' => '', 'type' => 'string'],
            ],
        ])->assertRedirect();

        $mapping = TableMapping::first();
        $this->assertSame('sales', $mapping->target_table);
        $this->assertCount(2, $mapping->columns);
    }

    public function test_mapping_validation(): void
    {
        $tab = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc'])->tabs()->create(['name' => 'Sales']);

        $this->post("/tabs/{$tab->id}/mappings", [
            'target_table' => 'users',
            'mode' => 'replace',
            'columns' => [
                ['source_column' => 'a', 'target_column' => 'id', 'type' => 'string'],
                ['source_column' => 'b', 'target_column' => 'bad name', 'type' => 'string'],
            ],
        ])->assertSessionHasErrors(['target_table', 'columns.0.target_column', 'columns.1.target_column']);
    }

    public function test_tab_validation(): void
    {
        $reader = new FakeSheetReader;
        $reader->set('def', 'Sales', []);
        $this->app->instance(SheetReader::class, $reader);

        $spreadsheet = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc']);
        $spreadsheet->tabs()->create(['name' => 'Sales']);
        $other = Spreadsheet::create(['name' => 'Other', 'google_id' => 'def']);
        $other->forceFill(['google_tabs' => ['Sales']])->save();

        $this->post("/spreadsheets/{$spreadsheet->id}/tabs", ['name' => 'Sales', 'header_row' => 0])
            ->assertSessionHasErrors([
                'name' => 'This tab is already added to the spreadsheet.',
                'header_row' => 'The header row starts at 1.',
            ]);

        $this->post("/spreadsheets/{$other->id}/tabs", ['name' => 'Sales', 'header_row' => 1])
            ->assertSessionHasNoErrors();

        $tab = $spreadsheet->tabs()->first();
        $this->put("/tabs/{$tab->id}", ['name' => 'Sales', 'header_row' => 2])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $tab->fresh()->header_row);
    }

    public function test_only_tabs_in_the_file_can_be_added(): void
    {
        $reader = new FakeSheetReader;
        $reader->set('abc', 'Sheet1', []);
        $reader->set('abc', 'Sales', []);
        $this->app->instance(SheetReader::class, $reader);

        $spreadsheet = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc']);
        $spreadsheet->forceFill(['google_tabs' => ['Sheet1', 'Sales']])->save();

        // found in Google: straight to choosing the table
        $this->post("/spreadsheets/{$spreadsheet->id}/tabs", ['name' => 'Sales', 'header_row' => 1, 'is_active' => 1])
            ->assertRedirect('/tabs/'.$spreadsheet->tabs()->where('name', 'Sales')->value('id').'/mappings/create')
            ->assertSessionHas('success');
        $this->assertDatabaseHas('sheet_tabs', ['name' => 'Sales', 'google_sheet_id' => 1001]);

        // not in the file: rejected, nothing saved
        $this->post("/spreadsheets/{$spreadsheet->id}/tabs", ['name' => 'Typo', 'header_row' => 1])
            ->assertSessionHasErrors(['name' => 'There is no tab "Typo" in the spreadsheet. Fetch the tab names again if it was just added.']);
        $this->assertDatabaseMissing('sheet_tabs', ['name' => 'Typo']);

        // a tab has to be chosen
        $this->post("/spreadsheets/{$spreadsheet->id}/tabs", ['name' => '', 'header_row' => 1])
            ->assertSessionHasErrors(['name' => 'Choose a tab of the spreadsheet.']);
        $this->assertDatabaseCount('sheet_tabs', 1);
    }

    public function test_tab_cant_be_added_before_tab_names_are_fetched(): void
    {
        $spreadsheet = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc']);

        $this->post("/spreadsheets/{$spreadsheet->id}/tabs", ['name' => 'Sales', 'header_row' => 1])
            ->assertSessionHasErrors(['name' => 'Fetch the tab names of the spreadsheet first.']);
        $this->assertDatabaseCount('sheet_tabs', 0);
    }

    public function test_preview_returns_headers_and_converted_rows(): void
    {
        $reader = new FakeSheetReader;
        $reader->set('abc', 'Sales', [
            ['date', 'Product Name', '', 'price'],
            ['2025-01-01', 'Chair', '', '$1,200.50'],
            ['2025-01-02', 'Lamp', '', 15],
        ]);
        $this->app->instance(SheetReader::class, $reader);

        $tab = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc'])->tabs()->create(['name' => 'Sales']);

        $columns = json_encode([
            ['source_column' => 'product name', 'target_column' => 'name', 'type' => 'string'],
            ['source_column' => 'price', 'target_column' => 'product_price', 'type' => 'decimal'],
            ['source_column' => 'missing', 'target_column' => 'x', 'type' => 'string'],
        ]);

        $this->getJson("/tabs/{$tab->id}/preview?columns=".urlencode($columns))
            ->assertOk()
            ->assertJson([
                'headers' => ['date', 'Product Name', 'price'],
                'samples' => ['price' => ['$1,200.50', 15]],
                'rows' => [
                    ['name' => 'Chair', 'product_price' => 1200.5],
                    ['name' => 'Lamp', 'product_price' => 15],
                ],
                'missing' => ['missing'],
            ]);
    }

    public function test_preview_reports_google_errors(): void
    {
        $tab = Spreadsheet::create(['name' => 'Shop', 'google_id' => 'abc'])->tabs()->create(['name' => 'Nope']);

        $this->getJson("/tabs/{$tab->id}/preview")->assertStatus(422)->assertJsonStructure(['message']);
    }
}
