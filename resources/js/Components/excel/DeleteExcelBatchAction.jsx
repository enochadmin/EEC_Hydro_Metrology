import Button from '@/Components/Button';
import Can from '@/Components/Can';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function DeleteExcelBatchAction({ batch }) {
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirmDelete = () => {
        router.delete(`/excel/${batch.uuid}`, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setConfirming(false);
            },
        });
    };

    const description =
        batch.imported_rows > 0
            ? `This permanently deletes the workbook, its processing files and validation history, all ${batch.imported_rows} observation(s) imported by this batch, and its quality issues. Monitoring station records are kept.`
            : 'This permanently deletes the workbook, its processing files, validation history and quality issues. Monitoring station records are kept.';

    return (
        <Can permission="delete data">
            <Button
                type="button"
                variant="danger-ghost"
                size="icon"
                className="h-8 w-8"
                aria-label={`Delete ${batch.file_name}`}
                title="Delete workbook"
                onClick={() => setConfirming(true)}
            >
                <Trash2 className="h-4 w-4" strokeWidth={1.75} />
            </Button>
            <ConfirmDialog
                open={confirming}
                title="Delete this workbook?"
                description={description}
                confirmLabel="Delete workbook"
                processing={processing}
                onConfirm={confirmDelete}
                onClose={() => {
                    if (!processing) {
                        setConfirming(false);
                    }
                }}
            />
        </Can>
    );
}
