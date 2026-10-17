import { useState } from 'react';
import { router } from '@inertiajs/react';
import { 
    Sparkles, 
    RotateCcw, 
    BookCheck, 
    AlertTriangle, 
    CheckCircle2, 
    XCircle, 
    HelpCircle, 
    Scale, 
    FileText, 
    ChevronDown, 
    ChevronUp,
    SendHorizonal,
    ArrowRight
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { toast } from 'sonner';

export type IdentifiedRegulation = {
    title: string;
    status: 'valid' | 'amended' | 'revoked' | 'unknown' | string;
    status_label?: string;
    latest_regulation?: string;
    notes?: string;
};

export type MaterialAiAnalysis = {
    id: number;
    model: string;
    status: string;
    summary?: string | null;
    identified_regulations?: IdentifiedRegulation[] | null;
    recommendations?: string[] | null;
    suggested_conclusion?: string | null;
    conclusion_rationale?: string | null;
    error_message?: string | null;
    created_at: string;
};

type Props = {
    reviewId: number;
    analysis?: MaterialAiAnalysis | null;
    role: 'reviewer' | 'developer';
    onApplyConclusion?: (conclusion: string) => void;
};

export default function AiAnalysisPanel({
    reviewId,
    analysis,
    role,
    onApplyConclusion,
}: Props) {
    const [isLoading, setIsLoading] = useState(false);
    const [customContext, setCustomContext] = useState('');
    const [showContextInput, setShowContextInput] = useState(false);

    const handleRunAnalysis = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        setIsLoading(true);

        router.post(
            `/reviews/${reviewId}/ai-analyze`,
            {
                custom_context: customContext.trim() || undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsLoading(false);
                    setShowContextInput(false);
                },
                onError: (errors) => {
                    setIsLoading(false);
                    toast.error('Gagal menjalankan analisis AI: ' + (Object.values(errors)[0] || 'Terjadi kesalahan'));
                },
                onFinish: () => {
                    setIsLoading(false);
                },
            }
        );
    };

    const getRegulationBadge = (status: string) => {
        switch (status) {
            case 'valid':
                return (
                    <Badge variant="outline" className="border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400 gap-1 text-[11px]">
                        <CheckCircle2 className="h-3 w-3" /> Masih Berlaku
                    </Badge>
                );
            case 'amended':
                return (
                    <Badge variant="outline" className="border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-400 gap-1 text-[11px]">
                        <AlertTriangle className="h-3 w-3" /> Telah Diubah
                    </Badge>
                );
            case 'revoked':
                return (
                    <Badge variant="outline" className="border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-400 gap-1 text-[11px]">
                        <XCircle className="h-3 w-3" /> Telah Dicabut
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline" className="border-slate-300 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400 gap-1 text-[11px]">
                        <HelpCircle className="h-3 w-3" /> Perlu Verifikasi
                    </Badge>
                );
        }
    };

    return (
        <div className="flex flex-col h-full space-y-4">
            {/* Top Toolbar */}
            <div className="flex items-center justify-between pb-3 border-b border-border">
                <div className="flex items-center gap-2">
                    <span className="font-semibold text-sm text-foreground flex items-center gap-1.5">
                        <Scale className="h-4 w-4 text-primary" /> Telaah Regulasi & Kebijakan
                    </span>
                    <Badge variant="secondary" className="text-[10px] bg-primary/10 text-primary border-primary/20 flex items-center gap-1">
                        <Sparkles className="h-3 w-3" /> Gemini 3.8 Flash
                    </Badge>
                </div>

                {analysis && !isLoading && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setShowContextInput(!showContextInput)}
                        className="h-7 text-xs text-muted-foreground hover:text-foreground gap-1"
                    >
                        <RotateCcw className="h-3 w-3" />
                        <span>Analisis Ulang</span>
                        {showContextInput ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                    </Button>
                )}
            </div>

            {/* Context Input Accordion */}
            {showContextInput && (
                <div className="p-3 bg-muted/40 border border-border rounded-lg space-y-2 animate-in fade-in duration-150">
                    <label className="text-xs font-medium text-foreground block">
                        Konteks / Catatan Tambahan (Opsional)
                    </label>
                    <textarea
                        className="w-full text-xs p-2 rounded-md border border-input bg-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring min-h-[60px]"
                        placeholder="Masukkan catatan spesifik, pasal, atau cuplikan materi yang ingin difokuskan..."
                        value={customContext}
                        onChange={(e) => setCustomContext(e.target.value)}
                    />
                    <div className="flex justify-end gap-2">
                        <Button 
                            type="button" 
                            variant="ghost" 
                            size="sm" 
                            onClick={() => setShowContextInput(false)} 
                            className="h-7 text-xs"
                        >
                            Batal
                        </Button>
                        <Button 
                            type="button" 
                            size="sm" 
                            onClick={() => handleRunAnalysis()} 
                            disabled={isLoading}
                            className="h-7 text-xs gap-1.5"
                        >
                            <SendHorizonal className="h-3 w-3" /> Jalankan
                        </Button>
                    </div>
                </div>
            )}

            {/* Content Area */}
            {isLoading ? (
                <div className="flex flex-col items-center justify-center py-16 px-4 text-center space-y-3">
                    <div className="relative">
                        <div className="h-10 w-10 rounded-full border-2 border-primary/30 border-t-primary animate-spin" />
                        <Sparkles className="h-5 w-5 text-primary absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 animate-pulse" />
                    </div>
                    <div className="space-y-1">
                        <h4 className="text-sm font-semibold text-foreground">Sedang Menganalisis dengan Gemini 3.8 Flash...</h4>
                        <p className="text-xs text-muted-foreground max-w-xs leading-relaxed">
                            Membaca substansi materi, mengekstrak dasar hukum, dan memeriksa peraturan/kebijakan terbaru di Indonesia.
                        </p>
                    </div>
                </div>
            ) : !analysis ? (
                /* Empty state - haven't analyzed yet */
                <Card className="p-6 text-center border-dashed">
                    <div className="flex flex-col items-center justify-center space-y-3">
                        <div className="p-3.5 rounded-full bg-primary/10 text-primary">
                            <Sparkles className="h-6 w-6" />
                        </div>
                        <div className="space-y-1">
                            <h4 className="text-sm font-semibold text-foreground">Belum Ada Analisis AI</h4>
                            <p className="text-xs text-muted-foreground max-w-xs leading-relaxed">
                                Gunakan kecerdasan buatan <strong>Gemini 3.8 Flash</strong> untuk menelaah kesesuaian materi ini terhadap peraturan perundang-undangan dan kebijakan terkini.
                            </p>
                        </div>

                        <div className="w-full pt-2 space-y-2">
                            <textarea
                                className="w-full text-xs p-2.5 rounded-md border border-input bg-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring min-h-[50px]"
                                placeholder="Tambah catatan/cuplikan fokus materi (opsional)..."
                                value={customContext}
                                onChange={(e) => setCustomContext(e.target.value)}
                            />
                            <Button 
                                type="button" 
                                className="w-full gap-2 shadow-sm"
                                onClick={() => handleRunAnalysis()}
                            >
                                <Sparkles className="h-4 w-4" /> Mulai Analisis Regulasi AI
                            </Button>
                        </div>
                    </div>
                </Card>
            ) : (
                /* Result display */
                <div className="space-y-4 pb-4">
                    {/* Notice if simulation mode */}
                    {analysis.error_message && (
                        <div className="p-2.5 rounded-md bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 text-xs flex items-start gap-2">
                            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                            <span>{analysis.error_message}</span>
                        </div>
                    )}

                    {/* Ringkasan Substansi */}
                    {analysis.summary && (
                        <div className="space-y-1.5">
                            <h5 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                                <FileText className="h-3.5 w-3.5 text-blue-500" /> Ringkasan Substansi
                            </h5>
                            <p className="text-xs text-foreground bg-muted/30 p-3 rounded-lg border border-border leading-relaxed">
                                {analysis.summary}
                            </p>
                        </div>
                    )}

                    {/* Rekomendasi Kesimpulan Reviu */}
                    {analysis.suggested_conclusion && (
                        <div className="p-3 bg-primary/5 border border-primary/20 rounded-lg space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-[11px] font-semibold text-primary uppercase tracking-wider flex items-center gap-1">
                                    <BookCheck className="h-3.5 w-3.5" /> Rekomendasi Kesimpulan Reviu
                                </span>
                                {role === 'reviewer' && onApplyConclusion && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            onApplyConclusion(analysis.suggested_conclusion!);
                                            toast.success('Rekomendasi kesimpulan berhasil disalin ke formulir reviu!');
                                        }}
                                        className="h-6 text-[11px] border-primary/30 text-primary hover:bg-primary/10 gap-1 px-2"
                                    >
                                        <span>Gunakan</span>
                                        <ArrowRight className="h-3 w-3" />
                                    </Button>
                                )}
                            </div>
                            <div className="font-medium text-xs text-foreground bg-background/80 p-2 rounded border border-border">
                                {analysis.suggested_conclusion}
                            </div>
                            {analysis.conclusion_rationale && (
                                <p className="text-[11px] text-muted-foreground leading-relaxed">
                                    {analysis.conclusion_rationale}
                                </p>
                            )}
                        </div>
                    )}

                    {/* Daftar Regulasi Terkait */}
                    {analysis.identified_regulations && analysis.identified_regulations.length > 0 && (
                        <div className="space-y-2">
                            <h5 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                                <Scale className="h-3.5 w-3.5 text-amber-500" /> Regulasi & Kebijakan Terkait ({analysis.identified_regulations.length})
                            </h5>
                            <div className="space-y-2.5">
                                {analysis.identified_regulations.map((reg, idx) => (
                                    <div 
                                        key={idx} 
                                        className="p-3 rounded-lg border border-border bg-card shadow-xs space-y-2"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="font-semibold text-xs text-foreground leading-tight">
                                                {reg.title}
                                            </span>
                                            {getRegulationBadge(reg.status)}
                                        </div>

                                        {reg.latest_regulation && reg.latest_regulation !== reg.title && (
                                            <div className="p-2 rounded bg-muted/60 border border-border/60 text-[11px] space-y-0.5">
                                                <span className="text-muted-foreground block text-[10px] font-medium uppercase">
                                                    Peraturan Pengganti / Terbaru:
                                                </span>
                                                <span className="font-semibold text-foreground">
                                                    {reg.latest_regulation}
                                                </span>
                                            </div>
                                        )}

                                        {reg.notes && (
                                            <p className="text-[11px] text-muted-foreground leading-relaxed pt-0.5">
                                                {reg.notes}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Rekomendasi Tindak Lanjut */}
                    {analysis.recommendations && analysis.recommendations.length > 0 && (
                        <div className="space-y-2">
                            <h5 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" /> Rekomendasi Pembaruan Materi
                            </h5>
                            <ul className="space-y-1.5">
                                {analysis.recommendations.map((rec, idx) => (
                                    <li 
                                        key={idx} 
                                        className="text-xs text-foreground bg-muted/30 p-2.5 rounded-md border border-border/70 flex items-start gap-2"
                                    >
                                        <span className="font-semibold text-primary text-[11px] mt-0.5">•</span>
                                        <span className="leading-relaxed">{rec}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="text-[10px] text-muted-foreground text-center pt-2 border-t border-border">
                        Dianalisis pada: {new Date(analysis.created_at).toLocaleString('id-ID')}
                    </div>
                </div>
            )}
        </div>
    );
}
