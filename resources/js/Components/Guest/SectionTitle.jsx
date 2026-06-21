import { BadgeCheck } from 'lucide-react';

export default function SectionTitle({ eyebrow, title, children, light = false }) {
    return (
        <div className="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="max-w-2xl">
                <span
                    className={`inline-flex min-h-9 items-center gap-2 rounded-full border px-4 text-xs font-extrabold uppercase tracking-[0.14em] ${
                        light
                            ? 'border-champagne/45 bg-white/10 text-champagne'
                            : 'border-gold/30 bg-champagne/55 text-gold-deep'
                    }`}
                >
                    <BadgeCheck size={15} /> {eyebrow}
                </span>
                <h2 className={`mt-4 font-display text-3xl font-extrabold leading-[1.08] md:text-4xl ${light ? 'text-white' : 'text-ink'}`}>
                    {title}
                </h2>
            </div>
            {children && <p className={`max-w-lg text-sm leading-7 md:text-base ${light ? 'text-white/72' : 'text-ink-soft'}`}>{children}</p>}
        </div>
    );
}
