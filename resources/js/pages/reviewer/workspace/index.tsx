import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Send, CheckCircle, MessageSquare } from 'lucide-react';
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
import type { SharedData } from '@/types';

type Comment = {
    id: number;
    body: string;
    anchor_type: string;
    status: string;
    user: { name: string };
    created_at: string;
    replies?: Comment[];
};

type Review = {
    id: number;
    status: string;
    general_notes?: string | null;
    material_version_id: number;
    material_version: {
        id: number;
        version_number: number;
        preview_url?: string | null;
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

export default function Workspace({ review }: { review: Review }) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } } & SharedData>().props;
    const [replyTo, setReplyTo] = useState<number | null>(null);
    const [isSubmitModalOpen, setIsSubmitModalOpen] = useState(false);
    const [generalNotes, setGeneralNotes] = useState(review.general_notes || '');
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
    }, [flash]);

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
        setIsSubmitting(true);
        router.post(`/reviewer/workspace/${review.id}/submit`, {
            general_notes: generalNotes,
        }, {
            onSuccess: () => {
                setIsSubmitModalOpen(false);
            },
            onError: () => {
                toast.error('Gagal menyelesaikan reviu. Silakan coba lagi.');
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
                                <h1 className="text-lg font-semibold text-foreground flex items-center gap-2">
                                    {review.material_version.material.title}
                                    <Badge variant="outline">v{review.material_version.version_number}</Badge>
                                </h1>
                                <p className="text-xs text-muted-foreground">
                                    {review.material_version.material.subject.training.title}
                                </p>
                            </div>
                        </div>
                        <Button asChild variant="secondary" size="sm">
                            <a href={`/viewer/${review.material_version.id}`} target="_blank" rel="noreferrer">
                                Buka Fullscreen
                            </a>
                        </Button>
                    </div>
                    <div className="flex-1 bg-muted relative">
                        <iframe 
                            src={viewerUrl}
                            className="absolute inset-0 w-full h-full border-0"
                            allow="autoplay; fullscreen"
                        />
                    </div>
                </div>

                {/* Right: Comments */}
                <div className="w-96 flex flex-col bg-background">
                    <div className="p-4 border-b border-border bg-muted/20 flex items-center justify-between">
                        <h2 className="font-semibold flex items-center">
                            <MessageSquare className="mr-2 h-4 w-4" /> Ulasan Anda
                        </h2>
                        {review.status === 'draft' ? (
                            <Badge variant="secondary">Draft</Badge>
                        ) : (
                            <Badge className="bg-green-500">Submitted</Badge>
                        )}
                    </div>
                    
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
                            <div className="text-center text-sm p-4 bg-green-500/10 border border-green-500/20 rounded-lg text-green-700 dark:text-green-400">
                                <div className="font-semibold flex items-center justify-center gap-1.5 mb-1">
                                    <CheckCircle className="h-4 w-4" /> Ulasan Selesai
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Ulasan ini telah disubmit. Anda masih dapat membalas diskusi pada komentar di atas.
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <Dialog open={isSubmitModalOpen} onOpenChange={setIsSubmitModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-lg">
                            <CheckCircle className="h-5 w-5 text-green-600" /> Selesaikan Reviu
                        </DialogTitle>
                        <DialogDescription>
                            Apakah Anda yakin ingin menyelesaikan ulasan ini? Setelah disubmit, Anda tidak dapat lagi menambahkan komentar utama baru, namun masih bisa membalas percakapan.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2 py-2">
                        <Label htmlFor="general_notes" className="text-xs">Catatan Kesimpulan / Ringkasan (Opsional)</Label>
                        <textarea
                            id="general_notes"
                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            placeholder="Tuliskan catatan kesimpulan akhir untuk pengembang materi..."
                            value={generalNotes}
                            onChange={(e) => setGeneralNotes(e.target.value)}
                        />
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
                            className="bg-green-600 hover:bg-green-700 text-white"
                            onClick={handleConfirmSubmit}
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Memproses...' : 'Ya, Selesaikan Reviu'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
