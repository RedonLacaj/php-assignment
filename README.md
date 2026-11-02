# Sheets Sync

Imports data from Google Sheets into MySQL once a day. Which files, tabs and columns are imported,
and where they are saved, is configured in database tables and can be managed from a web UI.

Built with Laravel 13, MySQL 8 and the Google Sheets API. The UI is React (TypeScript) with Inertia.js,
Tailwind CSS and shadcn/ui components.

## How it works

The configuration is stored in four tables:

| Table             | What it holds                                                                  |
|-------------------|--------------------------------------------------------------------------------|
| `spreadsheets`    | Google Sheets files to parse                                                   |
| `sheet_tabs`      | Tabs to read from each file (by Google tab id), and the row with the headers  |
| `table_mappings`  | Which database table a tab is saved to. One tab can have several mappings      |
| `column_mappings` | Sheet column (header) to table column, with a type                             |

So the example from the task, a tab with `date, product_name, price, amount` saved into `sales`, is one
table mapping with four column mappings:

```
sales:  date => date, product_name => name, price => product_price, amount => amount
```

On every sync (`app/Services/SheetSyncer.php`):

1. The tab is read from Google (`GoogleSheetReader`).
2. Columns are found by **header name**, not by position. Files with columns in a different order or with
   extra columns work with the same mapping. Header matching ignores case and extra spaces.
3. `TableSchemaManager` creates the target table if it doesn't exist and adds any mapped column the table
   doesn't have yet (`ALTER TABLE ... ADD COLUMN`). So a column added in the config shows up in the
   table on the next sync and gets filled with data.
4. Values are cast to the column type (`ValueCaster`): numbers like `$1,250.50`, Sheets date serials,
   yes/no booleans and so on.
5. Rows are written in a transaction. Each mapping has a mode:
   - `replace` - rows imported earlier by this mapping are deleted first, the table holds the current state of the sheet
   - `append` - rows are added on each sync, which keeps a daily history (use `synced_at` to tell snapshots apart)

Every target table gets `table_mapping_id` and `synced_at` columns, so several files can be saved into
the same table and you can see where each row came from. Each run is recorded in `sync_logs`.

## Setup on Ubuntu

Tested on a clean Ubuntu 24.04. 22.04 should work the same.

```bash
git clone <repo-url> /var/www/sheets-sync
cd /var/www/sheets-sync
sudo ./setup.sh
```

The script installs PHP 8.3, MySQL, Node.js 22, Nginx and Composer, builds the frontend, creates the
database and a DB user, configures `.env`, runs the migrations and seeds an example config. It also sets up
an Nginx site and a cron entry for the Laravel scheduler. At the end it prints the generated DB password.

Options are passed as environment variables, for example:

```bash
sudo APP_URL=http://sheets.example.com DB_PASS=secret SEED=0 ./setup.sh
```

After that, add the Google credentials (next section) and open the app in a browser.

### Manual setup

Requirements: PHP 8.3+ with `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`; MySQL 8; Composer; Node.js 22.

```bash
composer install
npm ci
npm run build
cp .env.example .env
php artisan key:generate
# set DB_* in .env
php artisan migrate --seed
php artisan serve --port=8008
```

For the daily sync, add the scheduler to cron:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

The sync time is configured in `.env`, see [Daily sync](#daily-sync).

### Docker

The repo has no Dockerfile. A `Dockerfile` and `docker-compose.yml` can be added if you need to run the app in Docker instead of the Ubuntu setup above. The app would be on port 8008, with MySQL published on port 3308.

## Google API credentials

The app uses a service account. It's a technical Google account the app authenticates as, so nobody
has to log in through a browser.

Takes about 5 minutes. A personal Google account is enough, billing is not needed.

1. **Create a project**: https://console.cloud.google.com/projectcreate
   Enter any name, e.g. `sheets-sync`, and click **Create**. Make sure the new project is selected
   in the project picker in the top bar before the next steps.
2. **Enable the Google Sheets API**: https://console.cloud.google.com/apis/library/sheets.googleapis.com
   Click **Enable**.
3. **Create a service account**: https://console.cloud.google.com/iam-admin/serviceaccounts
   Click **Create service account**, give it a name (e.g. `sheets-sync`) and click **Create and continue**.
   The role and user access steps are optional, skip them and click **Done**.
4. **Download a key**: in the same list click the new service account, open the **Keys** tab,
   then **Add key > Create new key**, choose **JSON** and click **Create**. A JSON file is downloaded.
5. **Put the key into the project**:
   ```bash
   mkdir -p storage/app/private/google
   cp ~/Downloads/<downloaded-file>.json storage/app/private/google/service-account.json
   ```
   Another location can be set with `GOOGLE_CREDENTIALS_PATH` in `.env`. The folder is in `.gitignore`,
   the key must never be committed. If the server was set up with `setup.sh`, give the file to the web user:
   `sudo chown www-data:www-data storage/app/private/google/service-account.json`
6. **Share your spreadsheets** with the service account email. It looks like
   `sheets-sync@your-project.iam.gserviceaccount.com` and can be copied from the service accounts page
   or from `client_email` in the JSON. In the spreadsheet click **Share**, paste the email and give
   **Viewer** access. Spreadsheets shared as "anyone with the link" (like the example one) don't need this.

If step 4 shows "Service account key creation is disabled", the Google account belongs to an organisation
that blocks keys (`iam.disableServiceAccountKeyCreation` policy). Either ask an admin to allow it for the
project or use a personal Gmail account.

Common errors when syncing:

- `Google credentials file not found` - the key is not at the path from step 5.
- `Google Sheets API has not been used in project ... or it is disabled` - step 2 was done in another project.
- `The caller does not have permission` - the spreadsheet is not shared with the service account (step 6).
- `Tab "..." not found in the spreadsheet` - the tab name in the config doesn't match any tab in the file.
- `Tab was deleted from the spreadsheet` - the tab was removed from the file, remove it from the config or point it to another tab.
- `cURL error 60: SSL certificate problem` - PHP has no CA certificates, usually on Windows. Download
  https://curl.se/ca/cacert.pem and set `curl.cainfo` and `openssl.cafile` to it in `php.ini`.

To check that everything works, run the example config, which uses a public sample spreadsheet from Google's docs:

```bash
php artisan sheets:sync
```

It should create the `students` and `student_activities` tables and fill them with the sample data.

## Usage

Open the app (`http://localhost:8008` with `php artisan serve --port=8008`, or the server URL after `setup.sh`).
Make sure the Google credentials are set up first (previous section).

### 1. Add a spreadsheet

1. In Google Sheets, share the spreadsheet with the service account email (Viewer access is enough).
2. In the app click **Add spreadsheet** and paste the spreadsheet URL. The app checks the file right away:
   it fills in the name from the spreadsheet title, or tells you if the file can't be opened (not shared,
   wrong URL) or is already added.
3. Keep **Include in daily sync** checked and click **Save**.

Each Google file can be added only once. To import more data from the same file, add more tabs or
table mappings to the existing entry.

### 2. Add a tab

On the spreadsheet page, in the **Add tab** card:

1. The tab names of the file are read when the spreadsheet is added. If the file changed, click
   **Fetch tab names** to read them again.
2. Choose the tab, set the **Header row** (the row with the column names, usually 1) and click **Add**.

Only tabs that exist in the file can be added. Tabs are tracked by Google's tab id, so renaming a tab in
Google Sheets later doesn't break anything, the new name is picked up on the next fetch or sync.

### 3. Map the tab to a table

After adding a tab you go straight to the table mapping form (later: **Table mapping** button on the tab).

1. **Database table**: name of the table the data goes to, e.g. `sales`. It's created on the first sync.
2. **Mode**: `Replace` keeps only the latest data of the sheet, `Append` adds a snapshot on every sync
   (daily history).
3. **Columns**: the columns of the sheet are loaded automatically (**Load headers from sheet** loads them
   again). For each one set the column name in the table and the type
   (`string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime`). Remove the ones you don't need.
4. Check the **Preview**: the first rows of the sheet, converted the way they will be saved.
5. **Save mapping**.

A tab can have several mappings, so the same data can be saved into several tables with different columns.
Adding a column to a mapping later adds it to the database table on the next sync.

### 4. Edit a tab and its mappings

Each tab on the spreadsheet page shows an **Active** / **Not active** badge and the buttons **Edit**,
**Delete** and **Table mapping**. **Edit** opens the tab page where you can:

- change the header row and turn the tab on or off (**Save changes**),
- edit or delete its table mappings, or add a new one,
- delete the tab.

Deleting a tab, mapping or spreadsheet only removes the configuration. Tables and imported data stay
in the database.

### 5. Sync

- **Sync now** on the spreadsheet list or page imports the data right away.
- The result is shown as a message, on the spreadsheet page (recent syncs) and in **Sync log**,
  including warnings like columns that were not found in the sheet.
- The list shows the last and the next sync of each spreadsheet.

From the command line:

```bash
php artisan sheets:sync          # all active spreadsheets
php artisan sheets:sync 1 3      # only spreadsheets with id 1 and 3
```

### Daily sync

All active spreadsheets are synced once a day by the Laravel scheduler (`routes/console.php`). The time is set in `.env`:

```
SHEETS_SYNC_TIME=02:00
APP_TIMEZONE=UTC        # timezone of SHEETS_SYNC_TIME, e.g. Europe/Berlin
```

```bash
php artisan schedule:list   # shows the task and when it runs next
php artisan schedule:work   # runs the scheduler in the foreground, for local testing without cron
```

The scheduler needs the cron entry from the setup section (`setup.sh` adds it). Spreadsheets with
"Include in daily sync" turned off are skipped.

## Development

```bash
php artisan serve --port=8008   # http://localhost:8008
npm run dev           # Vite dev server with hot reload, port 5173
```

Pages are in `resources/js/pages`, controllers return them with `Inertia::render()`.

## Tests

```bash
php artisan test
npx tsc --noEmit      # type check of the frontend
```

Tests use a fake sheet reader instead of Google and run on SQLite in memory. To run them on MySQL:

```bash
DB_CONNECTION=mysql DB_DATABASE=sheets_sync_test php artisan test
```

## Notes

- Removing a column from a mapping doesn't drop it from the database table, so existing data is never
  lost by a config change. The same goes for removing a mapping or a spreadsheet.
- Changing the type of a column that already exists in the table doesn't alter the table. Only new columns are added.
- Writing into the app's own tables (`users`, `spreadsheets`, `migrations` ...) is blocked, see `config/sheets.php`.
- The UI has no login. Run it on an internal network or behind basic auth.

## Structure

```
app/Services/SheetSyncer.php          sync of one spreadsheet
app/Services/TableSchemaManager.php   creates / alters target tables
app/Services/ValueCaster.php          converts cell values
app/Services/HeaderMap.php            finds columns by header name
app/Services/TabLocator.php           finds tabs by Google tab id, survives renames
app/Services/GoogleSheetReader.php    Google Sheets API client
app/Console/Commands/SyncSheets.php   sheets:sync command
routes/console.php                    daily schedule
app/Http/Controllers/                 web UI (Inertia controllers)
app/Http/Requests/                    form validation
app/Http/Resources/                   data sent to the React pages
resources/js/pages/                   React pages
resources/js/components/              React components, ui/ are shadcn/ui components
config/sheets.php                     settings
setup.sh                              Ubuntu setup script
```
