import { Link } from '@inertiajs/react';

export default function Pagination({ links = [] }) {
    if (!links.length) return null;

    return (
        <div className="flex flex-wrap gap-2 border-t border-silver-deep/60 p-4 dark:border-white/10">
            {links.map((link, index) => (
                <Link
                    className={`rounded-md px-3 py-2 text-sm font-extrabold transition ${
                        link.active
                            ? 'bg-ink text-white dark:bg-white dark:text-ink'
                            : 'bg-silver-soft text-ink-soft hover:bg-silver dark:bg-white/8 dark:text-white/70 dark:hover:bg-white/12'
                    } ${!link.url ? 'pointer-events-none opacity-45' : ''}`}
                    href={link.url ?? '#'}
                    preserveScroll
                    preserveState
                    key={`${link.label}-${index}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}
