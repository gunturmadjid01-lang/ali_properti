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
                    },
                    {
                        title: "Laporan Lead",
                        icon: BarChart3,
                        link: "/admin/marketing/laporan-lead",
                    },
                    {
                        title: "Pipeline Semua Marketing",
                        icon: KanbanSquare,
                        link: "/admin/marketing/operasional/pipeline",
                    },
                    {
                        title: "Campaign & Promosi",
                        icon: Megaphone,
                        link: "/admin/marketing/operasional/campaign",
                    },
                    {
                        title: "Validasi Berkas",
                        icon: FileCheck2,
                        link: "/admin/marketing/operasional/dokumen",
                    },
                    {
                        title: "Distribusi Lead",
                        icon: Share2,
                        link: "/admin/marketing/tools/distribusi-lead",
                    },
                    {
                        title: "Monitoring Aktivitas",
                        icon: BellRing,
                        link: "/admin/marketing/tools/monitoring-aktivitas",
                    },
                    {
                        title: "Target KPI & Komisi",
                        icon: Target,
                        link: "/admin/marketing/operasional/target-komisi",
                    },
                    {
                        title: "Leaderboard Sales",
                        icon: BarChart3,
                        link: "/admin/marketing/tools/leaderboard-sales",
                    },
                    {
                        title: "Tagihan & Kwitansi",
                        icon: WalletCards,
                        link: "/admin/marketing/operasional/piutang",
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
