import { useEffect, useMemo, useRef, useState } from "react";
import { usePage } from "@inertiajs/react";
import axios from "axios";
import Echo from "laravel-echo";
import Pusher from "pusher-js";
import {
    ArrowLeft,
    BellOff,
    CheckCheck,
    ChevronDown,
    Globe2,
    LoaderCircle,
    MessageCircle,
    FileText,
    MoreVertical,
    Paperclip,
    RotateCcw,
    Search,
    Send,
    Trash2,
    UserRound,
    Users,
    Volume2,
    X,
} from "lucide-react";

const api = axios;
api.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

function Avatar({ item, size = "h-9 w-9" }) {
    if (item?.avatar_url)
        return (
            <img
                className={`${size} shrink-0 rounded-full object-cover`}
                src={item.avatar_url}
                alt=""
            />
        );
    return (
        <span
            className={`${size} grid shrink-0 place-items-center rounded-full bg-gradient-to-br from-champagne to-gold text-xs font-black text-gold-deep`}
        >
            {item?.name?.[0]?.toUpperCase() ?? "?"}
        </span>
    );
}

function OnlineDot({ online, withLabel = false }) {
    return (
        <span
            className={`inline-flex items-center gap-1 text-[10px] font-bold ${online ? "text-emerald-600" : "text-ink-soft dark:text-white/40"}`}
        >
            <span
                className={`h-2 w-2 rounded-full ${online ? "bg-emerald-500 ring-2 ring-emerald-100" : "bg-slate-300 dark:bg-white/25"}`}
            />
            {withLabel && (online ? "Online" : "Offline")}
        </span>
    );
}

function UserPicker({ users, onlineIds, onClose, onSelect }) {
    const [query, setQuery] = useState("");
    const filtered = [...users]
        .filter((user) =>
            `${user.name} ${user.email}`
                .toLowerCase()
                .includes(query.toLowerCase()),
        )
        .sort(
            (a, b) =>
                Number(onlineIds.has(b.id)) - Number(onlineIds.has(a.id)) ||
                a.name.localeCompare(b.name),
        );
    return (
        <div
            className="fixed inset-0 z-[80] grid place-items-center bg-graphite/50 px-4 backdrop-blur-sm"
            onMouseDown={(e) => e.target === e.currentTarget && onClose()}
        >
            <section className="w-full max-w-md overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl dark:border-white/10 dark:bg-[#151a21]">
                <header className="flex items-center justify-between border-b border-silver-deep/60 p-4 dark:border-white/10">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-wider text-ink-soft">
                            Chat pribadi
                        </p>
                        <h3 className="text-lg font-black">Pilih pengguna</h3>
                    </div>
                    <button
                        className="rounded-xl p-2 hover:bg-silver dark:hover:bg-white/10"
                        onClick={onClose}
                        type="button"
                    >
                        <X size={18} />
                    </button>
                </header>
                <div className="p-4">
                    <label className="flex items-center gap-2 rounded-xl border border-silver-deep/70 px-3 dark:border-white/10">
                        <Search size={17} className="text-ink-soft" />
                        <input
                            autoFocus
                            className="min-h-11 w-full bg-transparent text-sm outline-none"
                            placeholder="Cari nama atau email..."
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                    </label>
                    <div className="mt-3 max-h-80 space-y-1 overflow-y-auto">
                        {filtered.map((user) => (
                            <button
                                key={user.id}
                                className="flex w-full items-center gap-3 rounded-xl p-3 text-left hover:bg-silver-soft dark:hover:bg-white/8"
                                onClick={() => onSelect(user)}
                                type="button"
                            >
                                <span className="relative">
                                    <Avatar item={user} />
                                    <span
                                        className={`absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-[#151a21] ${onlineIds.has(user.id) ? "bg-emerald-500" : "bg-slate-300"}`}
                                    />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-extrabold">
                                        {user.name}
                                    </span>
                                    <span className="block truncate text-xs text-ink-soft dark:text-white/50">
                                        {user.email}
                                    </span>
                                </span>
                                <OnlineDot
                                    online={onlineIds.has(user.id)}
                                    withLabel
                                />
                            </button>
                        ))}
                        {!filtered.length && (
                            <p className="py-10 text-center text-sm font-semibold text-ink-soft">
                                Pengguna tidak ditemukan.
                            </p>
                        )}
                    </div>
                </div>
            </section>
        </div>
    );
}

function timeLabel(value) {
    if (!value) return "";
    return new Intl.DateTimeFormat("id-ID", {
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
}

function dateLabel(value) {
    const date = new Date(value);
    const today = new Date();
    if (date.toDateString() === today.toDateString()) return "Hari ini";
    return new Intl.DateTimeFormat("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(date);
}

export default function FloatingChat() {
    const user = usePage().props.auth?.user;
    const [open, setOpen] = useState(false);
    const [picker, setPicker] = useState(false);
    const [openingUser, setOpeningUser] = useState(null);
    const [destinationMenu, setDestinationMenu] = useState(false);
    const [tab, setTab] = useState("chat");
    const [data, setData] = useState({ conversations: [], users: [] });
    const [activeId, setActiveId] = useState(null);
    const [messages, setMessages] = useState([]);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(false);
    const [sending, setSending] = useState(false);
    const [sendError, setSendError] = useState("");
    const [body, setBody] = useState("");
    const [attachment, setAttachment] = useState(null);
    const [onlineIds, setOnlineIds] = useState(
        () => new Set(user?.id ? [user.id] : []),
    );
    const [menuId, setMenuId] = useState(null);
    const [muteMenu, setMuteMenu] = useState(false);
    const bottomRef = useRef(null);
    const fileRef = useRef(null);
    const activeIdRef = useRef(null);
    const messageLoadRef = useRef(0);
    const loadedConversationRef = useRef(null);
    const messageCacheRef = useRef(new Map());
    const conversationsRef = useRef([]);
    const notificationAudioRef = useRef({ global: null, direct: null });
    const openRef = useRef(false);
    const sessionOnlineRef = useRef(new Set(user?.id ? [Number(user.id)] : []));

    const active = openingUser
        ? {
              id: null,
              type: "direct",
              name: openingUser.name,
              avatar_url: openingUser.avatar_url,
              other_user_id: openingUser.id,
          }
        : (data.conversations.find((item) => item.id === activeId) ??
          (!activeId
              ? data.conversations.find((item) => item.type === "global")
              : null));
    const unread = data.conversations.reduce(
        (sum, item) => sum + Number(item.unread_count || 0),
        0,
    );
    const global = data.conversations.find((item) => item.type === "global");
    const unreadSources = data.conversations.filter(
        (item) => Number(item.unread_count) > 0,
    );

    const refresh = async () => {
        try {
            const response = await api.get("/admin/chat/bootstrap");
            setData(response.data);
            const unreadConversations = response.data.conversations.filter(
                (item) => Number(item.unread_count) > 0,
            );
            window.dispatchEvent(
                new CustomEvent("chat:unread", {
                    detail: {
                        count: unreadConversations.reduce(
                            (sum, item) => sum + Number(item.unread_count || 0),
                            0,
                        ),
                        sources: unreadConversations.map((item) => ({
                            id: item.id,
                            name: item.name,
                            type: item.type,
                            count: Number(item.unread_count || 0),
                        })),
                    },
                }),
            );
            const sessionIds = new Set(
                (response.data.online_user_ids || [user.id]).map(Number),
            );
            sessionOnlineRef.current = sessionIds;
            setOnlineIds((current) => new Set([...current, ...sessionIds]));
            setActiveId(
                (current) =>
                    current ??
                    response.data.conversations.find(
                        (item) => item.type === "global",
                    )?.id ??
                    null,
            );
        } catch (error) {
            console.error("Gagal memuat chat", error);
        }
    };

    const loadMessages = async (conversationId, before = null) => {
        if (!conversationId) return;
        const requestId = before
            ? messageLoadRef.current
            : ++messageLoadRef.current;
        setLoading(true);
        try {
            const response = await api.get(
                `/admin/chat/conversations/${conversationId}/messages`,
                { params: before ? { before } : {} },
            );
            if (
                requestId !== messageLoadRef.current ||
                Number(conversationId) !== Number(activeIdRef.current)
            )
                return;
            setMessages((current) =>
                before
                    ? [...response.data.messages, ...current]
                    : response.data.messages,
            );
            const nextMessages = before
                ? [
                      ...response.data.messages,
                      ...(messageCacheRef.current.get(Number(conversationId)) ||
                          []),
                  ]
                : response.data.messages;
            messageCacheRef.current.set(Number(conversationId), nextMessages);
            loadedConversationRef.current = Number(conversationId);
            setHasMore(response.data.has_more);
            if (!before && openRef.current)
                await api.post(
                    `/admin/chat/conversations/${conversationId}/read`,
                );
            setData((current) => ({
                ...current,
                conversations: current.conversations.map((item) =>
                    item.id === conversationId
                        ? { ...item, unread_count: 0 }
                        : item,
                ),
            }));
        } finally {
            if (requestId === messageLoadRef.current) setLoading(false);
        }
    };

    useEffect(() => {
        if (user) refresh();
    }, [user?.id]);
    useEffect(() => {
        const sounds = {
            global: new Audio("/media/global_notif.wav"),
            direct: new Audio("/media/user_notif.wav"),
        };
        Object.values(sounds).forEach((audio) => {
            audio.preload = "auto";
            audio.load();
        });
        notificationAudioRef.current = sounds;

        const unlockAudio = () => {
            Object.values(sounds).forEach((audio) => {
                audio.muted = true;
                audio
                    .play()
                    .then(() => {
                        audio.pause();
                        audio.currentTime = 0;
                        audio.muted = false;
                    })
                    .catch(() => {
                        audio.muted = false;
                    });
            });
            window.removeEventListener("pointerdown", unlockAudio);
            window.removeEventListener("keydown", unlockAudio);
        };

        window.addEventListener("pointerdown", unlockAudio, { once: true });
        window.addEventListener("keydown", unlockAudio, { once: true });
        return () => {
            window.removeEventListener("pointerdown", unlockAudio);
            window.removeEventListener("keydown", unlockAudio);
            Object.values(sounds).forEach((audio) => {
                audio.pause();
                audio.src = "";
            });
        };
    }, []);
    useEffect(() => {
        openRef.current = open;
    }, [open]);
    useEffect(() => {
        activeIdRef.current = activeId;
        if (
            open &&
            activeId &&
            Number(loadedConversationRef.current) !== Number(activeId)
        )
            loadMessages(activeId);
    }, [activeId, open]);
    useEffect(() => {
        if (open) bottomRef.current?.scrollIntoView({ behavior: "smooth" });
    }, [messages.length, open]);
    useEffect(() => {
        window.dispatchEvent(
            new CustomEvent("chat:unread", {
                detail: {
                    count: unread,
                    sources: unreadSources.map((item) => ({
                        id: item.id,
                        name: item.name,
                        type: item.type,
                        count: Number(item.unread_count || 0),
                    })),
                },
            }),
        );
    }, [data.conversations, unread]);
    useEffect(() => {
        conversationsRef.current = data.conversations;
    }, [data.conversations]);

    useEffect(() => {
        if (!user) return undefined;
        const key = import.meta.env.VITE_PUSHER_APP_KEY;
        let echo;
        const receive = (event) => {
            const conversationId = Number(event.conversation_id);
            const isCurrentOpen =
                openRef.current &&
                conversationId === Number(activeIdRef.current);

            if (event.action === "message.created" && event.message) {
                const eventConversation = conversationsRef.current.find(
                    (item) => Number(item.id) === conversationId,
                );
                if (
                    Number(event.message.sender_id) !== Number(user.id) &&
                    !eventConversation?.is_muted
                ) {
                    const soundType =
                        event.conversation_type === "global"
                            ? "global"
                            : "direct";
                    const audio = notificationAudioRef.current[soundType];
                    if (audio) {
                        audio.currentTime = 0;
                        audio.play().catch(() => {});
                    }
                }
                const cached =
                    messageCacheRef.current.get(conversationId) || [];
                if (!cached.some((item) => item.id === event.message.id)) {
                    const next = [...cached, event.message];
                    messageCacheRef.current.set(conversationId, next);
                    if (isCurrentOpen) setMessages(next);
                }

                const conversationFound = conversationsRef.current.some(
                    (item) => Number(item.id) === conversationId,
                );
                setData((current) => ({
                    ...current,
                    conversations: current.conversations.map((item) => {
                        if (Number(item.id) !== conversationId) return item;
                        const incoming =
                            Number(event.message.sender_id) !== Number(user.id);
                        return {
                            ...item,
                            last_message: event.message,
                            unread_count:
                                incoming && !isCurrentOpen
                                    ? Number(item.unread_count || 0) + 1
                                    : isCurrentOpen
                                      ? 0
                                      : item.unread_count,
                        };
                    }),
                }));

                if (!conversationFound) refresh();
                if (
                    isCurrentOpen &&
                    Number(event.message.sender_id) !== Number(user.id)
                ) {
                    api.post(
                        `/admin/chat/conversations/${conversationId}/read`,
                    ).catch(() => {});
                }
                return;
            }

            if (event.action === "message.recalled" && event.message) {
                const cached =
                    messageCacheRef.current.get(conversationId) || [];
                const next = cached.map((item) =>
                    item.id === event.message.id ? event.message : item,
                );
                messageCacheRef.current.set(conversationId, next);
                if (isCurrentOpen) setMessages(next);
                setData((current) => ({
                    ...current,
                    conversations: current.conversations.map((item) =>
                        Number(item.id) === conversationId &&
                        item.last_message?.id === event.message.id
                            ? { ...item, last_message: event.message }
                            : item,
                    ),
                }));
            }
        };
        if (key) {
            window.Pusher = Pusher;
            echo = new Echo({
                broadcaster: "pusher",
                key,
                cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || "ap1",
                forceTLS: true,
            });
            echo.private("chat.global").listen(".chat.updated", receive);
            echo.private(`chat.user.${user.id}`).listen(
                ".chat.updated",
                receive,
            );
            echo.join("chat.online")
                .here((members) =>
                    setOnlineIds(
                        new Set([
                            ...sessionOnlineRef.current,
                            ...members.map((member) => Number(member.id)),
                        ]),
                    ),
                )
                .joining((member) =>
                    setOnlineIds(
                        (current) => new Set([...current, Number(member.id)]),
                    ),
                )
                .leaving((member) =>
                    setOnlineIds((current) => {
                        const next = new Set(current);
                        if (!sessionOnlineRef.current.has(Number(member.id)))
                            next.delete(Number(member.id));
                        return next;
                    }),
                );
        } else {
            setOnlineIds(new Set([Number(user.id)]));
        }
        const pollWhenVisible = () => {
            if (document.visibilityState === "visible") refresh();
        };
        // Pusher menjadi jalur utama. Polling hanya fallback berkala agar
        // php artisan serve tidak terus dipenuhi request chat di background.
        const polling = window.setInterval(
            pollWhenVisible,
            key ? 300000 : 60000,
        );
        document.addEventListener("visibilitychange", pollWhenVisible);
        return () => {
            window.clearInterval(polling);
            document.removeEventListener("visibilitychange", pollWhenVisible);
            echo?.disconnect();
        };
    }, [user?.id]);

    useEffect(() => {
        const toggle = () => setOpen((current) => !current);
        window.addEventListener("chat:toggle", toggle);
        return () => window.removeEventListener("chat:toggle", toggle);
    }, []);

    const selectConversation = (conversation) => {
        setOpeningUser(null);
        activeIdRef.current = conversation.id;
        messageLoadRef.current += 1;
        const cached = messageCacheRef.current.get(Number(conversation.id));
        loadedConversationRef.current = cached ? Number(conversation.id) : null;
        setMessages(cached || []);
        setHasMore(false);
        setSendError("");
        setMuteMenu(false);
        setActiveId(conversation.id);
        setTab("chat");
    };
    const chooseUser = async (selected) => {
        setPicker(false);
        setTab("chat");
        setOpeningUser(selected);
        setMessages([]);
        setHasMore(false);
        setSendError("");
        activeIdRef.current = null;
        messageLoadRef.current += 1;
        setActiveId(null);
        try {
            const response = await api.post("/admin/chat/direct", {
                user_id: selected.id,
            });
            setData((current) => ({
                ...current,
                conversations: [
                    response.data,
                    ...current.conversations.filter(
                        (item) => item.id !== response.data.id,
                    ),
                ],
            }));
            selectConversation(response.data);
        } catch (error) {
            setOpeningUser(null);
            setSendError(
                error.response?.data?.message ||
                    "Chat tidak dapat dibuka. Silakan coba kembali.",
            );
        }
    };
    const send = async (e) => {
        e.preventDefault();
        const text = body.trim();
        if ((!text && !attachment) || !active?.id || sending) return;
        const form = new FormData();
        if (text) form.append("body", text);
        if (attachment) form.append("attachment", attachment);
        const pendingAttachment = attachment;
        setSendError("");
        setSending(true);
        setBody("");
        setAttachment(null);
        try {
            const response = await api.post(
                `/admin/chat/conversations/${active.id}/messages`,
                form,
            );
            const conversationId = Number(active.id);
            const cached = messageCacheRef.current.get(conversationId) || [];
            const next = cached.some((item) => item.id === response.data.id)
                ? cached
                : [...cached, response.data];
            messageCacheRef.current.set(conversationId, next);
            loadedConversationRef.current = conversationId;
            setMessages(next);
            setData((current) => ({
                ...current,
                conversations: current.conversations.map((item) =>
                    Number(item.id) === conversationId
                        ? { ...item, last_message: response.data }
                        : item,
                ),
            }));
        } catch (error) {
            setBody(text);
            setAttachment(pendingAttachment);
            setSendError(
                error.response?.data?.message ||
                    "Pesan gagal dikirim. Silakan coba kembali.",
            );
        } finally {
            setSending(false);
        }
    };
    const removeMessage = async (message) => {
        if (!window.confirm("Hapus pesan ini dari tampilan Anda?")) return;
        await api.delete(`/admin/chat/messages/${message.id}`);
        setMessages((current) =>
            current.filter((item) => item.id !== message.id),
        );
        setMenuId(null);
    };
    const recallMessage = async (message) => {
        try {
            const response = await api.post(
                `/admin/chat/messages/${message.id}/recall`,
            );
            setMessages((current) =>
                current.map((item) =>
                    item.id === message.id ? response.data : item,
                ),
            );
            setMenuId(null);
        } catch (error) {
            window.alert(
                error.response?.data?.message || "Pesan tidak dapat ditarik.",
            );
        }
    };
    const clearConversation = async () => {
        if (
            !active ||
            !window.confirm(
                `Hapus seluruh isi ${active.name} dari tampilan Anda?`,
            )
        )
            return;
        await api.delete(`/admin/chat/conversations/${active.id}`);
        setMessages([]);
        refresh();
    };
    const muteConversation = async (duration) => {
        if (!active) return;
        const response = await api.post(
            `/admin/chat/conversations/${active.id}/mute`,
            { duration },
        );
        setData((current) => ({
            ...current,
            conversations: current.conversations.map((item) =>
                item.id === active.id ? { ...item, ...response.data } : item,
            ),
        }));
        setMuteMenu(false);
    };

    const rendered = useMemo(() => {
        let previous = "";
        return messages.map((message) => {
            const date = dateLabel(message.created_at);
            const showDate = date !== previous;
            previous = date;
            const mine = Number(message.sender_id) === Number(user?.id);
            return (
                <div key={message.id}>
                    {showDate && (
                        <div className="my-4 flex items-center gap-2">
                            <span className="h-px flex-1 bg-silver-deep/60 dark:bg-white/10" />
                            <span className="rounded-full bg-silver px-2.5 py-1 text-[10px] font-extrabold text-ink-soft dark:bg-white/10 dark:text-white/55">
                                {date}
                            </span>
                            <span className="h-px flex-1 bg-silver-deep/60 dark:bg-white/10" />
                        </div>
                    )}
                    <div
                        className={`group mb-3 flex items-end gap-2 ${mine ? "justify-end" : "justify-start"}`}
                    >
                        {!mine && (
                            <Avatar item={message.sender} size="h-7 w-7" />
                        )}
                        <div
                            className={`relative max-w-[78%] rounded-2xl px-3 py-2 shadow-sm ${mine ? "rounded-br-sm bg-ink text-white dark:bg-gold dark:text-graphite" : "rounded-bl-sm border border-silver-deep/50 bg-white dark:border-white/10 dark:bg-white/8"}`}
                        >
                            {active?.type === "global" && !mine && (
                                <p className="mb-1 text-[10px] font-black text-gold-deep dark:text-gold">
                                    {message.sender?.name}
                                </p>
                            )}
                            {message.recalled_at ? (
                                <p className="whitespace-pre-wrap text-sm italic leading-relaxed opacity-65">
                                    Pesan telah ditarik
                                </p>
                            ) : (
                                <>
                                    {message.attachment && (
                                        <a
                                            className="mb-1 block overflow-hidden rounded-xl"
                                            href={message.attachment.url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            {message.attachment.is_image ? (
                                                <img
                                                    className="max-h-52 w-full object-cover transition hover:scale-[1.02]"
                                                    src={message.attachment.url}
                                                    alt={
                                                        message.attachment.name
                                                    }
                                                />
                                            ) : (
                                                <span
                                                    className={`flex items-center gap-2 rounded-xl p-2.5 text-left ${mine ? "bg-white/10" : "bg-silver dark:bg-white/10"}`}
                                                >
                                                    <FileText
                                                        size={24}
                                                        className="shrink-0"
                                                    />
                                                    <span className="min-w-0">
                                                        <b className="block truncate text-xs">
                                                            {
                                                                message
                                                                    .attachment
                                                                    .name
                                                            }
                                                        </b>
                                                        <small className="opacity-60">
                                                            {Math.max(
                                                                1,
                                                                Math.round(
                                                                    (message
                                                                        .attachment
                                                                        .size ||
                                                                        0) /
                                                                        1024,
                                                                ),
                                                            )}{" "}
                                                            KB · Klik untuk buka
                                                        </small>
                                                    </span>
                                                </span>
                                            )}
                                        </a>
                                    )}
                                    {message.body && (
                                        <p className="whitespace-pre-wrap break-words text-sm leading-relaxed">
                                            {message.body}
                                        </p>
                                    )}
                                </>
                            )}
                            <div
                                className={`mt-1 flex items-center justify-end gap-1 text-[9px] ${mine ? "text-white/60 dark:text-graphite/60" : "text-ink-soft"}`}
                            >
                                <span>{timeLabel(message.created_at)}</span>
                                {mine && <CheckCheck size={12} />}
                            </div>
                            {!message.recalled_at && (
                                <button
                                    type="button"
                                    aria-label="Tindakan pesan"
                                    onClick={() =>
                                        setMenuId(
                                            menuId === message.id
                                                ? null
                                                : message.id,
                                        )
                                    }
                                    className={`absolute top-1 hidden rounded-full p-1 shadow group-hover:block ${mine ? "-left-7 bg-white text-graphite" : "-right-7 bg-white text-graphite"}`}
                                >
                                    <MoreVertical size={14} />
                                </button>
                            )}
                            {menuId === message.id && (
                                <div
                                    className={`absolute top-7 z-10 w-40 overflow-hidden rounded-xl border border-silver-deep bg-white py-1 text-graphite shadow-xl ${mine ? "right-full mr-1" : "left-full ml-1"}`}
                                >
                                    {mine && (
                                        <button
                                            className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-bold hover:bg-silver"
                                            onClick={() =>
                                                recallMessage(message)
                                            }
                                            type="button"
                                        >
                                            <RotateCcw size={14} /> Tarik pesan
                                        </button>
                                    )}
                                    <button
                                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-bold text-red-600 hover:bg-red-50"
                                        onClick={() => removeMessage(message)}
                                        type="button"
                                    >
                                        <Trash2 size={14} /> Hapus untuk saya
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            );
        });
    }, [messages, menuId, active?.type, user?.id]);

    if (!user) return null;
    return (
        <>
            <div className="fixed bottom-5 right-4 z-[60] sm:bottom-6 sm:right-6">
                {open && (
                    <section className="mb-3 flex h-[min(680px,calc(100vh-110px))] w-[calc(100vw-32px)] max-w-[400px] flex-col overflow-hidden rounded-2xl border border-white/70 bg-silver-soft shadow-2xl dark:border-white/10 dark:bg-[#11161c]">
                        <header className="bg-gradient-to-r from-ink to-graphite px-4 pb-3 pt-4 text-white dark:from-[#202833] dark:to-[#151a21]">
                            <div className="flex items-center justify-between gap-2">
                                <div className="flex min-w-0 items-center gap-2">
                                    <span className="grid h-9 w-9 place-items-center rounded-xl bg-white/12">
                                        <MessageCircle size={19} />
                                    </span>
                                    <div className="min-w-0">
                                        <h2 className="truncate text-sm font-black">
                                            Pesan Tim
                                        </h2>
                                        <p className="flex items-center gap-1.5 text-[10px] font-semibold text-white/65">
                                            <span className="h-2 w-2 rounded-full bg-emerald-400" />
                                            {onlineIds.size} pengguna aktif
                                        </p>
                                    </div>
                                </div>
                                <div className="relative flex">
                                    <button
                                        title={
                                            active?.is_muted
                                                ? `Dibisukan: ${active.mute_label}`
                                                : "Bisukan percakapan"
                                        }
                                        className={`rounded-lg p-2 hover:bg-white/10 ${active?.is_muted ? "text-amber-300" : ""}`}
                                        onClick={() => setMuteMenu(!muteMenu)}
                                        type="button"
                                    >
                                        {active?.is_muted ? (
                                            <BellOff size={16} />
                                        ) : (
                                            <Volume2 size={16} />
                                        )}
                                    </button>
                                    <button
                                        title="Hapus chat"
                                        className="rounded-lg p-2 hover:bg-white/10"
                                        onClick={clearConversation}
                                        type="button"
                                    >
                                        <Trash2 size={16} />
                                    </button>
                                    <button
                                        className="rounded-lg p-2 hover:bg-white/10"
                                        onClick={() => setOpen(false)}
                                        type="button"
                                    >
                                        <X size={18} />
                                    </button>
                                    {muteMenu && (
                                        <div className="absolute right-0 top-10 z-30 w-56 overflow-hidden rounded-xl border border-silver-deep bg-white py-1 text-graphite shadow-2xl dark:border-white/10 dark:bg-[#1b222b] dark:text-white">
                                            <p className="border-b border-silver-deep/60 px-3 py-2 text-[10px] font-black uppercase tracking-wide text-ink-soft dark:border-white/10 dark:text-white/45">
                                                {active?.is_muted
                                                    ? active.mute_label
                                                    : "Bisukan notifikasi"}
                                            </p>
                                            {active?.is_muted && (
                                                <button
                                                    className="flex w-full items-center gap-2 px-3 py-2.5 text-left text-xs font-extrabold hover:bg-silver dark:hover:bg-white/10"
                                                    type="button"
                                                    onClick={() =>
                                                        muteConversation(
                                                            "unmute",
                                                        )
                                                    }
                                                >
                                                    <Volume2 size={14} /> Buka
                                                    bisu
                                                </button>
                                            )}
                                            <button
                                                className="flex w-full items-center gap-2 px-3 py-2.5 text-left text-xs font-extrabold hover:bg-silver dark:hover:bg-white/10"
                                                type="button"
                                                onClick={() =>
                                                    muteConversation("8_hours")
                                                }
                                            >
                                                <BellOff size={14} /> Bisukan 8
                                                jam
                                            </button>
                                            <button
                                                className="flex w-full items-center gap-2 px-3 py-2.5 text-left text-xs font-extrabold hover:bg-silver dark:hover:bg-white/10"
                                                type="button"
                                                onClick={() =>
                                                    muteConversation("12_hours")
                                                }
                                            >
                                                <BellOff size={14} /> Bisukan 12
                                                jam
                                            </button>
                                            <button
                                                className="flex w-full items-center gap-2 px-3 py-2.5 text-left text-xs font-extrabold hover:bg-silver dark:hover:bg-white/10"
                                                type="button"
                                                onClick={() =>
                                                    muteConversation("forever")
                                                }
                                            >
                                                <BellOff size={14} /> Sampai
                                                dinyalakan kembali
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                            <div className="relative mt-3 flex gap-2">
                                <button
                                    className="flex min-h-10 flex-1 items-center justify-between rounded-xl bg-white/10 px-3 text-left text-xs font-extrabold hover:bg-white/15"
                                    onClick={() =>
                                        setDestinationMenu(!destinationMenu)
                                    }
                                    type="button"
                                >
                                    <span className="flex min-w-0 items-center gap-2">
                                        {active?.type === "global" ? (
                                            <Globe2 size={15} />
                                        ) : (
                                            <Avatar
                                                item={active}
                                                size="h-5 w-5"
                                            />
                                        )}
                                        <span className="truncate">
                                            {active?.name ?? "Chat Global"}
                                        </span>
                                    </span>
                                    <ChevronDown size={14} />
                                </button>
                                <button
                                    title="Daftar chat"
                                    className={`relative grid h-10 w-10 place-items-center rounded-xl ${tab === "list" ? "bg-gold text-graphite" : "bg-white/10 hover:bg-white/15"}`}
                                    onClick={() =>
                                        setTab(tab === "list" ? "chat" : "list")
                                    }
                                    type="button"
                                >
                                    <Users size={17} />
                                    {unread > 0 && (
                                        <span className="absolute -right-1 -top-1 rounded-full bg-red-600 px-1.5 text-[9px] font-black text-white">
                                            {unread > 99 ? "99+" : unread}
                                        </span>
                                    )}
                                </button>
                                {destinationMenu && (
                                    <div className="absolute left-0 top-11 z-20 w-[calc(100%-48px)] overflow-hidden rounded-xl border border-silver-deep bg-white py-1 text-graphite shadow-xl">
                                        <button
                                            className="flex w-full items-center gap-2 px-3 py-2.5 text-left text-xs font-extrabold hover:bg-silver"
                                            type="button"
                                            onClick={() => {
                                                if (global)
                                                    selectConversation(global);
                                                setDestinationMenu(false);
                                            }}
                                        >
                                            <Globe2 size={15} /> Chat Global
                                        </button>
                                        <button
                                            className="flex w-full items-center gap-2 px-3 py-2.5 text-left text-xs font-extrabold hover:bg-silver"
                                            type="button"
                                            onClick={() => {
                                                setDestinationMenu(false);
                                                setPicker(true);
                                            }}
                                        >
                                            <UserRound size={15} /> Pilih
                                            User...
                                        </button>
                                    </div>
                                )}
                            </div>
                        </header>
                        {unreadSources.length > 0 && (
                            <div className="flex shrink-0 gap-2 overflow-x-auto border-b border-red-100 bg-red-50 px-3 py-2 dark:border-red-500/15 dark:bg-red-500/8">
                                <span className="shrink-0 self-center text-[10px] font-black uppercase tracking-wide text-red-600 dark:text-red-300">
                                    Pesan baru:
                                </span>
                                {unreadSources.map((source) => (
                                    <button
                                        key={source.id}
                                        type="button"
                                        onClick={() =>
                                            selectConversation(source)
                                        }
                                        className="flex shrink-0 items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-[10px] font-extrabold text-graphite shadow-sm hover:bg-red-100 dark:bg-white/10 dark:text-white"
                                    >
                                        {source.type === "global" ? (
                                            <Globe2 size={12} />
                                        ) : (
                                            <UserRound size={12} />
                                        )}
                                        {source.name}
                                        <b className="rounded-full bg-red-600 px-1.5 text-white">
                                            {source.unread_count}
                                        </b>
                                    </button>
                                ))}
                            </div>
                        )}
                        {tab === "list" ? (
                            <div className="flex-1 overflow-y-auto p-3">
                                <div className="mb-2 flex items-center gap-2 px-1">
                                    <button
                                        className="rounded-lg p-1.5 hover:bg-silver dark:hover:bg-white/10"
                                        onClick={() => setTab("chat")}
                                        type="button"
                                    >
                                        <ArrowLeft size={17} />
                                    </button>
                                    <h3 className="text-sm font-black">
                                        Daftar chat
                                    </h3>
                                </div>
                                <button
                                    className="mb-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-gold px-4 text-sm font-black text-graphite shadow-sm hover:bg-gold-deep"
                                    type="button"
                                    onClick={() => setPicker(true)}
                                >
                                    <UserRound size={17} /> Chat Baru / Pilih
                                    User
                                </button>
                                {data.conversations.map((conversation) => (
                                    <button
                                        key={conversation.id}
                                        type="button"
                                        onClick={() =>
                                            selectConversation(conversation)
                                        }
                                        className="mb-1 flex w-full items-center gap-3 rounded-xl p-3 text-left hover:bg-white dark:hover:bg-white/8"
                                    >
                                        {conversation.type === "global" ? (
                                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-100 text-blue-700">
                                                <Globe2 size={18} />
                                            </span>
                                        ) : (
                                            <span className="relative shrink-0">
                                                <Avatar
                                                    item={conversation}
                                                    size="h-10 w-10"
                                                />
                                                <span
                                                    className={`absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-[#11161c] ${onlineIds.has(Number(conversation.other_user_id)) ? "bg-emerald-500" : "bg-slate-300"}`}
                                                />
                                            </span>
                                        )}
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-center justify-between gap-2">
                                                <span className="flex min-w-0 items-center gap-1.5">
                                                    <b className="truncate text-sm">
                                                        {conversation.name}
                                                    </b>
                                                    {conversation.is_muted && (
                                                        <BellOff
                                                            size={12}
                                                            className="shrink-0 text-amber-600"
                                                        />
                                                    )}
                                                    {conversation.type ===
                                                        "direct" && (
                                                        <OnlineDot
                                                            online={onlineIds.has(
                                                                Number(
                                                                    conversation.other_user_id,
                                                                ),
                                                            )}
                                                        />
                                                    )}
                                                </span>
                                                <small className="shrink-0 text-[9px] text-ink-soft">
                                                    {timeLabel(
                                                        conversation
                                                            .last_message
                                                            ?.created_at,
                                                    )}
                                                </small>
                                            </span>
                                            <span className="flex items-center justify-between gap-2">
                                                <span className="truncate text-xs text-ink-soft dark:text-white/50">
                                                    {conversation.last_message
                                                        ?.recalled_at
                                                        ? "Pesan telah ditarik"
                                                        : conversation
                                                              .last_message
                                                              ?.body ||
                                                          (conversation
                                                              .last_message
                                                              ?.attachment
                                                              ? `📎 ${conversation.last_message.attachment.name}`
                                                              : null) ||
                                                          "Belum ada pesan"}
                                                </span>
                                                {conversation.unread_count >
                                                    0 && (
                                                    <b className="rounded-full bg-red-600 px-2 py-0.5 text-[9px] text-white">
                                                        {
                                                            conversation.unread_count
                                                        }
                                                    </b>
                                                )}
                                            </span>
                                        </span>
                                    </button>
                                ))}
                            </div>
                        ) : (
                            <>
                                <div className="flex-1 overflow-y-auto px-3 py-2">
                                    {hasMore && (
                                        <div className="py-2 text-center">
                                            <button
                                                disabled={loading}
                                                onClick={() =>
                                                    loadMessages(
                                                        active.id,
                                                        messages[0]?.id,
                                                    )
                                                }
                                                className="rounded-full bg-white px-3 py-1.5 text-[10px] font-extrabold shadow-sm disabled:opacity-50 dark:bg-white/10"
                                                type="button"
                                            >
                                                {loading
                                                    ? "Memuat..."
                                                    : "Muat pesan lama"}
                                            </button>
                                        </div>
                                    )}
                                    {openingUser ? (
                                        <div className="grid h-full place-items-center text-center">
                                            <div>
                                                <LoaderCircle className="mx-auto animate-spin text-gold-deep" />
                                                <p className="mt-3 text-sm font-black">
                                                    Membuka chat dengan{" "}
                                                    {openingUser.name}
                                                </p>
                                                <p className="mt-1 text-xs font-semibold text-ink-soft">
                                                    Menyiapkan percakapan
                                                    baru...
                                                </p>
                                            </div>
                                        </div>
                                    ) : loading && !messages.length ? (
                                        <div className="grid h-full place-items-center">
                                            <LoaderCircle className="animate-spin text-ink-soft" />
                                        </div>
                                    ) : !messages.length ? (
                                        <div className="grid h-full place-items-center text-center">
                                            <div>
                                                <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-white text-ink-soft shadow-sm dark:bg-white/8">
                                                    <MessageCircle size={24} />
                                                </span>
                                                <p className="mt-3 text-sm font-black">
                                                    Mulai percakapan
                                                </p>
                                                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/45">
                                                    Kirim pesan pertama Anda di
                                                    sini.
                                                </p>
                                            </div>
                                        </div>
                                    ) : (
                                        rendered
                                    )}
                                    <div ref={bottomRef} />
                                </div>
                                <div className="border-t border-silver-deep/60 bg-white p-3 dark:border-white/10 dark:bg-[#151a21]">
                                    {attachment && (
                                        <div className="mb-2 flex items-center gap-2 rounded-xl bg-silver-soft p-2 dark:bg-white/8">
                                            <FileText
                                                size={20}
                                                className="shrink-0 text-gold-deep"
                                            />
                                            <span className="min-w-0 flex-1">
                                                <b className="block truncate text-xs">
                                                    {attachment.name}
                                                </b>
                                                <small className="text-[10px] text-ink-soft">
                                                    {Math.max(
                                                        1,
                                                        Math.round(
                                                            attachment.size /
                                                                1024,
                                                        ),
                                                    )}{" "}
                                                    KB
                                                </small>
                                            </span>
                                            <button
                                                className="rounded-lg p-1 hover:bg-silver dark:hover:bg-white/10"
                                                type="button"
                                                onClick={() =>
                                                    setAttachment(null)
                                                }
                                            >
                                                <X size={15} />
                                            </button>
                                        </div>
                                    )}
                                    {sendError && (
                                        <p className="mb-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-bold text-red-600 dark:bg-red-500/10 dark:text-red-300">
                                            {sendError}
                                        </p>
                                    )}
                                    <form
                                        className="flex items-end gap-2"
                                        onSubmit={send}
                                    >
                                        <input
                                            ref={fileRef}
                                            className="hidden"
                                            type="file"
                                            onChange={(e) => {
                                                const file =
                                                    e.target.files?.[0];
                                                if (file) setAttachment(file);
                                                e.target.value = "";
                                            }}
                                        />
                                        <button
                                            title="Lampirkan gambar atau file (maks. 15 MB)"
                                            className="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-silver-deep/70 text-ink-soft hover:border-gold hover:text-gold-deep dark:border-white/10"
                                            type="button"
                                            onClick={() =>
                                                fileRef.current?.click()
                                            }
                                        >
                                            <Paperclip size={18} />
                                        </button>
                                        <textarea
                                            rows={1}
                                            value={body}
                                            onChange={(e) =>
                                                setBody(e.target.value)
                                            }
                                            onKeyDown={(e) => {
                                                if (
                                                    e.key === "Enter" &&
                                                    !e.shiftKey
                                                ) {
                                                    e.preventDefault();
                                                    send(e);
                                                }
                                            }}
                                            placeholder="Tulis pesan..."
                                            className="max-h-28 min-h-11 flex-1 resize-none rounded-xl border border-silver-deep/70 bg-silver-soft px-3 py-3 text-sm outline-none focus:border-gold dark:border-white/10 dark:bg-white/6"
                                        />
                                        <button
                                            disabled={
                                                (!body.trim() && !attachment) ||
                                                !active?.id ||
                                                sending
                                            }
                                            className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gold text-graphite shadow disabled:opacity-40"
                                            type="submit"
                                        >
                                            {sending ? (
                                                <LoaderCircle
                                                    size={17}
                                                    className="animate-spin"
                                                />
                                            ) : (
                                                <Send size={17} />
                                            )}
                                        </button>
                                    </form>
                                </div>
                            </>
                        )}
                    </section>
                )}
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    className="relative ml-auto flex min-h-14 items-center gap-2 rounded-2xl bg-gradient-to-r from-gold to-gold-deep px-5 font-black text-graphite shadow-[0_14px_35px_rgba(153,116,34,.35)] transition hover:-translate-y-0.5"
                >
                    <MessageCircle size={21} />
                    <span>{open ? "Tutup" : "Chat"}</span>
                    {unread > 0 && (
                        <span className="absolute -right-2 -top-2 rounded-full bg-red-600 px-2 py-0.5 text-[10px] text-white ring-2 ring-white">
                            {unread > 99 ? "99+" : unread}
                        </span>
                    )}
                </button>
            </div>
            {picker && (
                <UserPicker
                    users={data.users}
                    onlineIds={onlineIds}
                    onClose={() => setPicker(false)}
                    onSelect={chooseUser}
                />
            )}
        </>
    );
}
