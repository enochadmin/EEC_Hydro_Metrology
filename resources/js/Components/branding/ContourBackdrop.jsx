import { cn } from '@/Utils/cn';

const contours = [
    'M-140 92C20 16 196 42 308 112s200 82 306 28 188-24 302 60',
    'M-160 156C10 72 206 98 324 172s208 78 312 24 192-16 308 70',
    'M-180 220C0 128 216 154 340 234s216 74 318 20 196-8 314 80',
    'M-200 284C-10 184 226 210 356 296s224 70 324 16 200 0 320 90',
    'M-220 348C-20 240 236 266 372 358s232 66 330 12 204 8 326 100',
    'M-240 412C-30 296 246 322 388 420s240 62 336 8 208 16 332 110',
    'M-260 476C-40 352 256 378 404 482s248 58 342 4 212 24 338 120',
    'M-280 540C-50 408 266 434 420 544s256 54 348 0 216 32 344 130',
    'M-300 604C-60 464 276 490 436 606s264 50 354-4 220 40 350 140',
    'M-320 668C-70 520 286 546 452 668s272 46 360-8 224 48 356 150',
    'M-340 732C-80 576 296 602 468 730s280 42 366-12 228 56 362 160',
    'M-360 796C-90 632 306 658 484 792s288 38 372-16 232 64 368 170',
];

/**
 * Topographic contour lines used behind the brand panels on public and
 * authentication screens. Colour comes from `currentColor`.
 */
export default function ContourBackdrop({ className }) {
    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 800 900"
            preserveAspectRatio="xMidYMid slice"
            fill="none"
            className={cn('pointer-events-none absolute inset-0 h-full w-full', className)}
        >
            {contours.map((path, index) => (
                <path
                    key={path}
                    d={path}
                    stroke="currentColor"
                    strokeWidth={index % 3 === 0 ? 1.4 : 1}
                    strokeLinecap="round"
                    opacity={index % 2 === 0 ? 0.9 : 0.55}
                />
            ))}
        </svg>
    );
}
