import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { SyncLog } from '@/types';

const styles: Record<string, string> = {
    success: 'bg-green-100 text-green-800',
    warning: 'bg-amber-100 text-amber-800',
    failed: 'bg-red-100 text-red-800',
    running: 'bg-blue-100 text-blue-800',
};

export default function StatusBadge({ log }: { log?: SyncLog | null }) {
    if (!log) {
        return <span className="text-muted-foreground">-</span>;
    }

    const badge = (
        <Badge variant="secondary" className={cn('capitalize', styles[log.status])}>
            {log.status}
        </Badge>
    );

    if (!log.message) {
        return badge;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{badge}</TooltipTrigger>
            <TooltipContent className="max-w-sm whitespace-pre-line">{log.message}</TooltipContent>
        </Tooltip>
    );
}
