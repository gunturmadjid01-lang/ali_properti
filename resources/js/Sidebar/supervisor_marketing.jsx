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
                        permission: "marketing.lead-source.manage",
                    },
                    {
                        title: "Data Customer",
                        icon: UserPlus,
                        link: "/admin/marketing/calon-konsumen",
                        permission: "customer.view",
                    },
                    {
                        title: "Laporan Lead",
                        icon: BarChart3,
                        link: "/admin/marketing/laporan-lead",
                        permission: "marketing.lead-report.view",
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
                        permission: "marketing.reminder.manage",
                    },
                    {
                        title: "Follow Up Customer",
                        icon: MessageCircle,
                        link: "/admin/marketing/jejak-follow-up",
                        permission: "customer.follow-up",
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
                        permission: "booking.view",
                    },
                    {
                        title: "Pembayaran SPR",
                        icon: Banknote,
                        items: [
                            {
                                title: "Booking Fee",
                                icon: Banknote,
                                link: "/admin/marketing/pembayaran-spr?tab=booking",
                                permission: "spr-payment.view",
                            },
                            {
                                title: "Uang Muka",
                                icon: Banknote,
                                link: "/admin/marketing/pembayaran-spr?tab=dp",
                                permission: "spr-payment.view",
                            },
                        ],
                    },
                    {
                        title: "Cash",
                        icon: Banknote,
                        link: "/admin/marketing/transaksi-pembelian/cash",
                        permission: "booking.view",
                    },
                    {
                        title: "KPR Bank",
                        icon: ClipboardCheck,
                        items: [
                            {
                                title: "Pengajuan KPR",
                                icon: ClipboardCheck,
                                link: "/admin/kpr",
                                permission: "kpr.view",
                            },
                            {
                                title: "Akad",
                                icon: FileText,
                                link: "/admin/kpr/proses/akad",
                                permission: "kpr.view",
                            },
                            {
                                title: "Serah Terima",
                                icon: KeyRound,
                                link: "/admin/kpr/proses/serah_terima",
                                permission: "kpr.view",
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
                        permission: "unit-stock.view",
                    },
                    {
                        title: "Pricelist Aktif",
                        icon: ReceiptText,
                        link: "/admin/marketing/tools/pricelist",
                        permission: "pricelist.view",
                    },
                    {
                        title: "Simulasi Pembayaran",
                        icon: Banknote,
                        link: "/admin/marketing/tools/simulasi-pembayaran",
                        permission: "payment-simulation.view",
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
                        permission: "marketing.pipeline-report.view",
                    },
                    {
                        title: "Campaign & Promosi",
                        icon: Megaphone,
                        link: "/admin/marketing/operasional/campaign",
                        permission: "marketing.campaign.manage",
                    },
                    {
                        title: "Reminder Follow Up",
                        icon: BellRing,
                        link: "/admin/marketing/operasional/reminder",
                        permission: "marketing.reminder.manage",
                    },
                    {
                        title: "Validasi Berkas",
                        icon: FileCheck2,
                        link: "/admin/marketing/operasional/dokumen",
                        permission: "marketing.document-review.manage",
                    },
                    {
                        title: "Template Komunikasi",
                        icon: MessagesSquare,
                        link: "/admin/marketing/operasional/template",
                        permission: "marketing.template.manage",
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
                        permission: "marketing.lead-distribution.manage",
                    },
                    {
                        title: "Monitoring Aktivitas Marketing",
                        icon: BellRing,
                        link: "/admin/marketing/tools/monitoring-aktivitas",
                        permission: "marketing.activity.view",
                    },
                    {
                        title: "Dashboard Performa",
                        icon: BarChart3,
                        link: "/admin/marketing/operasional/dashboard",
                        permission: "marketing.performance.view",
                    },
                    {
                        title: "Laporan Pipeline",
                        icon: KanbanSquare,
                        link: "/admin/marketing/laporan-pipeline",
                        permission: "marketing.pipeline-report.view",
                    },
                    {
                        title: "Tagihan & Kwitansi",
                        icon: WalletCards,
                        link: "/admin/marketing/operasional/piutang",
                        permission: "marketing.receivable.view",
                    },
                    {
                        title: "Target KPI & Komisi",
                        icon: Target,
                        link: "/admin/marketing/operasional/target-komisi",
                        permission: "marketing.target-commission.manage",
                    },
                    {
                        title: "Aging Lead",
                        icon: KanbanSquare,
                        link: "/admin/marketing/tools/aging-lead",
                        permission: "marketing.performance.view",
                    },
                    {
                        title: "Leaderboard Sales",
                        icon: BarChart3,
                        link: "/admin/marketing/tools/leaderboard-sales",
                        permission: "marketing.leaderboard.view",
                    },
                ],
            },
                    {
                        title: "Progress Pembangunan",
                        icon: ClipboardCheck,
                        link: "/admin/unit-rumah",
                        permission: "progress.view",
                    },
        ],
    },
];

export default supervisorMarketingSidebar;
