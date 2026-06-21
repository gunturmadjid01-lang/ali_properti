import { Link, usePage } from '@inertiajs/react';

export default function NavLink({ href, icon: Icon, children, collapsed = false, className = '', activeClassName = 'bg-ink text-white dark:bg-white dark:text-graphite', inactiveClassName = 'text-ink-soft hover:bg-silver hover:text-ink dark:text-white/62 dark:hover:bg-white/10 dark:hover:text-white' }) {
    const { url } = usePage();
    const active = url.split('?')[0] === href;

    return (
        <Link
            className={`flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-extrabold transition ${active ? activeClassName : inactiveClassName} ${collapsed ? 'lg:justify-center' : ''} ${className}`}
            href={href}
        >
            {Icon && <Icon size={19} />}
            {!collapsed && <span>{children}</span>}
        </Link>
    );
}
