import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Sheet } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import { toast } from 'sonner';

const nav = [
    { label: 'Spreadsheets', href: '/spreadsheets', match: ['/spreadsheets', '/mappings', '/tabs'] },
    { label: 'Sync log', href: '/logs', match: ['/logs'] },
];

export default function AppLayout({ children }: { children: ReactNode }) {
    const { url, props } = usePage<SharedProps>();
    const { flash } = props;

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.warning) toast.warning(flash.warning, { duration: 10000 });
        if (flash.error) toast.error(flash.error, { duration: 10000 });
    }, [flash]);

    return (
        <TooltipProvider>
            <header className="border-b bg-white">
                <div className="mx-auto flex h-14 max-w-6xl items-center gap-6 px-4">
                    <Link href="/spreadsheets" className="flex items-center gap-2 font-semibold">
                        <Sheet className="size-5 text-green-600" />
                        {props.appName}
                    </Link>
                    <nav className="flex gap-1 text-sm">
                        {nav.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-muted-foreground hover:bg-muted hover:text-foreground',
                                    item.match.some((m) => url.startsWith(m)) && 'bg-muted text-foreground',
                                )}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-4 py-8">{children}</main>

            <Toaster position="top-right" richColors closeButton toastOptions={{ className: 'whitespace-pre-line' }} />
        </TooltipProvider>
    );
}
