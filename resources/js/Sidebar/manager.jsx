import {
    BarChart3,
    BellRing,
    ClipboardCheck,
    CalendarClock,
    FileCheck2,
    FileText,
    KanbanSquare,
    LayoutDashboard,
    Megaphone,
    ReceiptText,
    RotateCcw,
    Share2,
    ShieldCheck,
    Target,
    TrendingUp,
    WalletCards,
} from "lucide-react";

const managerSidebar = [
    {
        title: "Menu Manager",
        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                link: "/admin/dashboard",
            },
            {
                title: "Approval",
                icon: ClipboardCheck,
                items: [
                    {
                        title: "Approval SPK",
                        icon: FileText,
                        link: "/admin/spk-kontraktor/approval",
                    },
                    {
                        title: "Pencairan Pembayaran SPK",
                        icon: WalletCards,
                        link: "/admin/spk-kontraktor/pencairan",
                    },
                    {
                        title: "Approval SPR",
                        icon: ReceiptText,
                        link: "/admin/marketing/spr",
                    },
                    {
                        title: "Approval Refund SPR",
                        icon: RotateCcw,
                        link: "/admin/refund-spr/approval",
                    },
                    {
                        title: "Approval Pengeluaran",
                        icon: ShieldCheck,
                        items: [
                            {
                                title: "Daftar Approval",
                                icon: ShieldCheck,
                                link: "/admin/approval",
                            },
                        ],
                    },
                    {
                        title: "Permintaan Pembelian Gudang",
                        icon: ReceiptText,
                        link: "/admin/daftar-permintaan-pembelian",
                    },
                ],
            },
            {
                title: "Manajemen Marketing",
                icon: BarChart3,
                items: [
                    {
                        title: "Sumber Lead",
                        icon: Share2,
                        link: "/admin/marketing/sumber-lead",
                        roles: ["manager", "supervisor_marketing"],
                        permission: "marketing.lead-source.manage",
                    },
                    {
                        title: "Laporan Lead",
                        icon: BarChart3,
                        link: "/admin/marketing/laporan-lead",
                        roles: ["owner", "manager"],
                        permission: "marketing.lead-report.view",
                    },
                    {
                        title: "Pipeline Semua Marketing",
                        icon: KanbanSquare,
                        link: "/admin/marketing/operasional/pipeline",
                        roles: ["owner", "manager"],
                        permission: "marketing.pipeline-report.view",
                    },
                    {
                        title: "Campaign & Promosi",
                        icon: Megaphone,
                        link: "/admin/marketing/operasional/campaign",
                        roles: ["pengawas"],
                        permission: "marketing.campaign.manage",
                    },
                    {
                        title: "Validasi Berkas",
                        icon: FileCheck2,
                        link: "/admin/marketing/operasional/dokumen",
                        roles: ["supervisor_marketing"],
                        permission: "marketing.document-review.manage",
                    },
                    {
                        title: "Distribusi Lead",
                        icon: Share2,
                        link: "/admin/marketing/tools/distribusi-lead",
                        roles: ["supervisor_marketing"],
                        permission: "marketing.lead-distribution.manage",
                    },
                    {
                        title: "Monitoring Aktivitas",
                        icon: BellRing,
                        link: "/admin/marketing/tools/monitoring-aktivitas",
                        roles: ["owner", "manager", "supervisor_marketing"],
                        permission: "marketing.activity.view",
                    },
                    {
                        title: "Target KPI & Komisi",
                        icon: Target,
                        link: "/admin/marketing/operasional/target-komisi",
                        roles: ["manager", "supervisor_marketing"],
                        permission: "marketing.target-commission.manage",
                    },
                    {
                        title: "Leaderboard Sales",
                        icon: BarChart3,
                        link: "/admin/marketing/tools/leaderboard-sales",
                        roles: ["owner", "manager", "supervisor_marketing"],
                        permission: "marketing.leaderboard.view",
                    },
                    {
                        title: "Tagihan & Kwitansi",
                        icon: WalletCards,
                        link: "/admin/marketing/operasional/piutang",
                        roles: ["owner", "manager"],
                        permission: "marketing.receivable.view",
                    },
                ],
            },
            {
                title: "Manajemen Proyek",
                icon: FileText,
                items: [
                    {
                        title: "Progress Pembangunan",
                        icon: TrendingUp,
                        link: "/admin/progress-pembangunan",
                    },
                    {
                        title: "Laporan Lapangan",
                        icon: FileText,
                        link: "/admin/laporan-lapangan",
                    },
                    {
                        title: "Kontrol Kualitas",
                        icon: ShieldCheck,
                        link: "/admin/kontrol-kualitas",
                    },
                    {
                        title: "Jadwal Lapangan",
                        icon: CalendarClock,
                        link: "/admin/jadwal-lapangan",
                    },
                ],
            },
        ],
    },
];

export default managerSidebar;
