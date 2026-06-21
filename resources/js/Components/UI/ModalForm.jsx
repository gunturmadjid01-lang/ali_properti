import Modal from './Modal';

export default function ModalForm({ open, onClose, title, description, children, actions, contentClassName = '', size = 'lg', ...props }) {
    return (
        <Modal open={open} onClose={onClose} title={title} size={size}>
            <form className="grid gap-5" {...props}>
                {description && (
                    <p className="text-sm leading-6 text-ink-soft dark:text-white/58">
                        {description}
                    </p>
                )}
                <div className={`grid gap-4 ${contentClassName}`}>{children}</div>
                {actions && (
                    <div className="flex flex-wrap items-center justify-end gap-3 border-t border-silver-deep/60 pt-5 dark:border-white/10">
                        {actions}
                    </div>
                )}
            </form>
        </Modal>
    );
}
