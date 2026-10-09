import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginationProps = {
    links: PaginationLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
    itemName?: string;
};

export default function Pagination({
    links,
    from,
    to,
    total,
    itemName = 'data',
}: PaginationProps) {
    if (!links || links.length <= 1) {
        return null;
    }

    const renderLabel = (label: string) => {
        if (label.includes('Previous')) {
            return (
                <span className="flex items-center gap-1">
                    <ChevronLeft className="h-4 w-4" />
                    <span className="hidden sm:inline">Sebelumnya</span>
                </span>
            );
        }
        if (label.includes('Next')) {
            return (
                <span className="flex items-center gap-1">
                    <span className="hidden sm:inline">Berikutnya</span>
                    <ChevronRight className="h-4 w-4" />
                </span>
            );
        }
        // Remove HTML entities if any (like &laquo; or &raquo;)
        return <span dangerouslySetInnerHTML={{ __html: label }} />;
    };

    return (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border-t border-border bg-card">
            <div className="text-sm text-muted-foreground">
                {total !== undefined && total > 0 ? (
                    <>
                        Menampilkan <span className="font-medium text-foreground">{from ?? 0}</span> sampai{' '}
                        <span className="font-medium text-foreground">{to ?? 0}</span> dari{' '}
                        <span className="font-medium text-foreground">{total}</span> {itemName}
                    </>
                ) : (
                    <span>Tidak ada {itemName}</span>
                )}
            </div>

            <div className="flex items-center gap-1">
                {links.map((link, idx) => {
                    const isPrev = link.label.includes('Previous');
                    const isNext = link.label.includes('Next');

                    if (!link.url) {
                        return (
                            <Button
                                key={idx}
                                variant="outline"
                                size="sm"
                                disabled
                                className="h-8 px-2 sm:px-3 text-xs opacity-50"
                            >
                                {renderLabel(link.label)}
                            </Button>
                        );
                    }

                    return (
                        <Button
                            key={idx}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            asChild
                            className={`h-8 px-2 sm:px-3 text-xs ${
                                link.active ? 'font-semibold' : ''
                            }`}
                        >
                            <Link
                                href={link.url}
                                preserveScroll
                                preserveState
                            >
                                {renderLabel(link.label)}
                            </Link>
                        </Button>
                    );
                })}
            </div>
        </div>
    );
}
