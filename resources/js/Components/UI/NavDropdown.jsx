import { ChevronDown } from "lucide-react";
import { useState } from "react";
import NavLink from "./NavLink";

export default function NavDropdown({
    label,
    icon: Icon,
    items = [],
    collapsed = false,
}) {
    const [open, setOpen] = useState(false);

    return (
        <div>
            <button
                className={`flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-sm font-extrabold text-ink-soft transition hover:bg-silver hover:text-ink dark:text-white/62 dark:hover:bg-white/10 dark:hover:text-white ${collapsed ? "lg:justify-center" : ""}`}
                type="button"
                onClick={() => setOpen(!open)}
            >
                {Icon && <Icon size={19} />}
                {!collapsed && (
                    <>
                        <span className="flex-1 text-left">{label}</span>
                        <ChevronDown
                            className={`transition ${open ? "rotate-180" : ""}`}
                            size={16}
                        />
                    </>
                )}
            </button>
            {open && !collapsed && (
                <div className="mt-2 grid gap-1 border-l border-silver-deep pl-3">
                    {items.map((item, i) => (
                        <NavLink
                            className="min-h-10 text-xs"
                            href={item.path}
                            icon={item.icon}
                            key={i}
                        >
                            {item.title}
                        </NavLink>
                    ))}
                </div>
            )}
        </div>
    );
}
