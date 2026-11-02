import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import type { Spreadsheet } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { CircleCheck, Loader2, TriangleAlert } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';

type Lookup =
    | { state: 'checking' }
    | { state: 'found'; title: string; existing: { id: number; name: string } | null }
    | { state: 'error'; message: string };

export default function SpreadsheetDialog({ spreadsheet, trigger }: { spreadsheet?: Spreadsheet; trigger: ReactNode }) {
    const [open, setOpen] = useState(false);
    const [lookup, setLookup] = useState<Lookup | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: spreadsheet?.name ?? '',
        google_id: spreadsheet?.google_id ?? '',
        is_active: spreadsheet?.is_active ?? true,
    });

    // last title filled in from Google, so a name the user typed is never overwritten
    const autoName = useRef('');

    // When adding, check the pasted URL with Google and fill in the name from its title
    useEffect(() => {
        if (spreadsheet || data.google_id.trim() === '') {
            setLookup(null);
            return;
        }

        setLookup({ state: 'checking' });
        const controller = new AbortController();

        const timer = setTimeout(async () => {
            try {
                const response = await fetch(`/spreadsheets/lookup?${new URLSearchParams({ google_id: data.google_id })}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                const json = await response.json();

                if (!response.ok) {
                    setLookup({ state: 'error', message: json.message });
                    return;
                }

                setLookup({ state: 'found', title: json.title, existing: json.existing });
                setData((prev) => {
                    if (prev.name !== '' && prev.name !== autoName.current) return prev;
                    autoName.current = json.title;
                    return { ...prev, name: json.title };
                });
            } catch {
                // aborted because the URL changed again
            }
        }, 500);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [data.google_id]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { onSuccess: () => setOpen(false) };

        if (spreadsheet) {
            put(`/spreadsheets/${spreadsheet.id}`, options);
        } else {
            post('/spreadsheets', options);
        }
    };

    const onOpenChange = (value: boolean) => {
        setOpen(value);
        if (!value) {
            clearErrors();
            if (!spreadsheet) {
                reset();
                autoName.current = '';
            }
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{spreadsheet ? 'Edit spreadsheet' : 'Add spreadsheet'}</DialogTitle>
                        <DialogDescription>
                            Share the spreadsheet with the service account email first, Viewer access is enough.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="google_id">Spreadsheet URL or ID</Label>
                        <Input
                            id="google_id"
                            value={data.google_id}
                            onChange={(e) => setData('google_id', e.target.value)}
                            placeholder="https://docs.google.com/spreadsheets/d/..."
                            autoFocus
                        />
                        <InputError message={errors.google_id} />
                        <LookupResult lookup={lookup} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {!spreadsheet && <p className="text-xs text-muted-foreground">Filled in from the spreadsheet title, you can change it.</p>}
                        <InputError message={errors.name} />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                        <Label htmlFor="is_active">Include in daily sync</Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function LookupResult({ lookup }: { lookup: Lookup | null }) {
    if (!lookup) {
        return null;
    }

    if (lookup.state === 'checking') {
        return (
            <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                <Loader2 className="size-4 animate-spin" /> Checking the spreadsheet...
            </p>
        );
    }

    if (lookup.state === 'error') {
        return (
            <p className="flex items-start gap-1.5 text-sm text-destructive">
                <TriangleAlert className="mt-0.5 size-4 shrink-0" /> {lookup.message}
            </p>
        );
    }

    if (lookup.existing) {
        return (
            <p className="flex items-center gap-1.5 text-sm text-amber-700">
                <TriangleAlert className="size-4 shrink-0" /> Already added as "{lookup.existing.name}".
                <Link href={`/spreadsheets/${lookup.existing.id}`} className="font-medium underline">
                    Open it
                </Link>
            </p>
        );
    }

    return (
        <p className="flex items-center gap-1.5 text-sm text-green-700">
            <CircleCheck className="size-4 shrink-0" /> Found "{lookup.title}"
        </p>
    );
}
