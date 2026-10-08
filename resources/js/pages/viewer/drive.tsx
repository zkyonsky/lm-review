import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Version = {
    id: number;
    preview_url: string;
    material: {
        title: string;
        type: string;
        subject: {
            title: string;
            training: {
                title: string;
            };
        };
    };
};

type Props = {
    version: Version;
    isEmbedded?: boolean;
};

export default function DriveViewer({ version, isEmbedded }: Props) {
    if (isEmbedded) {
        return (
            <div className="w-full h-screen bg-muted overflow-hidden relative">
                {version.preview_url ? (
                    <iframe
                        src={version.preview_url}
                        className="absolute inset-0 w-full h-full border-0"
                        allow="autoplay; fullscreen"
                    />
                ) : (
                    <div className="flex items-center justify-center h-full text-muted-foreground text-sm">
                        Tautan atau file materi belum tersedia.
                    </div>
                )}
            </div>
        );
    }

    return (
        <>
            <Head title={`Preview: ${version.material.title}`} />
            <div className="flex flex-col h-screen">
                <div className="flex items-center gap-4 p-4 border-b border-border bg-background">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/dashboard">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">{version.material.title}</h1>
                        <p className="text-sm text-muted-foreground">
                            {version.material.subject?.training?.title} &raquo; {version.material.subject?.title}
                        </p>
                    </div>
                </div>
                <div className="flex-1 w-full bg-muted overflow-hidden relative">
                    {version.preview_url ? (
                        <iframe
                            src={version.preview_url}
                            className="absolute inset-0 w-full h-full border-0"
                            allow="autoplay; fullscreen"
                        />
                    ) : (
                        <div className="flex items-center justify-center h-full text-muted-foreground text-sm">
                            Tautan atau file materi belum tersedia.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

DriveViewer.layout = (page: any) => page; // No standard sidebar layout, just fullscreen viewer
