export function formatDate(value: string | null): string {
    if (!value) {
        return 'never';
    }

    return new Date(value).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function duration(start: string, end: string | null): string {
    if (!end) {
        return '-';
    }

    const seconds = Math.round((new Date(end).getTime() - new Date(start).getTime()) / 1000);

    return seconds < 60 ? `${seconds}s` : `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}
