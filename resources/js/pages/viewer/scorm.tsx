import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useEffect, useRef, useState } from 'react';
import { Scorm12API, Scorm2004API } from 'scorm-again';

type SCO = {
    id: number;
    title: string;
    launch_path: string;
    identifier: string;
};

type Version = {
    id: number;
    material: {
        title: string;
        subject: {
            title: string;
            training: {
                title: string;
            };
        };
    };
    scorm_package?: {
        scorm_version: string;
        extract_path: string;
        launch_path: string;
        scos: SCO[];
    };
    scormPackage?: {
        scorm_version: string;
        extract_path: string;
        launch_path: string;
        scos: SCO[];
    };
};

type Props = {
    version: Version;
    isEmbedded?: boolean;
};

export default function ScormViewer({ version, isEmbedded }: Props) {
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const pkg = version.scorm_package || version.scormPackage;
    const scos = pkg?.scos || [];
    const defaultSco: SCO | null = scos[0] || (pkg?.launch_path ? {
        id: 0,
        title: version.material.title,
        launch_path: pkg.launch_path,
        identifier: 'default'
    } : null);

    const [activeSco, setActiveSco] = useState<SCO | null>(defaultSco);

    useEffect(() => {
        const settings = {
            autocommit: true,
            autocommitSeconds: 30,
            logLevel: 4 // INFO
        };

        let api: any = null;

        try {
            if (pkg?.scorm_version === '2004') {
                api = new Scorm2004API(settings);
                (window as any).API_1484_11 = api;
            } else {
                api = new Scorm12API(settings);
                (window as any).API = api;
            }

            api.on('LMSInitialize', () => console.log('SCORM Initialized'));
            api.on('LMSSetValue.cmi.score.raw', (CMIElement: any, value: any) => console.log('Score:', value));
            api.on('LMSCommit', () => console.log('SCORM Data Committed'));
            api.on('LMSFinish', () => console.log('SCORM Finished'));
        } catch (e) {
            console.warn('SCORM API initialization skipped/failed:', e);
        }

        return () => {
            if ((window as any).API) delete (window as any).API;
            if ((window as any).API_1484_11) delete (window as any).API_1484_11;
        };
    }, [pkg?.scorm_version]);

    const scormBaseUrl = pkg?.extract_path ? `/storage/${pkg.extract_path}/` : '';
    const activePath = activeSco?.launch_path || pkg?.launch_path || '';
    const launchUrl = activePath ? `${scormBaseUrl}${activePath}` : '';

    const showScoSidebar = scos.length > 1;

    return (
        <>
            <Head title={`SCORM: ${version.material.title}`} />
            <div className="flex h-screen w-full overflow-hidden bg-background">
                {/* Sidebar for SCOs (only if multiple or not embedded) */}
                {showScoSidebar && (
                    <div className="w-56 border-r border-border flex flex-col bg-muted/20 shrink-0">
                        {!isEmbedded && (
                            <div className="p-3 border-b border-border">
                                <Button variant="outline" size="sm" asChild className="mb-2 w-full justify-start">
                                    <Link href="/dashboard">
                                        <ArrowLeft className="mr-2 h-4 w-4" /> Kembali
                                    </Link>
                                </Button>
                                <h2 className="font-semibold text-xs leading-snug">{version.material.title}</h2>
                            </div>
                        )}
                        <div className="flex-1 overflow-y-auto p-3 space-y-1.5">
                            <h3 className="text-[10px] font-semibold uppercase text-muted-foreground mb-1">Modul (SCO)</h3>
                            {scos.map(sco => (
                                <button
                                    key={sco.id}
                                    onClick={() => setActiveSco(sco)}
                                    className={`w-full text-left px-2.5 py-1.5 text-xs rounded-md transition-colors ${
                                        activeSco?.id === sco.id 
                                            ? 'bg-primary text-primary-foreground font-medium' 
                                            : 'hover:bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {sco.title}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
                
                {/* Main Viewer Area */}
                <div className="flex-1 flex flex-col h-full overflow-hidden">
                    <div className="flex-1 w-full bg-white relative">
                        {launchUrl ? (
                            <iframe
                                ref={iframeRef}
                                src={launchUrl}
                                className="absolute inset-0 w-full h-full border-0"
                                allow="autoplay; fullscreen"
                            />
                        ) : (
                            <div className="flex items-center justify-center h-full text-muted-foreground text-sm">
                                Modul SCORM belum siap atau tidak ditemukan.
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

ScormViewer.layout = (page: any) => page; // Fullscreen layout
