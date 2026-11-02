import StatusBadge from '@/components/status-badge';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { duration, formatDate } from '@/lib/format';
import type { SyncLog } from '@/types';
import { Link } from '@inertiajs/react';

export default function SyncLogTable({ logs, showSpreadsheet = false }: { logs: SyncLog[]; showSpreadsheet?: boolean }) {
    return (
        <Card className="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="pl-4">Started</TableHead>
                        {showSpreadsheet && <TableHead>Spreadsheet</TableHead>}
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Rows</TableHead>
                        <TableHead className="text-right">Duration</TableHead>
                        <TableHead className="pr-4">Details</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {logs.map((log) => (
                        <TableRow key={log.id}>
                            <TableCell className="pl-4 whitespace-nowrap">{formatDate(log.started_at)}</TableCell>
                            {showSpreadsheet && (
                                <TableCell>
                                    {log.spreadsheet && (
                                        <Link href={`/spreadsheets/${log.spreadsheet.id}`} className="hover:underline">
                                            {log.spreadsheet.name}
                                        </Link>
                                    )}
                                </TableCell>
                            )}
                            <TableCell>
                                <StatusBadge log={log} />
                            </TableCell>
                            <TableCell className="text-right">{log.rows_saved}</TableCell>
                            <TableCell className="text-right">{duration(log.started_at, log.finished_at)}</TableCell>
                            <TableCell className="max-w-md pr-4 text-xs whitespace-pre-line text-muted-foreground">{log.message}</TableCell>
                        </TableRow>
                    ))}
                    {logs.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={showSpreadsheet ? 6 : 5} className="py-6 text-center text-muted-foreground">
                                No syncs yet.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </Card>
    );
}
