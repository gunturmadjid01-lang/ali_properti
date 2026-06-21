import {
    Banknote,
    BarChart3,
    BellRing,
    ClipboardCheck,
    Compass,
    FileCheck2,
    FileText,
    KanbanSquare,
    KeyRound,
    LayoutDashboard,
    Megaphone,
    MessageCircle,
    MessagesSquare,
    ReceiptText,
    Share2,
    Target,
    UserPlus,
    Users,
    WalletCards,
} from "lucide-react";

const supervisorMarketingSidebar = [
    {
        title: "Menu Supervisor Marketing",
        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                link: "/admin/dashboard",
            },
            {
                title: "Tahap 1 - Lead",
                icon: Users,
                items: [
                    {
                        title: "Sumber Lead",
                        icon: Share2,
                        link: "/admin/marketing/sumber-lead",
                    },
                    {
                        title: "Data Customer",
                        icon: UserPlus,
                        link: "/admin/marketing/calon-konsumen",
                    },
                    {
                        title: "Laporan Lead",
                        icon: BarChart3,
                        link: "/admin/marketing/laporan-lead",
                    },
                ],
            },
            {
                title: "Tahap 2 - Follow Up",
                icon: MessageCircle,
                items: [
                    {
                        title: "Jadwal Survey",
                        icon: Compass,
                        link: "/admin/marketing/jadwal-survey",
                    },
                    {
                        title: "Follow Up Customer",
                        icon: MessageCircle,
                        link: "/admin/marketing/jejak-follow-up",
                    },
                ],
            },
            {
                title: "Tahap 3 - Transaksi",
                icon: ReceiptText,
                items: [
                    {
                        title: "SPR",
                        icon: FileText,
                        link: "/admin/marketing/spr",
                    },
                    {
                        title: "Pembayaran SPR",
                        icon: Banknote,
                        items: [
                            {
                                title: "Booking Fee",
                                icon: Banknote,
                                link: "/admin/marketing/pembayaran-spr?tab=booking",
                            },
                            {
                                title: "Uang Muka",
                                icon: Banknote,
                                link: "/admin/marketing/pembayaran-spr?tab=dp",
                            },
                        ],
                    },
                    {
                        title: "Cash",
                        icon: Banknote,
                        link: "/admin/marketing/transaksi-pembelian/cash",
                    },
                    {
                        title: "KPR Bank",
                        icon: ClipboardCheck,
                        items: [
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
                ],
            },
            {
                title: "Stock & Pricing",
                icon: Target,
                items: [
                    {
                        title: "Unit Available / Stock Unit",
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
            {
                title: "Operasional Marketing",
                icon: KanbanSquare,
                items: [
                    {
                        title: "Pipeline Marketing",
                        icon: KanbanSquare,
                        link: "/admin/marketing/operasional/pipeline",
                    },
                    {
                        title: "Campaign & Promosi",
                        icon: Megaphone,
                        link: "/admin/marketing/operasional/campaign",
                    },
                    {
                        title: "Reminder Follow Up",
                        icon: BellRing,
                        link: "/admin/marketing/operasional/reminder",
                    },
                    {
                        title: "Validasi Berkas",
                        icon: FileCheck2,
                        link: "/admin/marketing/operasional/dokumen",
                    },
                    {
                        title: "Template Komunikasi",
                        icon: MessagesSquare,
                        link: "/admin/marketing/operasional/template",
                    },
                ],
            },
            {
                title: "Kontrol Marketing",
                icon: BarChart3,
                items: [
                    {
                        title: "Distribusi Lead",
                        icon: Share2,
                        link: "/admin/marketing/tools/distribusi-lead",
                    },
                    {
                        title: "Monitoring Aktivitas Marketing",
                        icon: BellRing,
                        link: "/admin/marketing/tools/monitoring-aktivitas",
                    },
                    {
                        title: "Approval Diskon / Promo",
                        icon: ReceiptText,
                        link: "/admin/marketing/tools/approval-diskon",
                    },
                    {
                        title: "Dashboard Performa",
                        icon: BarChart3,
                        link: "/admin/marketing/operasional/dashboard",
                    },
                    {
                        title: "Laporan Pipeline",
                        icon: KanbanSquare,
                        link: "/admin/marketing/laporan-pipeline",
                    },
                    {
                        title: "Tagihan & Kwitansi",
                        icon: WalletCards,
                        link: "/admin/marketing/operasional/piutang",
                    },
                    {
                        title: "Target KPI & Komisi",
                        icon: Target,
                        link: "/admin/marketing/operasional/target-komisi",
                    },
                    {
                        title: "Target per Marketing",
                        icon: Target,
                        link: "/admin/marketing/operasional/target-komisi",
                    },
                    {
                        title: "Evaluasi Campaign",
                        icon: Megaphone,
                        link: "/admin/marketing/operasional/campaign",
                    },
                    {
                        title: "Aging Lead",
                        icon: KanbanSquare,
                        link: "/admin/marketing/tools/aging-lead",
                    },
                    {
                        title: "Leaderboard Sales",
                        icon: BarChart3,
                        link: "/admin/marketing/tools/leaderboard-sales",
                    },
                ],
            },
            {
                title: "Progress Pembangunan",
                icon: ClipboardCheck,
                link: "/admin/unit-rumah",
            },
        ],
    },
];

export default supervisorMarketingSidebar;
