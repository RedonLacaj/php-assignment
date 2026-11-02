import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export default function ActiveBadge({ active }: { active: boolean }) {
    return (
        <Badge variant="secondary" className={cn(active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600')}>
            {active ? 'Active' : 'Not active'}
        </Badge>
    );
}
