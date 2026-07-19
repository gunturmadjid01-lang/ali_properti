import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

export default function Form({
    title,
    description,
    children,
    actions,
    className = '',
    contentClassName = '',
    defaultOpen = true,
    collapsible = false,
    ...props
}) {
    const [open, setOpen] = useState(defaultOpen);
    const hasHeader = Boolean(title || description);

    return (
        <form className={`overflow-hidden rounded-lg border border-ink/70 bg-white/90 backdrop-blur dark:border-white/40 dark:bg-white/8 ${className}`} {...props}>
            {hasHeader && (
                <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                    <button
                        type="button"
                        className={`flex w-full items-start justify-between gap-4 text-left ${collapsible ? 'cursor-pointer' : 'cursor-default'}`}
                        onClick={collapsible ? () => setOpen((value) => !value) : undefined}
                    >
                        <div className="min-w-0">
                            {title && <h2 className="text-lg font-extrabold text-ink dark:text-white">{title}</h2>}
                            {description && <p className="mt-2 text-sm leading-6 text-ink-soft dark:text-white/58">{description}</p>}
                        </div>
                        {collapsible && (
                            <ChevronDown className={`mt-1 shrink-0 text-ink-soft transition ${open ? 'rotate-180' : ''}`} size={18} />
                        )}
                    </button>
                </div>
            )}

            {(!collapsible || open) && (
                <div className="px-5 py-5">
                    <div className={`grid gap-4 ${contentClassName}`}>{children}</div>
                    {actions && <div className="mt-6 flex flex-wrap items-center justify-end gap-3">{actions}</div>}
                </div>
            )}
        </form>
    );
}
