import { MoreHorizontal, X } from "lucide-react";
import {
    Children,
    Fragment,
    cloneElement,
    isValidElement,
    useEffect,
    useRef,
    useState,
} from "react";
import { createPortal } from "react-dom";

function flattenActions(children) {
    return Children.toArray(children).flatMap((child) => {
        if (!isValidElement(child)) return child ? [child] : [];
        if (child.type === Fragment)
            return flattenActions(child.props.children);
        return [child];
    });
}

export default function TableActions({
    children,
    label = "Buka menu aksi",
    triggerText = "Aksi",
}) {
    const actions = flattenActions(children);
    const [open, setOpen] = useState(false);
    const [position, setPosition] = useState({ top: 0, left: 0 });
    const triggerRef = useRef(null);
    const menuRef = useRef(null);

    useEffect(() => {
        if (!open) return undefined;

        const close = (event) => {
            if (
                !triggerRef.current?.contains(event.target) &&
                !menuRef.current?.contains(event.target)
            )
                setOpen(false);
        };
        const reposition = () => {
            const rect = triggerRef.current?.getBoundingClientRect();
            if (!rect) return;
            const width = 208;
            const gap = 6;
            const viewportPadding = 12;
            const menuHeight = menuRef.current?.offsetHeight ?? 0;
            const roomBelow = window.innerHeight - rect.bottom - viewportPadding;
            const openAbove = menuHeight > 0 && roomBelow < menuHeight + gap;
            const desiredTop = openAbove
                ? rect.top - menuHeight - gap
                : rect.bottom + gap;
            setPosition({
                top: Math.max(
                    viewportPadding,
                    Math.min(
                        desiredTop,
                        window.innerHeight - menuHeight - viewportPadding,
                    ),
                ),
                left: Math.max(
                    viewportPadding,
                    Math.min(
                        rect.right - width,
                        window.innerWidth - width - viewportPadding,
                    ),
                ),
            });
        };

        reposition();
        document.addEventListener("pointerdown", close);
        window.addEventListener("resize", reposition);
        window.addEventListener("scroll", reposition, true);
        return () => {
            document.removeEventListener("pointerdown", close);
            window.removeEventListener("resize", reposition);
            window.removeEventListener("scroll", reposition, true);
        };
    }, [open]);

    if (!actions.length) return null;

    return (
        <div className="flex justify-end whitespace-nowrap">
            <button
                aria-expanded={open}
                aria-haspopup="menu"
                aria-label={label}
                className="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-ink/25 bg-white/80 px-3 text-sm font-extrabold text-ink transition hover:bg-silver dark:border-white/20 dark:bg-white/5 dark:text-white"
                onClick={() => setOpen((value) => !value)}
                ref={triggerRef}
                type="button"
            >
                {open ? <X size={17} /> : <MoreHorizontal size={19} />}
                <span>{open ? "Tutup" : triggerText}</span>
            </button>
            {open &&
                createPortal(
                    <div
                        className="fixed z-[1000] grid w-52 gap-1 rounded-xl border border-silver-deep/80 bg-white p-2 shadow-2xl dark:border-white/15 dark:bg-[#20262d]"
                        ref={menuRef}
                        role="menu"
                        style={position}
                    >
                        {actions.map((action, index) =>
                            isValidElement(action)
                                ? cloneElement(action, {
                                      className: `w-full !justify-start ${action.props.className ?? ""}`,
                                      key: action.key ?? index,
                                      onClick: (event) => {
                                          action.props.onClick?.(event);
                                          setOpen(false);
                                      },
                                  })
                                : action,
                        )}
                    </div>,
                    document.body,
                )}
        </div>
    );
}
