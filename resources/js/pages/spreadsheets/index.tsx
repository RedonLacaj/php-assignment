import ConfirmButton from '@/components/confirm-button';
import SpreadsheetDialog from '@/components/spreadsheet-dialog';
import StatusBadge from '@/components/status-badge';
import SyncButton from '@/components/sync-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { Schedule, Spreadsheet } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Clock, Plus, Trash2 } from 'lucide-react';

export default function Index({ spreadsheets, schedule }: { spreadsheets: Spreadsheet[]; schedule: Schedule }) {
    return (
        <AppLayout>
            <Head title="Spreadsheets" />

            <div className="mb-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-4">
                <div className="min-w-0">
                    <h1 className="text-2xl font-semibold">Spreadsheets</h1>
                    <p className="mt-1 flex items-start gap-1.5 text-sm text-muted-foreground">
                        <Clock className="mt-0.5 size-4 shrink-0" />
                        Synced automatically every day at {schedule.time} ({schedule.timezone})
                    </p>
                </div>
                <SpreadsheetDialog
                    trigger={
                        <Button>
                            <Plus /> Add spreadsheet
                        </Button>
                    }
                />
            </div>

            <Card className="py-0">
                <Table className="min-w-[64rem] table-fixed">
                    <colgroup>
                        <col className="w-[25%]" />
                        <col className="w-[8%]" />
                        <col className="w-[12%]" />
                        <col className="w-[18%]" />
                        <col className="w-[18%]" />
                        <col className="w-[19%]" />
                    </colgroup>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="h-12 pl-6">Name</TableHead>
                            <TableHead className="h-12 px-4 text-center">Tabs</TableHead>
                            <TableHead className="h-12 px-4">Last result</TableHead>
                            <TableHead className="h-12 px-4">Last sync</TableHead>
                            <TableHead className="h-12 px-4">Next sync</TableHead>
                            <TableHead className="h-12 pr-6" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {spreadsheets.map((spreadsheet) => (
                            <TableRow key={spreadsheet.id}>
                                <TableCell className="py-3 pl-6">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <Link href={`/spreadsheets/${spreadsheet.id}`} className="truncate font-medium hover:underline">
                                            {spreadsheet.name}
                                        </Link>
                                        {!spreadsheet.is_active && <Badge variant="outline">disabled</Badge>}
                                    </div>
                                </TableCell>
                                <TableCell className="px-4 py-3 text-center">{spreadsheet.tabs_count}</TableCell>
                                <TableCell className="px-4 py-3">
                                    <StatusBadge log={spreadsheet.latest_log} />
                                </TableCell>
                                <TableCell className="px-4 py-3 text-muted-foreground">{formatDate(spreadsheet.last_synced_at)}</TableCell>
                                <TableCell className="px-4 py-3 text-muted-foreground">{spreadsheet.is_active ? formatDate(schedule.next_run) : 'off'}</TableCell>
                                <TableCell className="py-3 pr-6">
                                    <div className="flex justify-end gap-2">
                                        <SyncButton spreadsheetId={spreadsheet.id} variant="outline" size="sm" />
                                        <ConfirmButton
                                            url={`/spreadsheets/${spreadsheet.id}`}
                                            title={`Delete "${spreadsheet.name}"?`}
                                            description="Its tabs and mappings are removed from the config. Data that was already imported stays in the database."
                                            variant="ghost"
                                            size="icon-sm"
                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            aria-label={`Delete ${spreadsheet.name}`}
                                        >
                                            <Trash2 />
                                        </ConfirmButton>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                        {spreadsheets.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                                    No spreadsheets yet. Add one to get started.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </Card>
        </AppLayout>
    );
}
