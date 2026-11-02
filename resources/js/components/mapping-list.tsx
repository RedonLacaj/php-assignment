import ActiveBadge from '@/components/active-badge';
import ConfirmButton from '@/components/confirm-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { TableMapping } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight, Pencil, Trash2 } from 'lucide-react';

// Table mappings of a tab, with Edit / Remove only where the tab is edited
export default function MappingList({ mappings, editable = false }: { mappings: TableMapping[]; editable?: boolean }) {
    if (mappings.length === 0) {
        return <p className="text-sm text-muted-foreground">No mappings yet, this tab is not imported. Add a table mapping to choose where its data goes.</p>;
    }

    return (
        <div className="space-y-2">
            {mappings.map((mapping) => (
                <div key={mapping.id} className="rounded-lg border bg-muted/40 p-3">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2 text-sm">
                            <span className="text-muted-foreground">Table</span>
                            <code className="rounded bg-white px-1.5 py-0.5 font-semibold">{mapping.target_table}</code>
                            <Badge variant="outline">{mapping.mode}</Badge>
                            <ActiveBadge active={mapping.is_active} />
                        </div>
                        {editable && (
                            <div className="flex gap-2">
                                <Button size="sm" variant="outline" asChild>
                                    <Link href={`/mappings/${mapping.id}/edit`}>
                                        <Pencil /> Edit
                                    </Link>
                                </Button>
                                <ConfirmButton
                                    url={`/mappings/${mapping.id}`}
                                    title={`Delete the mapping to "${mapping.target_table}"?`}
                                    description="The table and its data are kept, only the mapping is removed."
                                    size="sm"
                                    variant="outline"
                                    className="text-destructive"
                                >
                                    <Trash2 /> Delete
                                </ConfirmButton>
                            </div>
                        )}
                    </div>
                    <div className="mt-2 flex flex-col flex-wrap gap-4 text-xs">
                        {mapping.columns.map((column) => (
                            <span key={column.target_column} className="flex items-center gap-1 whitespace-nowrap">
                                {column.source_column}
                                <ArrowRight className="size-3 text-muted-foreground" />
                                <code>{column.target_column}</code>
                                <span className="text-muted-foreground">{column.type}</span>
                            </span>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
