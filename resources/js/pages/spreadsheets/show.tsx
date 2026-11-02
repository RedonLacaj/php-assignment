import AddTabForm from '@/components/add-tab-form';
import ConfirmButton from '@/components/confirm-button';
import SpreadsheetDialog from '@/components/spreadsheet-dialog';
import SyncButton from '@/components/sync-button';
import SyncLogTable from '@/components/sync-log-table';
import TabCard from '@/components/tab-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { Spreadsheet, SyncLog } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ExternalLink, Pencil, Trash2 } from 'lucide-react';

type Props = {
    spreadsheet: Spreadsheet;
    logs: SyncLog[];
};

export default function Show({ spreadsheet, logs }: Props) {
    return (
        <AppLayout>
            <Head title={spreadsheet.name} />

            <Link href="/spreadsheets" className="mb-2 inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
                <ChevronLeft className="size-4" /> Spreadsheets
            </Link>

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        {spreadsheet.name}
                        {!spreadsheet.is_active && <Badge variant="outline">disabled</Badge>}
                    </h1>
                    <a href={spreadsheet.url} target="_blank" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline">
                        Open in Google Sheets <ExternalLink className="size-3" />
                    </a>
                </div>
                <div className="flex gap-2">
                    <SyncButton spreadsheetId={spreadsheet.id} />
                    <SpreadsheetDialog
                        spreadsheet={spreadsheet}
                        trigger={
                            <Button variant="outline">
                                <Pencil /> Edit
                            </Button>
                        }
                    />
                    <ConfirmButton
                        url={`/spreadsheets/${spreadsheet.id}`}
                        title="Delete spreadsheet?"
                        description="Its tabs and mappings are removed from the config. Data that was already imported stays in the database."
                        variant="outline"
                        className="text-destructive"
                    >
                        <Trash2 /> Delete
                    </ConfirmButton>
                </div>
            </div>

            <div className="space-y-4">
                {spreadsheet.tabs?.map((tab) => <TabCard key={tab.id} tab={tab} />)}
                <AddTabForm spreadsheet={spreadsheet} />
            </div>

            <h2 className="mt-10 mb-3 text-lg font-semibold">Recent syncs</h2>
            <SyncLogTable logs={logs} />
        </AppLayout>
    );
}
