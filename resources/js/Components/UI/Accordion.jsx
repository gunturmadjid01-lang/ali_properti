import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

export default function Accordion({ items = [], defaultOpen = 0, className = '' }) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <div className={`divide-y divide-silver-deep/60 overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:divide-white/10 dark:border-white/10 dark:bg-white/8 ${className}`}>
            {items.map((item, index) => (
                <div key={item.title}>
                    <button className="flex min-h-14 w-full items-center justify-between gap-4 px-5 text-left font-extrabold text-ink dark:text-white" type="button" onClick={() => setOpen(open === index ? null : index)}>
                        <span>{item.title}</span>
                        <ChevronDown className={`shrink-0 text-ink-soft transition ${open === index ? 'rotate-180' : ''}`} size={18} />
                    </button>
                    {open === index && <div className="px-5 pb-5 text-sm leading-7 text-ink-soft dark:text-white/62">{item.content}</div>}
                </div>
            ))}
        </div>
    );
}
