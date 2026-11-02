# Sheets Sync

Imports data from Google Sheets into MySQL once a day. Which files, tabs and columns are imported is stored in the database and edited from a web UI.

Laravel 13, MySQL 8, Google Sheets API. The UI is React, Inertia.js, Tailwind CSS and shadcn/ui.

## How it works

| Table             | What it holds                                                        |
|-------------------|----------------------------------------------------------------------|
| `spreadsheets`    | Google Sheets files                                                  |
| `sheet_tabs`      | Tabs to read, and which row has the headers                         |
| `table_mappings`  | Which database table a tab is saved to. One tab can have several    |
| `column_mappings` | Sheet header to table column, with a type                           |

Columns are matched by header name, not by position. On sync the target table is created if it is missing, and any new mapped column is added. Values are cast to the column type.

- `replace` keeps the current sheet in the table
- `append` adds a snapshot on every sync (`synced_at` tells them apart)

Each imported table also gets `table_mapping_id` and `synced_at`. Each run is stored in `sync_logs`.

## Setup

Ubuntu 24.04 (22.04 should work the same):

```bash
git clone <repo-url> /var/www/sheets-sync
cd /var/www/sheets-sync
sudo ./setup.sh
```

The script installs PHP 8.3, MySQL, Node.js 22, Nginx and Composer, builds the frontend, creates the database, migrates, seeds an example config, and adds Nginx plus cron for the daily sync. It prints the database password.

```bash
sudo APP_URL=http://sheets.example.com DB_PASS=secret SEED=0 ./setup.sh
```

Then add the Google key (next section) and open the app.

### Manual setup

PHP 8.3+ (`pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`), MySQL 8, Composer, Node.js 22.

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

Daily sync needs cron. The time is `SHEETS_SYNC_TIME` in `.env` (timezone: `APP_TIMEZONE`).

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### Docker

There is no Dockerfile. I could have added `Dockerfile` and `docker-compose.yml`, but it wasn't requested in the task.

## Google credentials

The app uses a service account, so nobody logs in through a browser. A personal Google account is enough. Billing is not required.

1. Create a project: https://console.cloud.google.com/projectcreate
2. Enable the Sheets API: https://console.cloud.google.com/apis/library/sheets.googleapis.com
3. Create a service account: https://console.cloud.google.com/iam-admin/serviceaccounts (skip the optional role steps)
4. **Keys → Add key → Create new key → JSON**
5. Save the file as `storage/app/private/google/service-account.json` (or set `GOOGLE_CREDENTIALS_PATH`). Do not commit it. After `setup.sh`: `sudo chown www-data:www-data storage/app/private/google/service-account.json`
6. Share each spreadsheet with the service account email (`client_email` in the JSON) as Viewer. Sheets shared as "anyone with the link", including the example, do not need this.

If key creation is disabled, the account belongs to an organisation that blocks keys. Use a personal Gmail account, or ask an admin.

Check with the example config (a public sample spreadsheet):

```bash
php artisan sheets:sync
```

It fills the `students` and `student_activities` tables.

Common errors:

- `Google credentials file not found` — the key is not at the path above
- `Google Sheets API has not been used in project ... or it is disabled` — the API was enabled in another project
- `The caller does not have permission` — the spreadsheet is not shared with the service account
- `Tab "..." not found` or `Tab was deleted from the spreadsheet` — the tab in the config does not match the file
- `cURL error 60` — on Windows, PHP has no CA certificates. Point `curl.cainfo` and `openssl.cafile` in `php.ini` at https://curl.se/ca/cacert.pem

## Usage

Open http://localhost:8008, or the URL printed by `setup.sh`.

1. **Add spreadsheet.** Paste the Google Sheets URL. The app fills in the title, or says if the file cannot be opened or is already added. Leave **Include in daily sync** on. A file is added once; extra data from it is more tabs or mappings.
2. **Add tab.** Pick a tab and the header row (usually 1). **Fetch tab names** reloads the list. Tabs are stored by Google's tab id, so renaming a tab in Sheets later still works.
3. **Map columns.** Set the database table (created on the first sync), `Replace` or `Append`, and for each sheet column the table column and type (`string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime`). **Preview** shows the first rows as they will be saved. One tab can have several mappings.
4. **Edit or delete.** Change the header row, turn a tab off, or edit its mappings. Deleting a spreadsheet, tab or mapping removes the config only. Imported tables stay.
5. **Sync now.** Imports immediately. The result, including warnings such as a missing column, shows on the page and in **Sync log**.

```bash
php artisan sheets:sync        # all active spreadsheets
php artisan sheets:sync 1 3    # only ids 1 and 3
```

Daily sync runs at `SHEETS_SYNC_TIME` (`APP_TIMEZONE` is the timezone, for example `Europe/Berlin`). `php artisan schedule:list` shows the next run. `php artisan schedule:work` runs the scheduler in the foreground. Spreadsheets with daily sync turned off are skipped.

## Development

```bash
php artisan serve --port=8008
npm run dev
php artisan test
npx tsc --noEmit
```

Tests use a fake sheet reader and SQLite in memory. On MySQL: `DB_CONNECTION=mysql DB_DATABASE=sheets_sync_test php artisan test`.

## Notes

- Removing a column, mapping or spreadsheet does not drop the database table or its data.
- Changing the type of a column that already exists does not change the table. Only new columns are added.
- A mapping cannot write into the app's own tables (`users`, `spreadsheets`, `migrations`, …). See `config/sheets.php`.
- The UI has no login. Run it on an internal network or behind basic auth.
