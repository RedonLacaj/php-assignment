import ActiveBadge from '@/components/active-badge';
import ConfirmButton from '@/components/confirm-button';
import MappingList from '@/components/mapping-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import type { SheetTab } from '@/types';
import { Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

export default function TabCard({ tab }: { tab: SheetTab }) {
    return (
        <Card className={tab.is_active ? '' : 'opacity-60'}>
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <span className="font-medium whitespace-nowrap">{tab.name}</span>
                    <ActiveBadge active={tab.is_active} />
                    <span className="text-xs whitespace-nowrap text-muted-foreground">headers in row {tab.header_row}</span>
                </div>

                <div className="flex items-center gap-2">
                    <Button size="sm" variant="outline" asChild>
                        <Link href={`/tabs/${tab.id}/edit`}>
                            <Pencil /> Edit
                        </Link>
                    </Button>
                    <ConfirmButton
                        url={`/tabs/${tab.id}`}
                        title={`Delete "${tab.name}"?`}
                        description="The tab and its mappings are removed from the config. Imported data stays in the database."
                        size="sm"
                        variant="outline"
                        className="text-destructive"
                    >
                        <Trash2 /> Delete
                    </ConfirmButton>
                    <Button size="sm" asChild>
                        <Link href={`/tabs/${tab.id}/mappings/create`}>
                            <Plus /> Table mapping
                        </Link>
                    </Button>
                </div>
            </CardHeader>

            <CardContent>
                <MappingList mappings={tab.mappings} />
            </CardContent>
        </Card>
    );
}
