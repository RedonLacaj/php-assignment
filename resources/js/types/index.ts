export type ColumnType = 'string' | 'text' | 'integer' | 'decimal' | 'boolean' | 'date' | 'datetime';

export type SyncStatus = 'running' | 'success' | 'warning' | 'failed';

export interface ColumnMapping {
    id?: number;
    source_column: string;
    target_column: string;
    type: ColumnType;
}

export interface TableMapping {
    id: number;
    target_table: string;
    mode: 'replace' | 'append';
    is_active: boolean;
    columns: ColumnMapping[];
}

export interface SheetTab {
    id: number;
    name: string;
    google_sheet_id: number | null;
    header_row: number;
    is_active: boolean;
    mappings: TableMapping[];
}

export interface SyncLog {
    id: number;
    status: SyncStatus;
    rows_saved: number;
    message: string | null;
    started_at: string;
    finished_at: string | null;
    spreadsheet?: { id: number; name: string } | null;
}

export interface Spreadsheet {
    id: number;
    name: string;
    google_id: string;
    url: string;
    is_active: boolean;
    // tab names of the Google file, null until fetched
    google_tabs: string[] | null;
    tabs_fetched_at: string | null;
    last_synced_at: string | null;
    tabs_count?: number;
    tabs?: SheetTab[];
    latest_log?: SyncLog | null;
}

export interface Schedule {
    time: string;
    timezone: string;
    next_run: string;
}

// Shape of a paginated Laravel resource collection
export interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

export interface SharedProps {
    appName: string;
    flash: {
        success: string | null;
        warning: string | null;
        error: string | null;
    };
    [key: string]: unknown;
}
