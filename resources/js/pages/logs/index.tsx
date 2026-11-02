import SyncLogTable from '@/components/sync-log-table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Paginated, SyncLog } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function Index({ logs }: { logs: Paginated<SyncLog> }) {
    return (
        <AppLayout>
            <Head title="Sync log" />

            <h1 className="mb-6 text-2xl font-semibold">Sync log</h1>

            <SyncLogTable logs={logs.data} showSpreadsheet />

            {logs.meta.last_page > 1 && (
                <nav className="mt-4 flex flex-wrap gap-1">
                    {logs.meta.links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                preserveScroll
                                className={cn('rounded-md border px-3 py-1 text-sm hover:bg-muted', link.active && 'bg-primary text-primary-foreground hover:bg-primary')}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span key={i} className="rounded-md px-3 py-1 text-sm text-muted-foreground" dangerouslySetInnerHTML={{ __html: link.label }} />
                        ),
                    )}
                </nav>
            )}
        </AppLayout>
    );
}
