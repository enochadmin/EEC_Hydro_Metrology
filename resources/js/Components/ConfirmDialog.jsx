import Button from '@/Components/Button';
import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import { AlertTriangle } from 'lucide-react';

/**
 * Blocking confirmation for destructive registry actions.
 *
 * The dialog only collects the decision; the caller performs the request and
 * reports server-side refusals (for example a basin that still has children)
 * through the page error banner.
 */
export default function ConfirmDialog({
    open,
    title = 'Confirm deletion',
    description,
    confirmLabel = 'Delete',
    processing = false,
    onConfirm,
    onClose,
}) {
    return (
        <Transition show={open} leave="duration-150">
            <Dialog as="div" className="relative z-50" onClose={onClose}>
                <TransitionChild
                    enter="ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-ink/40" />
                </TransitionChild>

                <div className="fixed inset-0 flex items-end justify-center px-4 py-6 sm:items-center">
                    <TransitionChild
                        enter="ease-out duration-200"
                        enterFrom="opacity-0 translate-y-3 sm:scale-95"
                        enterTo="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-150"
                        leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                        leaveTo="opacity-0 translate-y-3 sm:scale-95"
                    >
                        <DialogPanel className="w-full max-w-md rounded-xl border border-line bg-surface p-5 shadow-lift">
                            <div className="flex items-start gap-3">
                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300">
                                    <AlertTriangle className="h-[18px] w-[18px]" strokeWidth={1.75} />
                                </span>
                                <div className="min-w-0">
                                    <DialogTitle className="text-sm font-semibold tracking-tight text-ink">{title}</DialogTitle>
                                    {description && <p className="mt-1.5 text-[13px] leading-relaxed text-muted">{description}</p>}
                                </div>
                            </div>

                            <div className="mt-5 flex justify-end gap-2">
                                <Button variant="secondary" size="sm" onClick={onClose} disabled={processing}>
                                    Cancel
                                </Button>
                                <Button variant="danger" size="sm" onClick={onConfirm} disabled={processing}>
                                    {processing ? 'Working…' : confirmLabel}
                                </Button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}
