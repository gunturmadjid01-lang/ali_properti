import { Link, router, usePage } from "@inertiajs/react";
import { useEffect, useMemo, useState } from "react";
import sidebarMenu from "../Sidebar/sidebarMenu";
import { Button, Dropdown } from "../Components/UI";
import RequestResponseModal from "../Components/UI/RequestResponseModal";
import FloatingChat from "../Components/Chat/FloatingChat";
import {
    DASHBOARD_TEMPLATE_STORAGE_KEY,
    readDashboardTemplatePreference,
    resolveDashboardTemplate,
} from "../Themes/dashboardTemplates";
import {
    Bell,
    ChevronDown,
    ChevronLeft,
    LogOut,
    Menu,
    MessageCircle,
    Moon,
    Search,
    Sun,
    UserCircle2,
    X,
} from "lucide-react";

function Avatar({ user, compact = false }) {
    const sizeClass = compact ? "h-10 w-10" : "h-12 w-12";

    if (user?.avatar_url) {
        return (
            <img
                className={`${sizeClass} rounded-2xl border border-silver-deep/70 object-cover`}
                src={user.avatar_url}
                alt={user.name}
            />
        );
    }

    return (
        <div
            className={`${sizeClass} grid place-items-center rounded-2xl border border-silver-deep/70 bg-gradient-to-br from-champagne to-gold text-sm font-black text-gold-deep`}
        >
            {user?.name?.charAt(0)?.toUpperCase() ?? "U"}
        </div>
    );
}

function SidebarIcon({ icon: Icon, className = "" }) {
    if (!Icon) {
        return null;
    }

    return <Icon className={`shrink-0 ${className}`} size={15} />;
}

function isActiveItem(item, currentUrl) {
    if (item.link) {
        const currentPath = currentUrl.split("?")[0].replace(/\/+$/, "") || "/";
        const itemPath = item.link.split("?")[0].replace(/\/+$/, "") || "/";

        if (
            item.exact
                ? currentPath === itemPath
                : currentPath === itemPath ||
                  currentPath.startsWith(`${itemPath}/`)
        ) {
            return true;
        }
    }

    return (
        item.items?.some((child) => isActiveItem(child, currentUrl)) ?? false
    );
}

function activeItemKey(items, currentUrl) {
    return items.find((item) => isActiveItem(item, currentUrl))?.title ?? null;
}

function permissionCandidates(permission) {
    const candidates = [permission];
    if (permission.endsWith(".view"))
        candidates.push(`${permission.slice(0, -5)}.manage`);
    if (permission.startsWith("marketing.")) {
        const lastDot = permission.lastIndexOf(".");
        const prefix = permission
            .slice(0, lastDot)
            .replace(/^marketing\./, "marketing-");
        const action = permission.slice(lastDot + 1);
        candidates.push(`${prefix}.${action}`);
        if (action === "manage") candidates.push(`${prefix}.view`);
    }
    return candidates;
}

function canSeeSidebarItem(item, permissions) {
    const has = (permission) =>
        permissionCandidates(permission).some((candidate) =>
            permissions.includes(candidate),
        );
    const permissionAllowed = !item.permission || has(item.permission);
    const permissionsAnyAllowed =
        !item.permissionsAny?.length ||
        item.permissionsAny.some((permission) => has(permission));

    return permissionAllowed && permissionsAnyAllowed;
}

function filterSidebarItems(items, permissions, inheritedPermission = true) {
    return items
        .map((item) => {
            const visible =
                inheritedPermission && canSeeSidebarItem(item, permissions);
            const children = item.items
                ? filterSidebarItems(item.items, permissions, visible)
                : null;

            if (item.items) {
                if (!visible || !children || children.length === 0) {
                    return null;
                }

                return { ...item, items: children };
            }

            return visible ? item : null;
        })
        .filter(Boolean);
}

function searchSidebarItems(items, query) {
    const keyword = query.trim().toLocaleLowerCase("id-ID");
    if (!keyword) return items;

    return items
        .map((item) => {
            const titleMatches = item.title
                .toLocaleLowerCase("id-ID")
                .includes(keyword);
            const children = item.items
                ? searchSidebarItems(item.items, query)
                : null;
            if (titleMatches) return item;
            if (children?.length) return { ...item, items: children };
            return null;
        })
        .filter(Boolean);
}

function SidebarList({ items, collapsed, currentUrl, badges = {}, level = 0 }) {
    const [openKey, setOpenKey] = useState(() =>
        activeItemKey(items, currentUrl),
    );

    useEffect(() => {
        const nextActiveKey = activeItemKey(items, currentUrl);

        if (nextActiveKey) {
            setOpenKey(nextActiveKey);
        }
    }, [items, currentUrl]);

    const listClassName =
        level === 1 && !collapsed
            ? "ml-3 space-y-1 pt-1"
            : level >= 2 && !collapsed
              ? "ml-2 space-y-1 pt-1"
              : level > 0
                ? "space-y-1 pt-1"
                : "space-y-1";

    return (
        <div className={listClassName}>
            {items.map((item) => (
                <SidebarItem
                    item={item}
                    collapsed={collapsed}
                    currentUrl={currentUrl}
                    badges={badges}
                    level={level}
                    open={openKey === item.title}
                    onToggle={() =>
                        setOpenKey(openKey === item.title ? null : item.title)
                    }
                    key={item.title}
                />
            ))}
        </div>
    );
}

function SidebarItem({
    item,
    collapsed,
    currentUrl,
    badges,
    level,
    open,
    onToggle,
}) {
    const hasChildren = Array.isArray(item.items) && item.items.length > 0;
    const active = isActiveItem(item, currentUrl);
    const badge = item.badgeKey ? Number(badges[item.badgeKey] ?? 0) : 0;
    const content = (
        <>
            <SidebarIcon icon={item.icon} />
            {!collapsed && <span className="truncate">{item.title}</span>}
            {!collapsed && badge > 0 && (
                <span className="ml-auto rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-black text-white">
                    {badge > 99 ? "99+" : badge}
                </span>
            )}
        </>
    );

    if (hasChildren) {
        const highlightParent = active && !open;

        return (
            <div className="space-y-1">
                <button
                    className={`flex min-h-9 w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-xs font-bold transition ${
                        highlightParent
                            ? "bg-ink text-white shadow-[0_10px_24px_rgba(31,37,43,0.14)] dark:bg-white dark:text-graphite"
                            : "text-ink-soft hover:bg-silver hover:text-ink dark:text-white/70 dark:hover:bg-white/10 dark:hover:text-white"
                    }`}
                    type="button"
                    onClick={onToggle}
                >
                    <span
                        className={`flex min-w-0 items-center gap-3 ${collapsed ? "justify-center" : ""}`}
                    >
                        {content}
                    </span>
                    {!collapsed && (
                        <ChevronDown
                            className={`shrink-0 transition ${open ? "rotate-180" : ""}`}
                            size={14}
                        />
                    )}
                </button>
                {(open || collapsed) && (
                    <SidebarList
                        items={item.items}
                        collapsed={collapsed}
                        currentUrl={currentUrl}
                        badges={badges}
                        level={level + 1}
                    />
                )}
            </div>
        );
    }

    return (
        <Link
            className={`flex min-h-9 items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold transition ${collapsed ? "justify-center" : ""} ${
                active
                    ? "bg-ink text-white shadow-[0_10px_24px_rgba(31,37,43,0.14)] dark:bg-white dark:text-graphite"
                    : "text-ink-soft hover:bg-silver hover:text-ink dark:text-white/70 dark:hover:bg-white/10 dark:hover:text-white"
            }`}
            href={item.link ?? "#"}
        >
            {content}
        </Link>
    );
}

export default function AdminLayout({ children, title = "Dasbor" }) {
    const { auth } = usePage().props;
    const { notifications } = usePage().props;
    const { sidebar_badges: sidebarBadges = {} } = usePage().props;
    const currentUrl = usePage().url;
    const user = auth?.user;
    const assignedPerumahans =
        auth?.assigned_perumahans ?? user?.assigned_perumahans ?? [];
    const activePerumahan =
        auth?.active_perumahan ?? user?.active_perumahan ?? null;
    const needsActivePerumahanSelection = Boolean(
        auth?.needs_active_perumahan_selection ??
        user?.needs_active_perumahan_selection,
    );
    const permissions = user?.permissions?.length ? user.permissions : [];
    const canViewAllPerumahans = (user?.roles ?? []).some((role) =>
        ["owner", "super_admin"].includes(role),
    );
    const displayUser = user ?? {
        name: "Guest Admin",
        email: "Akses tanpa login",
        roles: [],
    };
    const [collapsed, setCollapsed] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [menuSearch, setMenuSearch] = useState("");
    const [chatUnread, setChatUnread] = useState({ count: 0, sources: [] });
    const [theme, setTheme] = useState(() => {
        if (typeof window === "undefined") {
            return "light";
        }

        return localStorage.getItem("admin-theme") ?? "light";
    });
    const [dashboardTemplateKey, setDashboardTemplateKey] = useState(
        readDashboardTemplatePreference,
    );
    const dashboardTemplate = resolveDashboardTemplate(dashboardTemplateKey);

    useEffect(() => {
        localStorage.setItem("admin-theme", theme);
    }, [theme]);

    useEffect(() => {
        localStorage.setItem(
            DASHBOARD_TEMPLATE_STORAGE_KEY,
            dashboardTemplate.key,
        );
    }, [dashboardTemplate.key]);

    useEffect(() => {
        const changeDashboardTemplate = (event) => {
            setDashboardTemplateKey(
                resolveDashboardTemplate(event.detail?.template).key,
            );
        };

        window.addEventListener(
            "admin:dashboard-template-change",
            changeDashboardTemplate,
        );

        return () =>
            window.removeEventListener(
                "admin:dashboard-template-change",
                changeDashboardTemplate,
            );
    }, []);

    useEffect(() => {
        const updateChatUnread = (event) =>
            setChatUnread(event.detail ?? { count: 0, sources: [] });
        window.addEventListener("chat:unread", updateChatUnread);
        return () =>
            window.removeEventListener("chat:unread", updateChatUnread);
    }, []);

    const dark = theme === "dark";
    const logout = () => {
        router.post("/logout", {}, { preserveScroll: true });
    };
    const changeActivePerumahan = (perumahanId) => {
        router.post(
            "/admin/active-perumahan",
            { perumahan_id: perumahanId },
            { preserveScroll: true },
        );
    };

    const menuSections = useMemo(() => {
        return sidebarMenu
            .map((section) => ({
                ...section,
                items: filterSidebarItems(section.items ?? [], permissions),
            }))
            .filter((section) => (section.items ?? []).length > 0);
    }, [permissions]);

    const hasMenu = menuSections.length > 0;
    const searchedMenuSections = useMemo(
        () =>
            menuSections
                .map((section) => {
                    const keyword = menuSearch
                        .trim()
                        .toLocaleLowerCase("id-ID");
                    const sectionMatches = section.title
                        .toLocaleLowerCase("id-ID")
                        .includes(keyword);
                    return sectionMatches
                        ? section
                        : {
                              ...section,
                              items: searchSidebarItems(
                                  section.items,
                                  menuSearch,
                              ),
                          };
                })
                .filter((section) => section.items.length > 0),
        [menuSections, menuSearch],
    );

    return (
        <div
            className={`admin-theme-scope min-h-screen overflow-x-hidden bg-silver text-ink transition-colors dark:bg-[#0e1116] dark:text-white ${dark ? "dark" : ""}`}
            data-color-theme={theme}
            data-dashboard-template={dashboardTemplate.key}
        >
            {mobileOpen && (
                <button
                    className="fixed inset-0 z-30 bg-graphite/45 lg:hidden"
                    type="button"
                    onClick={() => setMobileOpen(false)}
                    aria-label="Tutup sidebar"
                />
            )}

            <aside
                className={`fixed inset-y-0 left-0 z-40 flex flex-col border-r border-white/70 bg-white/88 shadow-soft backdrop-blur-xl transition-all duration-300 dark:border-white/10 dark:bg-[#151a21]/94 ${
                    collapsed ? "lg:w-24" : "lg:w-72"
                } ${mobileOpen ? "w-72 translate-x-0" : "w-72 -translate-x-full lg:translate-x-0"}`}
            >
                <div className="flex min-h-4 justify-end px-4 pt-4 lg:hidden">
                    <button
                        className="rounded-lg p-2 text-ink-soft hover:bg-silver lg:hidden"
                        type="button"
                        onClick={() => setMobileOpen(false)}
                    >
                        <X size={19} />
                    </button>
                </div>

                {displayUser && (
                    <div className="border-b border-silver-deep/50 px-4 py-4 dark:border-white/10">
                        <Link
                            className={`flex items-center gap-3 rounded-2xl border border-silver-deep/60 bg-silver-soft p-3 dark:border-white/10 dark:bg-white/6 ${collapsed ? "lg:justify-center" : ""}`}
                            href="/admin/profile"
                        >
                            <Avatar user={displayUser} compact />
                            {!collapsed && (
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-extrabold text-ink dark:text-white">
                                        {displayUser.name}
                                    </p>
                                    <p className="truncate text-xs font-bold text-ink-soft dark:text-white/50">
                                        {displayUser.email}
                                    </p>
                                </div>
                            )}
                        </Link>
                        {!collapsed && (
                            <div className="mt-3 flex gap-2">
                                <Button
                                    as={Link}
                                    href="/admin/profile"
                                    variant="outline"
                                    size="sm"
                                    className="flex-1"
                                >
                                    <UserCircle2 size={16} />
                                    Profil
                                </Button>
                                {user ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="flex-1 text-red-600 hover:text-red-700 dark:text-red-300"
                                        onClick={logout}
                                    >
                                        <LogOut size={16} />
                                        Logout
                                    </Button>
                                ) : (
                                    <Button
                                        as={Link}
                                        href="/login"
                                        variant="ghost"
                                        size="sm"
                                        className="flex-1"
                                    >
                                        Login
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                )}

                <nav className="flex-1 space-y-3 overflow-y-auto p-4">
                    {!collapsed && hasMenu && (
                        <label className="relative block">
                            <Search
                                className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft dark:text-white/45"
                                size={16}
                            />
                            <input
                                className="w-full rounded-lg border border-silver-deep/70 bg-white/75 py-2.5 pl-9 pr-9 text-xs font-bold outline-none transition focus:border-gold dark:border-white/10 dark:bg-white/7"
                                type="search"
                                value={menuSearch}
                                onChange={(event) =>
                                    setMenuSearch(event.target.value)
                                }
                                placeholder="Cari menu..."
                                aria-label="Cari menu sidebar"
                            />
                            {menuSearch && (
                                <button
                                    className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-ink-soft"
                                    type="button"
                                    onClick={() => setMenuSearch("")}
                                    aria-label="Hapus pencarian"
                                >
                                    <X size={14} />
                                </button>
                            )}
                        </label>
                    )}
                    {searchedMenuSections.length ? (
                        searchedMenuSections.map((section) => (
                            <div className="space-y-2" key={section.title}>
                                {!collapsed && (
                                    <p className="px-2 text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">
                                        {section.title}
                                    </p>
                                )}
                                <SidebarList
                                    items={section.items}
                                    collapsed={collapsed}
                                    currentUrl={currentUrl}
                                    badges={sidebarBadges}
                                />
                            </div>
                        ))
                    ) : (
                        <div className="rounded-2xl border border-dashed border-silver-deep/60 p-4 text-sm font-bold text-ink-soft dark:border-white/10 dark:text-white/55">
                            Tidak ada menu yang cocok untuk role ini.
                        </div>
                    )}
                </nav>

                <div className="hidden border-t border-silver-deep/50 p-4 dark:border-white/10 lg:block">
                    <button
                        className="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-silver text-sm font-extrabold text-ink-soft hover:bg-silver dark:bg-white/8 dark:text-white/68 dark:hover:bg-white/12"
                        type="button"
                        onClick={() => setCollapsed(!collapsed)}
                    >
                        <ChevronLeft
                            className={`transition ${collapsed ? "rotate-180" : ""}`}
                            size={18}
                        />
                        {!collapsed && "Tutup Sidebar"}
                    </button>
                </div>
            </aside>

            <div
                className={`min-w-0 transition-all duration-300 ${collapsed ? "lg:pl-24" : "lg:pl-72"}`}
            >
                <header className="sticky top-0 z-20 flex min-h-16 min-w-0 items-center justify-between gap-3 border-b border-white/70 bg-silver-soft/85 px-4 backdrop-blur-xl dark:border-white/10 dark:bg-[#10141a]/88 lg:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <button
                            className="rounded-lg p-2 text-ink-soft hover:bg-silver lg:hidden"
                            type="button"
                            onClick={() => setMobileOpen(true)}
                        >
                            <Menu size={22} />
                        </button>
                        <div className="min-w-0">
                            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">
                                PT Ali Properti Indonesia
                            </p>
                            <h1 className="truncate text-lg font-extrabold">
                                {title}
                            </h1>
                        </div>
                    </div>
                    <div className="flex min-w-0 shrink-0 items-center gap-3">
                        {!canViewAllPerumahans && (
                        <div className="hidden w-[320px] md:block">
                            <Dropdown
                                value={
                                    activePerumahan?.value ??
                                    (activePerumahan?.id
                                        ? String(activePerumahan.id)
                                        : "")
                                }
                                label={
                                    assignedPerumahans.length
                                        ? "Pilih Properti Aktif"
                                        : "Belum ada properti"
                                }
                                options={assignedPerumahans}
                                onChange={changeActivePerumahan}
                                buttonClassName="min-h-10"
                            />
                        </div>
                        )}
                        <Button
                            as={Link}
                            href="/admin/notifications"
                            variant="outline"
                            size="sm"
                            className="relative"
                        >
                            <Bell size={17} />
                            <span className="hidden sm:inline">Notif</span>
                            {(notifications?.unread_count ?? 0) > 0 && (
                                <span className="absolute -right-2 -top-2 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-black text-white">
                                    {notifications.unread_count}
                                </span>
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            className="relative"
                            title={
                                chatUnread.sources.length
                                    ? `Pesan baru: ${chatUnread.sources.map((item) => `${item.name} (${item.count})`).join(", ")}`
                                    : "Buka chat"
                            }
                            onClick={() =>
                                window.dispatchEvent(new Event("chat:toggle"))
                            }
                        >
                            <MessageCircle size={17} />
                            <span className="hidden sm:inline">Chat</span>
                            {chatUnread.count > 0 && (
                                <span className="absolute -right-2 -top-2 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-black text-white">
                                    {chatUnread.count > 99
                                        ? "99+"
                                        : chatUnread.count}
                                </span>
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            onClick={() => setTheme(dark ? "light" : "dark")}
                        >
                            {dark ? <Sun size={17} /> : <Moon size={17} />}
                            <span className="hidden sm:inline">
                                {dark ? "Light" : "Dark"} Mode
                            </span>
                        </Button>
                    </div>
                </header>

                {!canViewAllPerumahans && (
                <div className="border-b border-white/70 bg-white/70 px-4 py-3 backdrop-blur-xl dark:border-white/10 dark:bg-[#10141a]/70 md:hidden">
                    <Dropdown
                        value={
                            activePerumahan?.value ??
                            (activePerumahan?.id
                                ? String(activePerumahan.id)
                                : "")
                        }
                        label={
                            assignedPerumahans.length
                                ? "Pilih Properti Aktif"
                                : "Belum ada properti"
                        }
                        options={assignedPerumahans}
                        onChange={changeActivePerumahan}
                        buttonClassName="min-h-10"
                    />
                </div>
                )}

                <main className="min-w-0 overflow-x-hidden p-4 lg:p-6">
                    {children}
                </main>
            </div>
            {needsActivePerumahanSelection && assignedPerumahans.length > 1 && (
                <div className="fixed inset-0 z-50 grid place-items-center bg-graphite/55 px-4 backdrop-blur-sm">
                    <section className="w-full max-w-lg rounded-2xl border border-white/80 bg-white p-5 shadow-soft dark:border-white/10 dark:bg-[#151a21]">
                        <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">
                            Properti Aktif
                        </p>
                        <h2 className="mt-1 text-xl font-extrabold">
                            Pilih perumahan yang mau dikelola
                        </h2>
                        <p className="mt-2 text-sm font-semibold text-ink-soft dark:text-white/55">
                            Data marketing akan mengikuti perumahan yang
                            dipilih.
                        </p>
                        <div className="mt-4 grid gap-2">
                            {assignedPerumahans.map((perumahan) => (
                                <button
                                    className="flex min-h-12 items-center justify-between rounded-lg border border-silver-deep/70 px-4 text-left text-sm font-extrabold transition hover:bg-silver-soft dark:border-white/10 dark:hover:bg-white/8"
                                    key={perumahan.id}
                                    type="button"
                                    onClick={() =>
                                        changeActivePerumahan(
                                            perumahan.value ??
                                                String(perumahan.id),
                                        )
                                    }
                                >
                                    <span>
                                        {perumahan.label ??
                                            perumahan.nama_perusahaan}
                                    </span>
                                    <ChevronDown
                                        className="-rotate-90 text-ink-soft"
                                        size={16}
                                    />
                                </button>
                            ))}
                        </div>
                    </section>
                </div>
            )}
            <RequestResponseModal />
            {user && <FloatingChat />}
        </div>
    );
}
