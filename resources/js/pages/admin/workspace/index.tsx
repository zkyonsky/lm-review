import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Send, CheckCircle, MessageSquare, Reply, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import { useEffect, useState } from 'react';
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

type Review = {
    id: number;
    status: string;
    user: User;
    material_version_id: number;
    material_version: {
        id: number;
        version_number: number;
        preview_url?: string | null;
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

type ReviewSummary = {
    id: number;
    status: string;
    user: User;
};

type Props = {
    review: Review;
    allReviews?: ReviewSummary[];
};

export default function Workspace({ review, allReviews }: Props) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } } & SharedData>().props;
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
    }, [flash]);

    const handleReply = (e: React.FormEvent) => {
        e.preventDefault();
        if (!replyTo) return;
        
        post(`/admin/workspace/${review.id}/comments/${replyTo}/reply`, {
            onSuccess: () => {
                reset('body');
                setReplyTo(null);
            }
        });
    };

    const handleToggleStatus = (commentId: number) => {
        statusForm.patch(`/admin/workspace/${review.id}/comments/${commentId}/toggle`);
    };

    return (
        <>
            <Head title={`Admin Workspace: ${review.material_version.material.title}`} />
            <div className="flex h-[calc(100vh-4rem)] overflow-hidden">
                {/* Left: Viewer */}
                <div className="flex-1 flex flex-col border-r border-border">
                    <div className="flex items-center justify-between p-4 border-b border-border bg-background">
                        <div className="flex items-center gap-4">
                            <Button variant="outline" size="icon" asChild>
                                <Link href={`/admin/materials/${review.material_version.material.id}`}>
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
                    <div className="p-4 border-b border-border bg-muted/20">
                        <div className="flex items-center justify-between">
                            <h2 className="font-semibold flex items-center text-sm">
                                <MessageSquare className="mr-2 h-4 w-4" /> Ulasan {review.user.name}
                            </h2>
                            {review.status === 'draft' ? (
                                <Badge variant="secondary">Draft</Badge>
                            ) : (
                                <Badge className="bg-green-500">Submitted</Badge>
                            )}
                        </div>
                        {allReviews && allReviews.length > 1 && (
                            <div className="mt-3 pt-3 border-t border-border flex items-center gap-2">
                                <span className="text-xs text-muted-foreground whitespace-nowrap">Reviewer:</span>
                                <select
                                    className="flex h-8 w-full rounded-md border border-input bg-background px-2 py-1 text-xs shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    value={review.id}
                                    onChange={(e) => router.visit(`/admin/workspace/${e.target.value}`)}
                                >
                                    {allReviews.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.user.name} ({r.status === 'submitted' ? 'Selesai' : 'Draft'})
                                        </option>
                                    ))}
                                </select>
                            </div>
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
                                                    className={`h-6 px-2 text-[10px] ${comment.status === 'open' ? '' : 'bg-green-500 hover:bg-green-600'}`}
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
                                                    placeholder="Tulis balasan..."
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
                </div>
            </div>
        </>
    );
}
