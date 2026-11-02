import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { Loader2, RefreshCw } from 'lucide-react';
import { useState, type ComponentProps } from 'react';

type Props = { spreadsheetId: number } & Omit<ComponentProps<typeof Button>, 'onClick'>;

export default function SyncButton({ spreadsheetId, ...props }: Props) {
    const [syncing, setSyncing] = useState(false);

    const sync = () =>
        router.post(
            `/spreadsheets/${spreadsheetId}/sync`,
            {},
            {
                preserveScroll: true,
                onStart: () => setSyncing(true),
                onFinish: () => setSyncing(false),
            },
        );

    return (
        <Button onClick={sync} disabled={syncing} {...props}>
            {syncing ? <Loader2 className="animate-spin" /> : <RefreshCw />}
            {syncing ? 'Syncing...' : 'Sync now'}
        </Button>
    );
}
