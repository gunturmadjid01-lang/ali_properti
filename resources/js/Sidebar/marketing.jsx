import {
    Banknote,
    BellRing,
    ClipboardCheck,
    Compass,
    FileText,
    KeyRound,
    LayoutDashboard,
    MessageCircle,
    ReceiptText,
    Target,
    UserPlus,
    Users,
} from "lucide-react";

const marketingSidebar = [
    {
        title: "Menu Marketing",
        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                link: "/admin/dashboard",
            },
            {
                title: "Lead & Customer",
                icon: Users,
                items: [
                    {
                        title: "Data Customer",
                        icon: UserPlus,
                        link: "/admin/marketing/calon-konsumen",
                    },
                    {
                        title: "Follow Up Customer",
                        icon: MessageCircle,
                        link: "/admin/marketing/jejak-follow-up",
                    },
                    {
                        title: "Jadwal Survey",
                        icon: Compass,
                        link: "/admin/marketing/jadwal-survey",
                    },
                    {
                        title: "Reminder Follow Up",
                        icon: BellRing,
                        link: "/admin/marketing/operasional/reminder",
                        badgeKey: "reminder_follow_up",
                    },
                ],
            },
            {
                title: "Transaksi Customer",
                icon: ReceiptText,
                items: [
                    {
                        title: "SPR",
                        icon: FileText,
                        link: "/admin/marketing/spr",
                    },
                    {
                        title: "Pembayaran Awal",
                        icon: Banknote,
                        link: "/admin/marketing/pembayaran-spr",
                    },
                    {
                        title: "Pengajuan KPR",
                        icon: ClipboardCheck,
                        link: "/admin/kpr",
                    },
                    {
                        title: "Akad",
                        icon: FileText,
                        link: "/admin/kpr/proses/akad",
                    },
                    {
                        title: "Serah Terima",
                        icon: KeyRound,
                        link: "/admin/kpr/proses/serah_terima",
                    },
                ],
            },
            {
                title: "Tools Closing",
                icon: Target,
                items: [
                    {
                        title: "Unit Available",
                        icon: ClipboardCheck,
                        link: "/admin/marketing/tools/unit-stock",
                    },
                    {
                        title: "Pricelist Aktif",
                        icon: ReceiptText,
                        link: "/admin/marketing/tools/pricelist",
                    },
                    {
                        title: "Simulasi Pembayaran",
                        icon: Banknote,
                        link: "/admin/marketing/tools/simulasi-pembayaran",
                    },
                ],
            },
        ],
    },
];

export default marketingSidebar;
