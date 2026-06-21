const variants = {
    primary: 'bg-ink text-white shadow-soft hover:bg-graphite dark:bg-white dark:text-graphite dark:hover:bg-silver',
    outline: 'border border-silver-deep/80 bg-white/70 text-ink-soft hover:bg-silver dark:border-white/10 dark:bg-graphite/70 dark:text-white/72 dark:hover:bg-white/10',
    ghost: 'text-ink-soft hover:bg-silver hover:text-ink dark:text-white/70 dark:hover:bg-white/10 dark:hover:text-white',
    dark: 'bg-graphite text-white hover:bg-ink dark:bg-white dark:text-graphite',
};

const sizes = {
    sm: 'min-h-9 px-3 text-xs',
    md: 'min-h-11 px-4 text-sm',
    lg: 'min-h-12 px-6 text-base',
};

export default function Button({ as: Component = 'button', variant = 'primary', size = 'md', className = '', children, ...props }) {
    return (
        <Component
            className={`inline-flex items-center justify-center gap-2 rounded-lg font-extrabold transition disabled:pointer-events-none disabled:opacity-50 ${variants[variant] ?? variant} ${sizes[size] ?? sizes.md} ${className}`}
            {...props}
        >
            {children}
        </Component>
    );
}
