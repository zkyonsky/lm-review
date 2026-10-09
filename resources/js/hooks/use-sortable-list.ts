import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';

export function useSortableList<T extends { id: number; order?: number; sort_order?: number }>(
    initialItems: T[],
    reorderUrl: string
) {
    const [items, setItems] = useState<T[]>(initialItems);
    const [draggedIndex, setDraggedIndex] = useState<number | null>(null);
    const [dragOverIndex, setDragOverIndex] = useState<number | null>(null);
    const [isReordering, setIsReordering] = useState(false);

    useEffect(() => {
        setItems(initialItems);
    }, [initialItems]);

    const handleDragStart = (e: React.DragEvent, index: number) => {
        setDraggedIndex(index);
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', `${index}`);
    };

    const handleDragOver = (e: React.DragEvent, index: number) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (dragOverIndex !== index) {
            setDragOverIndex(index);
        }
    };

    const handleDrop = (e: React.DragEvent, targetIndex: number) => {
        e.preventDefault();
        if (draggedIndex === null || draggedIndex === targetIndex) {
            setDraggedIndex(null);
            setDragOverIndex(null);
            return;
        }

        const previousItems = [...items];
        const newItems = [...items];
        const [movedItem] = newItems.splice(draggedIndex, 1);
        newItems.splice(targetIndex, 0, movedItem);

        const reordered = newItems.map((item, idx) => ({
            ...item,
            order: idx + 1,
            sort_order: idx + 1,
        }));

        setItems(reordered);
        setDraggedIndex(null);
        setDragOverIndex(null);
        setIsReordering(true);

        router.post(
            reorderUrl,
            {
                orders: reordered.map((item, idx) => ({
                    id: item.id,
                    order: idx + 1,
                })),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setIsReordering(false);
                    toast.success('Urutan berhasil diperbarui.');
                },
                onError: () => {
                    setIsReordering(false);
                    setItems(previousItems);
                    toast.error('Gagal memperbarui urutan.');
                },
            }
        );
    };

    const handleDragEnd = () => {
        setDraggedIndex(null);
        setDragOverIndex(null);
    };

    return {
        items,
        draggedIndex,
        dragOverIndex,
        isReordering,
        handleDragStart,
        handleDragOver,
        handleDrop,
        handleDragEnd,
    };
}
