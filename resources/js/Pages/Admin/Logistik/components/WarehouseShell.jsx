import { ArrowRight } from 'lucide-react';

export function WarehousePage({ title, eyebrow, description, actions, children, className = '' }) {
    return (
        <div className={`grid gap-6 ${className}`}>
            {(eyebrow || title || description || actions) && (
                <section className="rounded-2xl border border-white/70 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-white/6">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="min-w-0">
                            {eyebrow && <p className="text-[11px] font-black uppercase tracking-[0.18em] text-ink-soft dark:text-white/45">{eyebrow}</p>}
                            {title && <h1 className="mt-2 text-3xl font-black tracking-tight text-ink dark:text-white">{title}</h1>}
                            {description && <p className="mt-3 max-w-4xl text-sm leading-6 font-medium text-ink-soft dark:text-white/58">{description}</p>}
                        </div>
                        {actions && <div className="flex flex-wrap gap-3">{actions}</div>}
                    </div>
                </section>
            )}
            {children}
        </div>
    );
}

export function StatGrid({ items = [] }) {
    return (
        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {items.map((item) => (
                <div key={item.label} className="rounded-2xl border border-white/70 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-white/6">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-[11px] font-black uppercase tracking-[0.18em] text-ink-soft dark:text-white/45">{item.label}</p>
                            <p className={`mt-2 text-3xl font-black ${item.tone ?? 'text-ink dark:text-white'}`}>{item.value}</p>
                            <p className="mt-2 text-sm font-medium text-ink-soft dark:text-white/55">{item.hint}</p>
                        </div>
                        <div className="rounded-2xl border border-silver-deep/60 bg-silver-soft p-3 dark:border-white/10 dark:bg-white/8">
                            <item.icon size={18} />
                        </div>
                    </div>
                </div>
            ))}
        </section>
    );
}

export function SectionCard({ title, description, actions, children, className = '' }) {
    return (
        <section className={`overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6 ${className}`}>
            {(title || description || actions) && (
                <div className="border-b border-silver-deep/50 px-5 py-4 dark:border-white/10">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            {title && <h2 className="text-lg font-black text-ink dark:text-white">{title}</h2>}
                            {description && <p className="mt-1 text-sm font-medium text-ink-soft dark:text-white/55">{description}</p>}
                        </div>
                        {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                    </div>
                </div>
            )}
            {children}
        </section>
    );
}

export function ActionGrid({ items = [], onAction }) {
    return (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {items.map((item) => (
                <button
                    key={item.title}
                    className="group flex min-h-[96px] items-center justify-between gap-4 rounded-2xl border border-silver-deep/60 bg-white/75 px-4 py-4 text-left transition hover:-translate-y-0.5 hover:border-ink/15 hover:bg-silver-soft dark:border-white/10 dark:bg-white/6 dark:hover:bg-white/10"
                    type="button"
                    onClick={() => onAction(item.href)}
                >
                    <span className="flex min-w-0 items-start gap-3">
                        <span className="rounded-xl border border-silver-deep/60 bg-silver-soft p-2.5 dark:border-white/10 dark:bg-white/8">
                            <item.icon size={16} />
                        </span>
                        <span className="min-w-0">
                            <span className="block text-sm font-extrabold text-ink dark:text-white">{item.title}</span>
                            <span className="mt-1 block text-xs leading-5 font-medium text-ink-soft dark:text-white/50">{item.description}</span>
                        </span>
                    </span>
                    <ArrowRight size={16} className="shrink-0 text-ink-soft transition group-hover:translate-x-0.5 dark:text-white/40" />
                </button>
            ))}
        </div>
    );
}
