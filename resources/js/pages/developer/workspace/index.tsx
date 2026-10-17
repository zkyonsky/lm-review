import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Send, CheckCircle, MessageSquare, Reply, X, Sparkles, Clock } from 'lucide-react';
import AiAnalysisPanel, { type MaterialAiAnalysis } from '@/components/workspace/ai-analysis-panel';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';

type User = { name: string; id: number };

type Comment = {
    id: number;
    body: string;
    anchor_type: string;
    status: string;
    user: User;
    created_at: string;
    replies: Comment[];
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
    general_notes?: string | null;
    user: User;
    material_version_id: number;
    material_version: {
        id: number;
        version_number: number;
        changelog?: string | null;
        preview_url?: string | null;
        latest_ai_analysis?: MaterialAiAnalysis | null;
        material: {
            id: number;
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
    
    const viewerUrl = review.material_version.material.type === 'scorm'
        ? `/viewer/${review.material_version.id}?embed=1`
        : (review.material_version.preview_url || `/viewer/${review.material_version.id}?embed=1`);
    
    const { data, setData, post, processing, reset, errors } = useForm({
        body: '',
    });
    
    const statusForm = useForm();

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

    const handleReply = (e: React.FormEvent) => {
        e.preventDefault();
        if (!replyTo) return;
        
        post(`/developer/workspace/${review.id}/comments/${replyTo}/reply`, {
            onSuccess: () => {
                reset('body');
                setReplyTo(null);
            }
        });
    };

    const handleToggleStatus = (commentId: number) => {
        statusForm.patch(`/developer/workspace/${review.id}/comments/${commentId}/toggle`);
    };

    return (
        <>
            <Head title={`Catatan Reviu: ${review.material_version.material.title}`} />
            <div className="flex h-[calc(100vh-4rem)] overflow-hidden">
                {/* Left: Viewer */}
                <div className="flex-1 flex flex-col border-r border-border">
                    <div className="flex items-center justify-between p-4 border-b border-border bg-background">
                        <div className="flex items-center gap-4">
                            <Button variant="outline" size="icon" asChild>
                                <Link href={`/developer/materials/${review.material_version.material.id}`}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <div>
                                <h1 className="text-lg font-semibold text-foreground flex items-center gap-2">
                                    {review.material_version.material.title}
                                    <Badge variant="outline">v{review.material_version.version_number}</Badge>
                                </h1>
                                <p className="text-xs text-muted-foreground flex gap-2 items-center">
                                    Reviewer: <span className="font-medium text-foreground">{review.user.name}</span>
                                </p>
                            </div>
                        </div>
                        <Button asChild variant="secondary" size="sm">
                            <a href={`/viewer/${review.material_version.id}`} target="_blank" rel="noreferrer">
                                Buka Layar Penuh
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
                                <span>Catatan v{review.material_version?.version_number} ({review.comments.length})</span>
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
                                <Badge variant="secondary">Sedang Direviu</Badge>
                            ) : (
                                <Badge className="bg-green-600">Reviu Selesai</Badge>
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
                                    Daftar catatan dan kesimpulan reviu dari siklus versi sebelumnya sebagai panduan kontinuitas perbaikan.
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
                                            <span className="font-semibold text-foreground block mb-0.5">Kesimpulan Reviewer:</span>
                                            <p className="text-foreground/90 whitespace-pre-wrap">{prevRev.general_notes}</p>
                                        </div>
                                    )}

                                    <div className="space-y-2 pt-1">
                                        <span className="text-xs font-semibold text-foreground block">
                                            Catatan Komentar ({prevRev.comments?.length || 0}):
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
                                role="developer"
                            />
                        </div>
                    ) : (
                        <div className="flex-1 overflow-y-auto p-4 space-y-6 bg-muted/10">
                        {review.general_notes && (
                            <div className="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-800 dark:text-emerald-300">
                                <div className="font-semibold flex items-center gap-1.5 text-xs mb-1">
                                    <CheckCircle className="h-4 w-4 text-emerald-600" /> Kesimpulan Reviu:
                                </div>
                                <p className="text-xs text-foreground/80 whitespace-pre-wrap bg-background/60 p-2.5 rounded border border-emerald-500/20">
                                    {review.general_notes}
                                </p>
                            </div>
                        )}
                        {review.comments.length === 0 ? (
                            <div className="text-center text-muted-foreground py-8 text-sm">
                                Belum ada komentar dari reviewer.
                            </div>
                        ) : (
                            review.comments.map(comment => (
                                <div key={comment.id} className="space-y-3">
                                    {/* Parent Comment */}
                                    <div className="bg-background border border-border p-3 rounded-lg shadow-sm">
                                        <div className="flex items-center justify-between mb-2">
                                            <div className="font-medium text-sm text-primary">{comment.user.name}</div>
                                            <Badge variant="outline" className="text-[10px]">{comment.anchor_type}</Badge>
                                        </div>
                                        <p className="text-sm text-foreground mb-3 whitespace-pre-wrap">{comment.body}</p>
                                        <div className="flex items-center justify-between mt-2 pt-2 border-t border-border">
                                            <div className="flex gap-2">
                                                <Button 
                                                    variant="ghost" 
                                                    size="sm" 
                                                    className="h-6 px-2 text-[10px]"
                                                    onClick={() => setReplyTo(comment.id)}
                                                >
                                                    <Reply className="mr-1 h-3 w-3" /> Balas
                                                </Button>
                                                <Button
                                                    variant={comment.status === 'open' ? 'secondary' : 'default'}
                                                    size="sm"
                                                    className={`h-6 px-2 text-[10px] ${comment.status === 'open' ? '' : 'bg-green-600 hover:bg-green-700'}`}
                                                    onClick={() => handleToggleStatus(comment.id)}
                                                    disabled={statusForm.processing}
                                                >
                                                    <CheckCircle className="mr-1 h-3 w-3" />
                                                    {comment.status === 'open' ? 'Tandai Selesai' : 'Selesai'}
                                                </Button>
                                            </div>
                                            <span className="text-[10px] text-muted-foreground">
                                                {new Date(comment.created_at).toLocaleString('id-ID')}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    {/* Replies */}
                                    {comment.replies && comment.replies.length > 0 && (
                                        <div className="ml-6 space-y-2 border-l-2 border-primary/20 pl-4">
                                            {comment.replies.map(reply => (
                                                <div key={reply.id} className="bg-muted/50 p-2 rounded-md text-sm">
                                                    <div className="flex items-center justify-between mb-1">
                                                        <span className="font-medium text-xs">{reply.user.name}</span>
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
                                                    placeholder="Tulis balasan atau tindak lanjut..."
                                                    value={data.body}
                                                    onChange={e => setData('body', e.target.value)}
                                                    required
                                                />
                                                <div className="flex gap-2 justify-end">
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => setReplyTo(null)} className="h-7 text-xs">
                                                        Batal
                                                    </Button>
                                                    <Button type="submit" size="sm" className="h-7 text-xs" disabled={processing || !data.body}>
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
                )}
            </div>
        </div>
    </>
);
}
