import ActiveBadge from '@/components/active-badge';
import ConfirmButton from '@/components/confirm-button';
import InputError from '@/components/input-error';
import MappingList from '@/components/mapping-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { SheetTab } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronLeft, Loader2, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    tab: SheetTab;
    spreadsheet: { id: number; name: string };
};

export default function Edit({ tab, spreadsheet }: Props) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        name: tab.name,
        header_row: String(tab.header_row),
        is_active: tab.is_active,
    });

    const save = (e: FormEvent) => {
        e.preventDefault();
        put(`/tabs/${tab.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={tab.name} />

            <Link href={`/spreadsheets/${spreadsheet.id}`} className="mb-2 inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
                <ChevronLeft className="size-4" /> {spreadsheet.name}
            </Link>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <h1 className="flex items-center gap-3 text-2xl font-semibold">
                    {tab.name}
                    <ActiveBadge active={tab.is_active} />
                </h1>
                <ConfirmButton
                    url={`/tabs/${tab.id}`}
                    title={`Delete "${tab.name}"?`}
                    description="The tab and its mappings are removed from the config. Imported data stays in the database."
                    variant="outline"
                    className="text-destructive"
                >
                    <Trash2 /> Delete
                </ConfirmButton>
            </div>

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Settings</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={save} className="space-y-4">
                            <div className="flex flex-wrap gap-x-12 gap-y-4">
                                <div className="w-32 space-y-2">
                                    <Label htmlFor="header-row">Header row</Label>
                                    <Input
                                        id="header-row"
                                        type="number"
                                        min={1}
                                        value={data.header_row}
                                        onChange={(e) => setData('header_row', e.target.value)}
                                    />
                                    <InputError message={errors.header_row} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="is-active">Status</Label>
                                    <div className="flex h-9 items-center gap-2">
                                        <Checkbox id="is-active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                                        <Label htmlFor="is-active" className="font-normal">
                                            Active, included in the sync
                                        </Label>
                                    </div>
                                </div>
                            </div>
                            <div className="flex justify-end border-t pt-4">
                                <Button type="submit" disabled={processing || !isDirty}>
                                    {processing && <Loader2 className="animate-spin" />}
                                    Save changes
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle className="text-base">Table mappings</CardTitle>
                            <CardDescription>Where the data of this tab is saved. One tab can be saved into several tables.</CardDescription>
                        </div>
                        <Button size="sm" asChild>
                            <Link href={`/tabs/${tab.id}/mappings/create`}>
                                <Plus /> Table mapping
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <MappingList mappings={tab.mappings} editable />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
