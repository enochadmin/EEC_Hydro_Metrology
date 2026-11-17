import UseAnimations from 'react-useanimations';
import loadingAnimation from './loading-v4.json';

export default function ExcelUploadOverlay({ fileName, progress, title, message }) {
    const uploadPercentage = progress?.percentage;
    const isPreparing = typeof uploadPercentage === 'number' && uploadPercentage >= 100;
    const status =
        message ??
        (isPreparing
            ? 'Upload complete. Preparing your workbook for review…'
            : typeof uploadPercentage === 'number'
              ? `Uploading workbook… ${uploadPercentage}%`
              : 'Uploading and inspecting your workbook…');
    const heading = title ?? (isPreparing ? 'Inspecting workbook' : 'Uploading workbook');

    return (
        <div
            className="fixed inset-0 z-[100] grid place-items-center bg-slate-950/35 px-4 py-8 backdrop-blur-sm"
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <div className="w-full max-w-sm rounded-2xl border border-line bg-surface p-7 text-center shadow-2xl shadow-slate-950/20">
                <div className="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                    <UseAnimations animation={{ animationData: loadingAnimation, interaction: 'loop' }} size={44} aria-label="Loading workbook" />
                </div>
                <p className="mt-5 text-base font-semibold text-ink">{heading}</p>
                <p className="mt-1 break-words text-sm text-muted">{status}</p>
                {fileName && <p className="mt-3 truncate text-xs text-muted">{fileName}</p>}

                <div className="mt-5 h-1.5 overflow-hidden rounded-full bg-canvas" aria-hidden="true">
                    {typeof uploadPercentage === 'number' && uploadPercentage < 100 ? (
                        <div className="h-full rounded-full bg-brand-600 transition-[width] duration-300" style={{ width: `${uploadPercentage}%` }} />
                    ) : (
                        <div className="h-full w-1/3 animate-pulse rounded-full bg-brand-600" />
                    )}
                </div>
                {typeof uploadPercentage === 'number' && uploadPercentage < 100 && (
                    <p className="mt-2 text-[11px] tabular-nums text-muted">{uploadPercentage}% uploaded</p>
                )}
            </div>
        </div>
    );
}
