import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { COLUMN_TYPES, guessType, snakeCase } from '@/lib/columns';
import type { ColumnMapping, ColumnType, TableMapping } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronLeft, Download, Loader2, Plus, X } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

type Props = {
    tab: { id: number; name: string; spreadsheet: { id: number; name: string } };
    mapping: TableMapping | null;
};

type Preview = {
    headers: string[];
    samples: Record<string, unknown[]>;
    rows: Record<string, unknown>[];
    missing: string[];
};

const emptyColumn = (): ColumnMapping => ({ source_column: '', target_column: '', type: 'string' });

export default function MappingForm({ tab, mapping }: Props) {
    const { data, setData, post, put, processing, errors } = useForm({
        target_table: mapping?.target_table ?? '',
        mode: mapping?.mode ?? 'replace',
        is_active: mapping?.is_active ?? true,
        columns: mapping?.columns.length ? mapping.columns.map(({ source_column, target_column, type }) => ({ source_column, target_column, type })) : [emptyColumn()],
    });

    const [preview, setPreview] = useState<Preview | null>(null);
    const [loading, setLoading] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);

    const columnErrors = errors as Record<string, string | undefined>;

    const updateColumn = (index: number, patch: Partial<ColumnMapping>) =>
        setData('columns', data.columns.map((column, i) => (i === index ? { ...column, ...patch } : column)));

    const removeColumn = (index: number) => setData('columns', data.columns.filter((_, i) => i !== index));

    const fetchPreview = async (columns: ColumnMapping[]): Promise<Preview | null> => {
        const params = new URLSearchParams({ columns: JSON.stringify(columns.filter((c) => c.source_column && c.target_column)) });
        const response = await fetch(`/tabs/${tab.id}/preview?${params}`, { headers: { Accept: 'application/json' } });
        const json = await response.json();

        if (!response.ok) {
            throw new Error(json.message ?? 'Could not read the sheet');
        }

        return json;
    };

    // Refresh the preview a moment after the user stops editing columns
    const columnsKey = JSON.stringify(data.columns);

    useEffect(() => {
        const timer = setTimeout(() => {
            fetchPreview(data.columns)
                .then((result) => {
                    setPreview(result);
                    setPreviewError(null);
                })
                .catch((e: Error) => setPreviewError(e.message));
        }, 500);

        return () => clearTimeout(timer);
    }, [columnsKey]);

    const loadHeaders = async (silent = false) => {
        setLoading(true);

        try {
            const result = await fetchPreview([]);
            if (!result) return;

            const existing = data.columns.filter((c) => c.source_column.trim() !== '' || c.target_column.trim() !== '');
            const known = existing.map((c) => c.source_column.trim().toLowerCase());
            const added = result.headers
                .filter((header) => !known.includes(header.toLowerCase()))
                .map((header) => ({
                    source_column: header,
                    target_column: snakeCase(header),
                    type: guessType(result.samples[header] ?? []),
                }));

            setData({
                ...data,
                target_table: data.target_table || snakeCase(tab.name),
                columns: [...existing, ...added],
            });

            if (!silent) toast.success(added.length ? `Added ${added.length} columns from the sheet` : 'All headers are already mapped');
        } catch (e) {
            toast.error((e as Error).message);
        } finally {
            setLoading(false);
        }
    };

    // A new mapping starts with the columns of the sheet already filled in
    useEffect(() => {
        if (!mapping) {
            loadHeaders(true);
        }
    }, []);

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (mapping) {
            put(`/mappings/${mapping.id}`);
        } else {
            post(`/tabs/${tab.id}/mappings`);
        }
    };

    const previewColumns = data.columns.filter((c) => c.target_column && preview?.rows[0] && c.target_column in preview.rows[0]);

    return (
        <AppLayout>
            <Head title="Table mapping" />

            <Link href={`/tabs/${tab.id}/edit`} className="mb-2 inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
                <ChevronLeft className="size-4" /> {tab.spreadsheet.name} / {tab.name}
            </Link>
            <h1 className="text-2xl font-semibold">{mapping ? 'Edit table mapping' : 'New table mapping'}</h1>
            <p className="mb-6 text-muted-foreground">Tab: {tab.name}</p>

            <form onSubmit={submit} className="space-y-6">
                <Card>
                    <CardContent className="grid gap-4 md:grid-cols-[2fr_2fr_1fr]">
                        <div className="space-y-2">
                            <Label htmlFor="target_table">Database table</Label>
                            <Input id="target_table" value={data.target_table} onChange={(e) => setData('target_table', e.target.value)} placeholder="sales" />
                            <InputError message={errors.target_table} />
                        </div>
                        <div className="space-y-2">
                            <Label>Mode</Label>
                            <Select value={data.mode} onValueChange={(v) => setData('mode', v as 'replace' | 'append')}>
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="replace">Replace - keep only the latest data</SelectItem>
                                    <SelectItem value="append">Append - add a snapshot on every sync</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="is-active">Status</Label>
                            <div className="flex h-9 items-center gap-2">
                                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                                <Label htmlFor="is_active">Active</Label>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between">
                        <div className="space-y-1.5">
                            <CardTitle>Columns</CardTitle>
                            <CardDescription>
                                Sheet columns are matched by header name, so their order in the file doesn't matter. New columns are added to the table on the next sync.
                            </CardDescription>
                        </div>
                        <Button type="button" variant="outline" onClick={() => loadHeaders()} disabled={loading}>
                            {loading ? <Loader2 className="animate-spin" /> : <Download />}
                            Load headers from sheet
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="grid grid-cols-[1fr_1fr_160px_40px] gap-2 text-xs font-medium text-muted-foreground">
                            <span>Column in sheet (header)</span>
                            <span>Column in table</span>
                            <span>Type</span>
                            <span />
                        </div>

                        {data.columns.map((column, i) => (
                            <div key={i} className="grid grid-cols-[1fr_1fr_160px_40px] items-start gap-2">
                                <div>
                                    <Input
                                        value={column.source_column}
                                        onChange={(e) => updateColumn(i, { source_column: e.target.value })}
                                        className={preview?.missing.includes(column.source_column) ? 'border-amber-500' : ''}
                                    />
                                    {preview?.missing.includes(column.source_column) && <p className="text-xs text-amber-700">Not found in the sheet</p>}
                                    <InputError message={columnErrors[`columns.${i}.source_column`]} />
                                </div>
                                <div>
                                    <Input value={column.target_column} onChange={(e) => updateColumn(i, { target_column: e.target.value })} />
                                    <InputError message={columnErrors[`columns.${i}.target_column`]} />
                                </div>
                                <Select value={column.type} onValueChange={(v) => updateColumn(i, { type: v as ColumnType })}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {COLUMN_TYPES.map((type) => (
                                            <SelectItem key={type} value={type}>
                                                {type}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button type="button" variant="ghost" size="icon" onClick={() => removeColumn(i)} aria-label="Remove column">
                                    <X />
                                </Button>
                            </div>
                        ))}

                        <InputError message={errors.columns} />

                        <Button type="button" variant="ghost" size="sm" onClick={() => setData('columns', [...data.columns, emptyColumn()])}>
                            <Plus /> Add column
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Preview</CardTitle>
                        <CardDescription>First rows of the sheet, converted the same way the sync will save them.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {previewError && <p className="text-sm text-destructive">{previewError}</p>}
                        {!previewError && previewColumns.length === 0 && <p className="text-sm text-muted-foreground">Map some columns to see a preview.</p>}
                        {!previewError && previewColumns.length > 0 && (
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            {previewColumns.map((c) => (
                                                <TableHead key={c.target_column}>
                                                    {c.target_column} <Badge variant="outline" className="ml-1 font-normal">{c.type}</Badge>
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {preview!.rows.map((row, r) => (
                                            <TableRow key={r}>
                                                {previewColumns.map((c) => (
                                                    <TableCell key={c.target_column} className="font-mono text-xs">
                                                        {row[c.target_column] === null ? <span className="text-muted-foreground">NULL</span> : String(row[c.target_column])}
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" disabled={processing}>
                        Save mapping
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={`/tabs/${tab.id}/edit`}>Cancel</Link>
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
