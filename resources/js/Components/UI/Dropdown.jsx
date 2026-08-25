import { ChevronDown, Plus, Search } from "lucide-react";
import { usePage } from "@inertiajs/react";
import { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import HelpTooltip, { fieldHelp } from "./HelpTooltip";

export default function Dropdown({
    label = "Pilih",
    help,
    value,
    options = [],
    onChange,
    onCreate,
    creatable = false,
    createLabel = "Tambah pilihan",
    className = "",
    buttonClassName = "",
    menuClassName = "",
    searchable = true,
    disabled = false,
    name,
    id,
    error: providedError,
}) {
    const sharedErrors = usePage().props.errors ?? {};
    const error = providedError ?? sharedErrors[name] ?? sharedErrors[id];
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState("");
    const [createdOptions, setCreatedOptions] = useState([]);
    const [menuStyle, setMenuStyle] = useState({});
    const wrapperRef = useRef(null);
    const buttonRef = useRef(null);
    const menuRef = useRef(null);
    const availableOptions = useMemo(
        () => [
            ...options,
            ...createdOptions.filter(
                (created) =>
                    !options.some(
                        (option) =>
                            String(option.value) === String(created.value),
                    ),
            ),
        ],
        [createdOptions, options],
    );
    const selected = availableOptions.find(
        (option) => String(option.value) === String(value),
    );
    const filteredOptions = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        if (!keyword) {
            return availableOptions;
        }

        return availableOptions.filter((option) =>
            Object.values(option).some((item) =>
                String(item ?? "")
                    .toLowerCase()
                    .includes(keyword),
            ),
        );
    }, [availableOptions, search]);
    const canCreate =
        creatable &&
        search.trim() !== "" &&
        !availableOptions.some(
            (option) =>
                String(option.label ?? "")
                    .trim()
                    .toLowerCase() === search.trim().toLowerCase(),
        );

    useEffect(() => {
        if (!open) {
            return;
        }

        const updatePosition = () => {
            if (!buttonRef.current) {
                return;
            }

            const rect = buttonRef.current.getBoundingClientRect();
            setMenuStyle({
                position: "fixed",
                top: `${rect.bottom + 8}px`,
                left: `${rect.left}px`,
                width: `${rect.width}px`,
                zIndex: 9999,
            });
        };

        const handlePointerDown = (event) => {
            const target = event.target;
            const insideButton = wrapperRef.current?.contains(target);
            const insideMenu = menuRef.current?.contains(target);

            if (!insideButton && !insideMenu) {
                setOpen(false);
            }
        };

        updatePosition();
        window.addEventListener("resize", updatePosition);
        window.addEventListener("scroll", updatePosition, true);
        document.addEventListener("mousedown", handlePointerDown);

        return () => {
            window.removeEventListener("resize", updatePosition);
            window.removeEventListener("scroll", updatePosition, true);
            document.removeEventListener("mousedown", handlePointerDown);
        };
    }, [open]);

    const menu =
        open && !disabled
            ? createPortal(
                  <div
                      ref={menuRef}
                      className={`overflow-hidden rounded-lg border border-ink/70 bg-white p-1 dark:border-white/40 dark:bg-graphite ${menuClassName}`}
                      style={menuStyle}
                      onMouseDown={(event) => event.stopPropagation()}
                      onClick={(event) => event.stopPropagation()}
                  >
                      {searchable && (
                          <label className="mb-1 flex min-h-10 items-center gap-2 rounded-md border border-silver-deep/60 bg-silver-soft px-3 text-sm font-bold text-ink-soft dark:border-white/10 dark:bg-white/8 dark:text-white/70">
                              <Search size={15} />
                              <input
                                  className="w-full bg-transparent outline-none placeholder:text-ink-soft/50 dark:placeholder:text-white/35"
                                  placeholder="Cari pilihan..."
                                  value={search}
                                  onChange={(event) =>
                                      setSearch(event.target.value)
                                  }
                              />
                          </label>
                      )}
                      <div className="max-h-72 overflow-y-auto">
                          {canCreate && (
                              <button
                                  className="mb-1 flex min-h-11 w-full items-center gap-2 rounded-md bg-amber-50 px-3 text-left text-sm font-extrabold text-amber-800 transition hover:bg-amber-100 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/15"
                                  type="button"
                                  onClick={async () => {
                                      const created = search.trim();
                                      const result = await onCreate?.(created);
                                      const option = result?.value
                                          ? result
                                          : {
                                                value: created,
                                                label: created,
                                                created: true,
                                            };
                                      setCreatedOptions((current) => [
                                          ...current.filter(
                                              (item) =>
                                                  String(item.value) !==
                                                  String(option.value),
                                          ),
                                          option,
                                      ]);
                                      onChange?.(String(option.value), option);
                                      setOpen(false);
                                      setSearch("");
                                  }}
                              >
                                  <Plus size={16} /> {createLabel}: “
                                  {search.trim()}”
                              </button>
                          )}
                          {filteredOptions.map((option) => (
                              <button
                                  className={`flex min-h-10 w-full items-center rounded-md px-3 text-left text-sm font-bold transition ${
                                      String(option.value) === String(value)
                                          ? "bg-ink text-white"
                                          : "text-ink-soft hover:bg-silver dark:text-white/70 dark:hover:bg-white/10"
                                  }`}
                                  type="button"
                                  onClick={() => {
                                      onChange?.(option.value, option);
                                      setOpen(false);
                                      setSearch("");
                                  }}
                                  key={option.value}
                              >
                                  {option.label}
                              </button>
                          ))}
                          {filteredOptions.length === 0 && !canCreate && (
                              <p className="px-3 py-3 text-sm font-bold text-ink-soft dark:text-white/50">
                                  Pilihan tidak ditemukan.
                              </p>
                          )}
                      </div>
                  </div>,
                  document.body,
              )
            : null;

    return (
        <div ref={wrapperRef} className={`relative isolate ${className}`}>
            <div className="relative">
                <button
                    ref={buttonRef}
                    id={id}
                    name={name}
                    className={`relative z-20 flex min-h-11 w-full cursor-pointer items-center justify-between gap-3 rounded-lg border bg-white/90 px-4 pr-16 text-left text-sm font-extrabold text-ink transition hover:bg-silver dark:bg-[#151a20] dark:text-white dark:hover:bg-white/12 ${error ? "border-red-500 dark:border-red-400" : "border-ink/70 dark:border-white/40"} ${buttonClassName}`}
                    role="combobox"
                    aria-expanded={open}
                    type="button"
                    disabled={disabled}
                    onClick={() => {
                        if (disabled) {
                            return;
                        }
                        setOpen((current) => !current);
                        setSearch("");
                    }}
                    onKeyDown={(event) => {
                        if (disabled) {
                            return;
                        }

                        if (event.key === "Enter" || event.key === " ") {
                            event.preventDefault();
                            setOpen((current) => !current);
                            setSearch("");
                        }
                    }}
                >
                    <span>{selected?.label ?? label}</span>
                    <ChevronDown
                        className={`transition ${open ? "rotate-180" : ""}`}
                        size={17}
                    />
                </button>
                <span className="absolute inset-y-0 right-9 z-30 flex items-center">
                    <HelpTooltip
                        text={help ?? fieldHelp({ label })}
                        label={`Bantuan untuk ${label}`}
                    />
                </span>
            </div>
            {menu}
            {error && (
                <span className="mt-2 block text-xs font-bold text-red-600 dark:text-red-300">
                    {error}
                </span>
            )}
        </div>
    );
}
