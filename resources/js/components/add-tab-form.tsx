import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatDate } from '@/lib/format';
import type { Spreadsheet } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { Loader2, RefreshCw } from 'lucide-react';
import { useRef, useState, type FormEvent } from 'react';

export default function AddTabForm({ spreadsheet }: { spreadsheet: Spreadsheet }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', header_row: '1', is_active: true });
    const [fetching, setFetching] = useState(false);
    const headerRow = useRef<HTMLInputElement>(null);

    const added = spreadsheet.tabs?.map((tab) => tab.name) ?? [];
    const fetched = spreadsheet.google_tabs !== null;
    const tabs = (spreadsheet.google_tabs ?? []).filter((name) => !added.includes(name));

    const fetchTabs = () =>
        router.post(
            `/spreadsheets/${spreadsheet.id}/fetch-tabs`,
            {},
            { preserveScroll: true, onStart: () => setFetching(true), onFinish: () => setFetching(false) },
        );

    const select = (name: string) => {
        setData('name', name);
        // the header row is the other thing to check before adding
        setTimeout(() => headerRow.current?.select());
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/spreadsheets/${spreadsheet.id}/tabs`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle className="text-base">Add tab</CardTitle>
                    <CardDescription>Choose which tab of the file to import and the row its column headers are in.</CardDescription>
                </div>
                <div className="flex flex-col items-end gap-1">
                    <Button type="button" variant="outline" size="sm" onClick={fetchTabs} disabled={fetching}>
                        {fetching ? <Loader2 className="animate-spin" /> : <RefreshCw />}
                        Fetch tab names
                    </Button>
                    <span className="text-xs text-muted-foreground">
                        {fetched ? `Fetched ${formatDate(spreadsheet.tabs_fetched_at)}` : 'Not fetched yet'}
                    </span>
                </div>
            </CardHeader>
            <CardContent>
                {!fetched && <p className="text-sm text-muted-foreground">Fetch the tab names to see the tabs of the file.</p>}

                {fetched && tabs.length === 0 && (
                    <p className="text-sm text-muted-foreground">All tabs of the file are already added. Fetch the tab names again if the file has new tabs.</p>
                )}

                {tabs.length > 0 && (
                    <form onSubmit={submit} className="flex flex-wrap items-start gap-3">
                        <div className="min-w-64 flex-1 space-y-1">
                            <Label className="text-xs text-muted-foreground">Tab</Label>
                            <Select value={data.name} onValueChange={select}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Choose a tab" />
                                </SelectTrigger>
                                <SelectContent>
                                    {tabs.map((name) => (
                                        <SelectItem key={name} value={name}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.name} />
                        </div>
                        <div className="w-28 space-y-1">
                            <Label htmlFor="header-row" className="text-xs text-muted-foreground">
                                Header row
                            </Label>
                            <Input
                                id="header-row"
                                ref={headerRow}
                                type="number"
                                min={1}
                                value={data.header_row}
                                onChange={(e) => setData('header_row', e.target.value)}
                            />
                            <InputError message={errors.header_row} />
                        </div>
                        <Button type="submit" className="mt-5" disabled={processing || data.name === ''}>
                            {processing && <Loader2 className="animate-spin" />}
                            Add
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}
