import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Send, CheckCircle, MessageSquare, Sparkles, Clock, CalendarRange } from 'lucide-react';
import AiAnalysisPanel, { type MaterialAiAnalysis } from '@/components/workspace/ai-analysis-panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toast } from 'sonner';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';

const CONCLUSION_PRESETS = [
    {
        id: '1',
        label: '1. Materi masih relevan',
        text: 'Materi masih relevan',
    },
    {
        id: '2',
        label: '2. Materi masih relevan, tetapi perlu perbaikan minor sesuai komentar',
        text: 'Materi masih relevan, tetapi perlu perbaikan minor sesuai komentar',
    },
    {
        id: '3',
        label: '3. Materi sudah tidak relevan, perlu perubahan major',
        text: 'Materi sudah tidak relevan, perlu perubahan major',
    },
    {
        id: '4',
        label: '4. Materi sudah tidak sesuai kebutuhan, perlu penyesuaian kurikulum',
        text: 'Materi sudah tidak sesuai kebutuhan, perlu penyesuaian kurikulum',
    },
    {
        id: '5',
        label: '5. (Isian bebas)',
        text: '',
    },
];

type Comment = {
    id: number;
    body: string;
    anchor_type: string;
    status: string;
    user: { name: string };
    created_at: string;
    replies?: Comment[];
};

type PreviousReview = {
    id: number;
    status: string;
    conclusion?: string | null;
    general_notes?: string | null;
    submitted_at?: string | null;
    assignment?: {
        id: number;
        review_period_id?: number | null;
        period?: {
            id: number;
            name: string;
            year: number;
        } | null;
    } | null;
    material_version?: {
        id: number;
        version_number: number;
        changelog?: string | null;
    };
    comments: Comment[];
};

type Review = {
    id: number;
    status: string;
    conclusion?: string | null;
    general_notes?: string | null;
    assignment?: {
        id: number;
        review_period_id?: number | null;
        period?: {
            id: number;
            name: string;
            year: number;
        } | null;
    };
    material_version_id: number;
    material_version: {
        id: number;
        version_number: number;
        changelog?: string | null;
        preview_url?: string | null;
        latest_ai_analysis?: MaterialAiAnalysis | null;
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
    comments: Comment[];
};

export default function Workspace({ review, previousReviews = [] }: { review: Review; previousReviews?: PreviousReview[] }) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string; warning?: string } } & SharedData>().props;
    const [activeTab, setActiveTab] = useState<'comments' | 'ai' | 'history'>('comments');
    const [replyTo, setReplyTo] = useState<number | null>(null);
    const [isSubmitModalOpen, setIsSubmitModalOpen] = useState(false);
    const [conclusion, setConclusion] = useState<'relevant' | 'needs_revision'>('relevant');
    const [generalNotes, setGeneralNotes] = useState(review.general_notes || '');
    const initialMatchedPreset = CONCLUSION_PRESETS.find(p => p.text && p.text === review.general_notes);
    const [selectedOption, setSelectedOption] = useState<string>(
        initialMatchedPreset ? initialMatchedPreset.id : (review.general_notes ? '5' : '1')
    );
    const [isSubmitting, setIsSubmitting] = useState(false);

    const viewerUrl = review.material_version.material.type === 'scorm'
        ? `/viewer/${review.material_version.id}?embed=1`
        : (review.material_version.preview_url || `/viewer/${review.material_version.id}?embed=1`);

    const { data, setData, post, processing, reset, errors } = useForm({
        body: '',
        anchor_type: 'general',
    });
    
    const { 
        data: replyData, 
        setData: setReplyData, 
        post: postReply, 
        processing: replyProcessing, 
        reset: resetReply 
    } = useForm({
        body: '',
        parent_id: '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
        if (flash?.warning) {
            toast.warning(flash.warning);
        }
    }, [flash]);

    const handleApplyConclusion = (conclusionText: string) => {
        setGeneralNotes(conclusionText);
        const matched = CONCLUSION_PRESETS.find(p => p.text === conclusionText);
        if (matched) {
            setSelectedOption(matched.id);
        } else {
            setSelectedOption('5');
        }
    };

    const handleAddComment = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/reviewer/workspace/${review.id}/comments`, {
            onSuccess: () => reset('body')
        });
    };

    const handleReply = (e: React.FormEvent) => {
        e.preventDefault();
        if (!replyTo) return;
        
        replyData.parent_id = replyTo.toString();
        
        postReply(`/reviewer/workspace/${review.id}/comments`, {
            onSuccess: () => {
                resetReply('body', 'parent_id');
                setReplyTo(null);
            }
        });
    };

    const handleConfirmSubmit = () => {
        if (!generalNotes.trim()) {
            toast.error('Catatan kesimpulan / ringkasan wajib diisi.');
            return;
        }

        setIsSubmitting(true);
        router.post(`/reviewer/workspace/${review.id}/submit`, {
            general_notes: generalNotes.trim(),
            conclusion: conclusion,
        }, {
            onSuccess: () => {
                setIsSubmitModalOpen(false);
            },
            onError: (errs) => {
                const msg = errs.general_notes || 'Gagal menyelesaikan reviu. Silakan coba lagi.';
                toast.error(msg);
            },
            onFinish: () => {
                setIsSubmitting(false);
            }
        });
    };

    return (
        <>
            <Head title={`Workspace: ${review.material_version.material.title}`} />
            <div className="flex h-[calc(100vh-4rem)] overflow-hidden">
                {/* Left: Viewer */}
                <div className="flex-1 flex flex-col border-r border-border">
                    <div className="flex items-center justify-between p-4 border-b border-border bg-background">
                        <div className="flex items-center gap-4">
                            <Button variant="outline" size="icon" asChild>
                                <Link href="/reviewer/reviews">
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <div>
                                <h1 className="text-lg font-semibold text-foreground flex flex-wrap items-center gap-2">
                                    <span>{review.material_version?.material?.title ?? 'Materi'}</span>
                                    {review.material_version?.version_number && (
                                        <Badge variant="outline">v{review.material_version.version_number}</Badge>
                                    )}
                                    {review.assignment?.period && (
                                        <Badge variant="outline" className="border-purple-300 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-950/40 dark:text-purple-400 gap-1 text-[11px] py-0.5">
                                            <CalendarRange className="h-3 w-3" />
                                            <span>{review.assignment.period.name} ({review.assignment.period.year})</span>
                                        </Badge>
                                    )}
                                </h1>
                                <p className="text-xs text-muted-foreground">
                                    {review.material_version?.material?.subject?.training?.title ?? review.material_version?.material?.subject?.title ?? '-'}
                                </p>
                            </div>
                        </div>
                        <Button asChild variant="secondary" size="sm">
                            <a href={`/viewer/${review.material_version.id}`} target="_blank" rel="noreferrer">
                                Buka Fullscreen
                            </a>
                        </Button>
                    </div>

                    {review.material_version?.changelog && (
                        <div className="bg-purple-50/90 dark:bg-purple-950/40 border-b border-purple-200/60 dark:border-purple-800/40 px-4 py-2.5 text-xs flex items-start gap-2.5">
                            <Sparkles className="h-4 w-4 text-purple-600 dark:text-purple-400 shrink-0 mt-0.5" />
                            <div className="flex-1">
                                <span className="font-semibold text-purple-900 dark:text-purple-200">
                                    Catatan Pemutakhiran (v{review.material_version.version_number}):
                                </span>
                                <span className="text-purple-800 dark:text-purple-300 ml-1.5 whitespace-pre-wrap">
                                    {review.material_version.changelog}
                                </span>
                            </div>
                        </div>
                    )}

                    <div className="flex-1 bg-muted relative">
                        <iframe 
                            src={viewerUrl}
                            className="absolute inset-0 w-full h-full border-0"
                            allow="autoplay; fullscreen"
                        />
                    </div>
                </div>

                {/* Right: Comments, History & AI Tabs */}
                <div className="w-[430px] max-w-full flex flex-col bg-background shrink-0">
                    <div className="p-2.5 border-b border-border bg-muted/20 flex items-center justify-between gap-2 overflow-x-auto">
                        <div className="flex items-center gap-1 bg-muted p-1 rounded-lg shrink-0">
                            <button
                                type="button"
                                onClick={() => setActiveTab('comments')}
                                className={cn(
                                    "px-2.5 py-1 rounded-md text-xs font-medium transition-all flex items-center gap-1.5",
                                    activeTab === 'comments' 
                                        ? "bg-background text-foreground shadow-xs" 
                                        : "text-muted-foreground hover:text-foreground"
                                )}
                            >
                                <MessageSquare className="h-3.5 w-3.5" />
                                <span>Komentar v{review.material_version?.version_number} ({review.comments.length})</span>
                            </button>
                            {previousReviews.length > 0 && (
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('history')}
                                    className={cn(
                                        "px-2.5 py-1 rounded-md text-xs font-medium transition-all flex items-center gap-1.5",
                                        activeTab === 'history' 
                                            ? "bg-background text-foreground shadow-xs" 
                                            : "text-muted-foreground hover:text-foreground"
                                    )}
                                >
                                    <Clock className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                                    <span>Riwayat Lalu ({previousReviews.reduce((acc, r) => acc + (r.comments?.length || 0), 0)})</span>
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={() => setActiveTab('ai')}
                                className={cn(
                                    "px-2.5 py-1 rounded-md text-xs font-medium transition-all flex items-center gap-1.5",
                                    activeTab === 'ai' 
                                        ? "bg-background text-foreground shadow-xs" 
                                        : "text-muted-foreground hover:text-foreground"
                                )}
                            >
                                <Sparkles className="h-3.5 w-3.5 text-primary" />
                                <span>AI</span>
                                {review.material_version?.latest_ai_analysis && (
                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                )}
                            </button>
                        </div>

                        <div>
                            {review.status === 'draft' ? (
                                <Badge variant="secondary">Draft</Badge>
                            ) : (
                                <Badge className="bg-green-500">Submitted</Badge>
                            )}
                        </div>
                    </div>

                    {activeTab === 'history' ? (
                        <div className="flex-1 overflow-y-auto p-4 space-y-4 bg-muted/10">
                            <div className="bg-amber-500/10 border border-amber-500/20 rounded-lg p-3 text-xs text-amber-900 dark:text-amber-200">
                                <div className="font-semibold flex items-center gap-1.5">
                                    <Clock className="h-3.5 w-3.5 text-amber-600" />
                                    <span>Riwayat Reviu Siklus Sebelumnya</span>
                                </div>
                                <p className="mt-1 text-muted-foreground text-[11px]">
                                    Gunakan catatan &amp; kesimpulan versi sebelumnya ini untuk memverifikasi apakah poin perbaikan telah diakomodasi oleh pengembang pada materi saat ini.
                                </p>
                            </div>

                            {previousReviews.map((prevRev) => (
                                <div key={prevRev.id} className="border border-border rounded-lg bg-background p-3.5 space-y-3 shadow-xs">
                                    <div className="flex items-center justify-between border-b border-border pb-2">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <Badge variant="outline" className="font-semibold text-xs">
                                                Versi v{prevRev.material_version?.version_number}
                                            </Badge>
                                            {prevRev.assignment?.period && (
                                                <Badge variant="secondary" className="text-[10px] bg-primary/10 text-primary border-primary/20">
                                                    {prevRev.assignment.period.name}
                                                </Badge>
                                            )}
                                            {prevRev.submitted_at && (
                                                <span className="text-[11px] text-muted-foreground">
                                                    {new Date(prevRev.submitted_at).toLocaleDateString('id-ID', {
                                                        day: 'numeric',
                                                        month: 'short',
                                                        year: 'numeric'
                                                    })}
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            {prevRev.conclusion && (
                                                <Badge variant="outline" className={cn(
                                                    "text-[10px]",
                                                    prevRev.conclusion === 'relevant'
                                                        ? "border-emerald-500 text-emerald-600 bg-emerald-50 dark:bg-emerald-950/30"
                                                        : "border-amber-500 text-amber-600 bg-amber-50 dark:bg-amber-950/30"
                                                )}>
                                                    {prevRev.conclusion === 'relevant' ? 'Masih Relevan' : 'Perlu Revisi'}
                                                </Badge>
                                            )}
                                            <Badge className="bg-emerald-600 text-[10px]">Selesai Direviu</Badge>
                                        </div>
                                    </div>

                                    {prevRev.material_version?.changelog && (
                                        <div className="text-[11px] text-muted-foreground bg-muted/30 p-2 rounded border border-border">
                                            <span className="font-medium text-foreground">Catatan Versi:</span> {prevRev.material_version.changelog}
                                        </div>
                                    )}

                                    {prevRev.general_notes && (
                                        <div className="bg-muted/50 p-2.5 rounded border border-border text-xs">
                                            <span className="font-semibold text-foreground block mb-0.5">Kesimpulan Reviu Lalu:</span>
                                            <p className="text-foreground/90 whitespace-pre-wrap">{prevRev.general_notes}</p>
                                        </div>
                                    )}

                                    <div className="space-y-2 pt-1">
                                        <span className="text-xs font-semibold text-foreground block">
                                            Catatan Masukan ({prevRev.comments?.length || 0}):
                                        </span>
                                        {(!prevRev.comments || prevRev.comments.length === 0) ? (
                                            <p className="text-xs text-muted-foreground italic">Tidak ada catatan spesifik pada versi ini.</p>
                                        ) : (
                                            prevRev.comments.map((c) => (
                                                <div key={c.id} className="bg-muted/20 border border-border/80 p-2.5 rounded text-xs space-y-1.5">
                                                    <div className="flex items-center justify-between">
                                                        <span className="font-medium text-primary text-[11px]">{c.user?.name}</span>
                                                        <div className="flex items-center gap-1.5">
                                                            <Badge variant="outline" className="text-[9px] py-0 px-1">{c.anchor_type}</Badge>
                                                            <span className={cn(
                                                                "text-[10px] font-semibold px-1.5 py-0.5 rounded",
                                                                c.status === 'open' 
                                                                    ? "bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-300"
                                                                    : "bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-300"
                                                            )}>
                                                                {c.status === 'open' ? 'BELUM DITINDAKLANJUTI' : 'SUDAH DITINDAKLANJUTI'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <p className="text-foreground whitespace-pre-wrap">{c.body}</p>

                                                    {/* Replies from developer */}
                                                    {c.replies && c.replies.length > 0 && (
                                                        <div className="ml-3 pl-2.5 border-l-2 border-primary/20 space-y-1 pt-1">
                                                            {c.replies.map((r: any) => (
                                                                <div key={r.id} className="bg-background/80 p-1.5 rounded text-[11px]">
                                                                    <div className="flex items-center justify-between text-muted-foreground text-[10px]">
                                                                        <span className="font-medium text-foreground">{r.user?.name || 'Pengembang'}</span>
                                                                        <span>{new Date(r.created_at).toLocaleString('id-ID')}</span>
                                                                    </div>
                                                                    <p className="text-foreground mt-0.5">{r.body}</p>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : activeTab === 'ai' ? (
                        <div className="flex-1 overflow-y-auto p-4 bg-muted/10">
                            <AiAnalysisPanel
                                reviewId={review.id}
                                analysis={review.material_version?.latest_ai_analysis}
                                role="reviewer"
                                onApplyConclusion={handleApplyConclusion}
                            />
                        </div>
                    ) : (
                        <>
                            <div className="flex-1 overflow-y-auto p-4 space-y-6 bg-muted/10">
                        {review.comments.length === 0 ? (
                            <div className="text-center text-muted-foreground py-8 text-sm">
                                Belum ada komentar.
                            </div>
                        ) : (
                            review.comments.map(comment => (
                                <div key={comment.id} className="space-y-3">
                                    <div className="bg-background border border-border p-3 rounded-lg shadow-sm">
                                        <div className="flex items-center justify-between mb-2">
                                            <div className="font-medium text-sm text-primary">{comment.user.name}</div>
                                            <Badge variant="outline" className="text-[10px]">{comment.anchor_type}</Badge>
                                        </div>
                                        <p className="text-sm text-foreground mb-3 whitespace-pre-wrap">{comment.body}</p>
                                        <div className="text-[10px] text-muted-foreground flex justify-between items-center border-t border-border pt-2 mt-2">
                                            <div className="flex items-center gap-3">
                                                <span>{new Date(comment.created_at).toLocaleString('id-ID')}</span>
                                                <button 
                                                    className="text-primary hover:underline flex items-center"
                                                    onClick={() => {
                                                        setReplyTo(comment.id);
                                                        setReplyData('parent_id', comment.id.toString());
                                                    }}
                                                >
                                                    <MessageSquare className="mr-1 h-3 w-3" /> Balas
                                                </button>
                                            </div>
                                            <span className={comment.status === 'open' ? 'text-orange-500' : 'text-green-500 font-medium'}>
                                                {comment.status === 'open' ? 'OPEN' : 'ADDRESSED'}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    {/* Replies */}
                                    {comment.replies && comment.replies.length > 0 && (
                                        <div className="ml-6 space-y-2 border-l-2 border-primary/20 pl-4">
                                            {comment.replies.map((reply: any) => (
                                                <div key={reply.id} className="bg-muted/50 p-2 rounded-md text-sm">
                                                    <div className="flex items-center justify-between mb-1">
                                                        <span className="font-medium text-xs">{reply.user?.name || 'Pengguna'}</span>
                                                        <span className="text-[10px] text-muted-foreground">{new Date(reply.created_at).toLocaleString('id-ID')}</span>
                                                    </div>
                                                    <p className="text-xs">{reply.body}</p>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {/* Reply Form */}
                                    {replyTo === comment.id && (
                                        <div className="ml-6 pl-4 border-l-2 border-primary/20">
                                            <form onSubmit={handleReply} className="flex flex-col gap-2">
                                                <textarea
                                                    className="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                                    placeholder="Tulis balasan..."
                                                    value={replyData.body}
                                                    onChange={e => setReplyData('body', e.target.value)}
                                                    required
                                                />
                                                <div className="flex gap-2 justify-end">
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => { setReplyTo(null); resetReply('body', 'parent_id'); }} className="h-7 text-xs">
                                                        Batal
                                                    </Button>
                                                    <Button type="submit" size="sm" className="h-7 text-xs" disabled={replyProcessing || !replyData.body}>
                                                        Kirim Balasan
                                                    </Button>
                                                </div>
                                            </form>
                                        </div>
                                    )}
                                </div>
                            ))
                        )}
                    </div>

                    <div className="p-4 border-t border-border bg-background shrink-0 space-y-3">
                        {review.status === 'draft' ? (
                            <>
                                <form onSubmit={handleAddComment} className="space-y-3">
                                    <div>
                                        <Label className="text-xs">Tambah Komentar</Label>
                                        <textarea
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 mt-1"
                                            placeholder="Ketik masukkan Anda di sini..."
                                            value={data.body}
                                            onChange={e => setData('body', e.target.value)}
                                            required
                                        />
                                        {errors.body && <p className="text-xs text-destructive mt-1">{errors.body}</p>}
                                    </div>
                                    <Button type="submit" className="w-full" disabled={processing || !data.body}>
                                        <Send className="mr-2 h-4 w-4" /> Kirim Komentar
                                    </Button>
                                </form>

                                <div className="pt-2 border-t border-border">
                                    <Button 
                                        type="button" 
                                        variant="outline" 
                                        className="w-full border-green-600/40 text-green-700 hover:bg-green-50 hover:text-green-800 dark:text-green-400 dark:hover:bg-green-950/40"
                                        onClick={() => setIsSubmitModalOpen(true)}
                                        disabled={isSubmitting}
                                    >
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-600 dark:text-green-400" /> Selesaikan Reviu
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <div className="p-4 bg-green-500/10 border border-green-500/20 rounded-lg text-green-700 dark:text-green-400 space-y-2">
                                <div className="font-semibold flex items-center gap-1.5 text-sm">
                                    <CheckCircle className="h-4 w-4" /> Ulasan Selesai
                                </div>
                                {review.general_notes && (
                                    <div className="pt-2 border-t border-green-500/20 text-left">
                                        <p className="text-[11px] font-semibold text-foreground mb-0.5">Catatan Kesimpulan / Ringkasan:</p>
                                        <p className="text-xs text-foreground/80 whitespace-pre-wrap bg-background/60 p-2.5 rounded border border-green-500/20">
                                            {review.general_notes}
                                        </p>
                                    </div>
                                )}
                                <p className="text-xs text-muted-foreground text-left pt-1">
                                    Ulasan ini telah disubmit. Anda masih dapat membalas diskusi pada komentar di atas.
                                </p>
                            </div>
                        )}
                    </div>
                </>
            )}
        </div>
    </div>

    <Dialog open={isSubmitModalOpen} onOpenChange={setIsSubmitModalOpen}>
        <DialogContent className="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle className="flex items-center gap-2 text-lg">
                    <CheckCircle className="h-5 w-5 text-green-600" /> Selesaikan Reviu
                </DialogTitle>
                <DialogDescription>
                    Apakah Anda yakin ingin menyelesaikan ulasan ini? Setelah disubmit, Anda tidak dapat lagi menambahkan komentar utama baru, namun masih bisa membalas percakapan.
                </DialogDescription>
            </DialogHeader>

            <div className="space-y-3.5 py-2">
                {review.material_version?.latest_ai_analysis?.suggested_conclusion && (
                    <div className="p-2.5 rounded-lg bg-primary/5 border border-primary/20 flex items-center justify-between gap-2">
                        <div className="flex items-center gap-1.5 min-w-0">
                            <Sparkles className="h-4 w-4 text-primary shrink-0" />
                            <div className="text-[11px] truncate">
                                <span className="font-semibold text-primary">Saran AI: </span>
                                <span className="text-foreground">{review.material_version.latest_ai_analysis.suggested_conclusion}</span>
                            </div>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => handleApplyConclusion(review.material_version.latest_ai_analysis!.suggested_conclusion!)}
                            className="h-6 text-[10px] border-primary/30 text-primary hover:bg-primary/10 shrink-0"
                        >
                            Terapkan
                        </Button>
                    </div>
                )}

                {/* Keputusan Kesimpulan Evaluasi Kebijakan */}
                <div className="space-y-1.5 pb-2 border-b border-border">
                    <Label className="text-xs font-semibold text-foreground">
                        Keputusan Reviu Regulasi
                    </Label>
                    <div className="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                setConclusion('relevant');
                                setSelectedOption('1');
                                setGeneralNotes(CONCLUSION_PRESETS[0].text);
                            }}
                            className={cn(
                                "p-2.5 rounded-lg border text-left transition-all",
                                conclusion === 'relevant'
                                    ? "border-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 ring-1 ring-emerald-600 font-medium"
                                    : "border-border hover:bg-muted/50 text-muted-foreground"
                            )}
                        >
                            <div className="font-semibold text-xs flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400">
                                <CheckCircle className="h-3.5 w-3.5" />
                                <span>Masih Relevan</span>
                            </div>
                            <p className="text-[10px] mt-0.5 opacity-80 leading-normal">
                                Sesuai regulasi terkini. Reviu selesai tanpa perlu versi baru.
                            </p>
                        </button>

                        <button
                            type="button"
                            onClick={() => {
                                setConclusion('needs_revision');
                                setSelectedOption('2');
                                setGeneralNotes(CONCLUSION_PRESETS[1].text);
                            }}
                            className={cn(
                                "p-2.5 rounded-lg border text-left transition-all",
                                conclusion === 'needs_revision'
                                    ? "border-amber-600 bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 ring-1 ring-amber-600 font-medium"
                                    : "border-border hover:bg-muted/50 text-muted-foreground"
                            )}
                        >
                            <div className="font-semibold text-xs flex items-center gap-1.5 text-amber-700 dark:text-amber-400">
                                <Clock className="h-3.5 w-3.5" />
                                <span>Perlu Revisi</span>
                            </div>
                            <p className="text-[10px] mt-0.5 opacity-80 leading-normal">
                                Ada regulasi yang berubah. Pengembang diminta unggah v2.
                            </p>
                        </button>
                    </div>
                </div>

                <div className="space-y-1">
                    <Label className="text-xs font-semibold text-foreground flex items-center justify-between">
                        <span>Pilih Ringkasan Catatan <span className="text-destructive">*</span></span>
                        <span className="text-[11px] text-destructive font-medium">Wajib diisi</span>
                    </Label>
                    <p className="text-[11px] text-muted-foreground">
                        Pilih template ringkasan catatan di bawah atau tuliskan catatan sendiri:
                    </p>
                </div>

                        {/* Pilihan Jawaban Kesimpulan */}
                        <div className="space-y-2">
                            {CONCLUSION_PRESETS.map((preset) => {
                                const isSelected = selectedOption === preset.id;
                                return (
                                    <label
                                        key={preset.id}
                                        onClick={() => {
                                            setSelectedOption(preset.id);
                                            if (preset.id === '1') {
                                                setConclusion('relevant');
                                            } else if (['2', '3', '4'].includes(preset.id)) {
                                                setConclusion('needs_revision');
                                            }
                                            if (preset.id !== '5') {
                                                setGeneralNotes(preset.text);
                                            } else if (CONCLUSION_PRESETS.slice(0, 4).some(p => p.text === generalNotes)) {
                                                setGeneralNotes('');
                                            }
                                        }}
                                        className={cn(
                                            "flex items-start gap-2.5 p-2.5 rounded-lg border text-xs cursor-pointer transition-all",
                                            isSelected
                                                ? "border-emerald-600 bg-emerald-50/70 dark:bg-emerald-950/40 text-foreground font-medium shadow-xs"
                                                : "border-border hover:bg-muted/50 text-muted-foreground hover:text-foreground"
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="conclusion_option"
                                            value={preset.id}
                                            checked={isSelected}
                                            onChange={() => {}}
                                            className="mt-0.5 h-3.5 w-3.5 text-emerald-600 border-muted-foreground focus:ring-emerald-600"
                                        />
                                        <span className="leading-snug">{preset.label}</span>
                                    </label>
                                );
                            })}
                        </div>

                        {/* Textarea Uraian Catatan */}
                        <div className="pt-1 space-y-1.5">
                            <Label htmlFor="general_notes" className="text-[11px] font-medium text-foreground">
                                {selectedOption === '5' ? (
                                    <>Tuliskan Catatan Kesimpulan Akhir <span className="text-destructive">*</span></>
                                ) : (
                                    'Uraian / Catatan Tambahan (Opsional):'
                                )}
                            </Label>
                            <textarea
                                id="general_notes"
                                rows={3}
                                className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                placeholder={selectedOption === '5' 
                                    ? 'Tuliskan catatan kesimpulan akhir untuk pengembang materi...' 
                                    : 'Pilih salah satu kesimpulan di atas atau tambahkan catatan rinci di sini...'}
                                value={generalNotes}
                                onChange={(e) => {
                                    setGeneralNotes(e.target.value);
                                    if (!CONCLUSION_PRESETS.slice(0, 4).some(p => p.text === e.target.value)) {
                                        setSelectedOption('5');
                                    }
                                }}
                            />
                            {!generalNotes.trim() && (
                                <p className="text-[11px] text-destructive font-medium">
                                    * Catatan kesimpulan / ringkasan wajib dipilih atau diisi sebelum menekan tombol selesaikan reviu.
                                </p>
                            )}
                        </div>
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setIsSubmitModalOpen(false)}
                            disabled={isSubmitting}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            className="bg-green-600 hover:bg-green-700 text-white disabled:opacity-50"
                            onClick={handleConfirmSubmit}
                            disabled={isSubmitting || !generalNotes.trim()}
                        >
                            {isSubmitting ? 'Memproses...' : 'Ya, Selesaikan Reviu'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
