import {
    Activity,
    BadgeCheck,
    Banknote,
    BarChart3,
    BellRing,
    Boxes,
    Building2,
    CalendarClock,
    ClipboardCheck,
    FileCheck2,
    FileText,
    Home,
    KanbanSquare,
    LayoutDashboard,
    Megaphone,
    ReceiptText,
    RotateCcw,
    Scale,
    ShieldCheck,
    Share2,
    Target,
    Users,
    WalletCards,
    Library,
    Wrench,
} from "lucide-react";

const dashboardItem = {
    title: "Dashboard",
    icon: LayoutDashboard,
    link: "/admin/dashboard",
};

const approvalItems = [
    {
        title: "Daftar Approval",
        icon: ClipboardCheck,
        link: "/admin/approval",
        permission: "approval.view",
    },
    {
        title: "Permintaan Pembelian Gudang",
        icon: Boxes,
        link: "/admin/daftar-permintaan-pembelian",
        permission: "material-purchase.view",
    },
    {
        title: "Approval SPK",
        icon: FileText,
        link: "/admin/spk-kontraktor/approval",
        permission: "spk-kontraktor.view",
    },
    {
        title: "Approval SPR",
        icon: FileText,
        link: "/admin/marketing/spr",
        permission: "booking.view",
    },
    {
        title: "Approval Refund SPR",
        icon: RotateCcw,
        link: "/admin/refund-spr/approval",
        permission: "refund-spr.view",
    },
    {
        title: "Approval Material",
        icon: Boxes,
        link: "/admin/permintaan-barang",
        permission: "material-request.view",
    },
    {
        title: "Setting Approval",
        icon: BadgeCheck,
        link: "/admin/approval/settings",
        permission: "approval.settings",
    },
];

const marketingLeadAndReportItems = [
    {
        title: "Sumber Lead",
        icon: Share2,
        link: "/admin/marketing/sumber-lead",
        permission: "marketing.lead-source.manage",
    },
    {
        title: "Laporan Lead",
        icon: BarChart3,
        link: "/admin/marketing/laporan-lead",
        permission: "marketing.lead-report.view",
    },
    {
        title: "Pipeline Semua Marketing",
        icon: KanbanSquare,
        link: "/admin/marketing/operasional/pipeline",
        permission: "marketing.pipeline-report.view",
    },
    {
        title: "Laporan Pipeline",
        icon: KanbanSquare,
        link: "/admin/marketing/laporan-pipeline",
        permission: "marketing.pipeline-report.view",
    },
];

const marketingOperationsItems = [
    {
        title: "Campaign & Promosi",
        icon: Megaphone,
        link: "/admin/marketing/operasional/campaign",
        permission: "marketing.campaign.manage",
    },
    {
        title: "Validasi Berkas",
        icon: FileCheck2,
        link: "/admin/marketing/operasional/dokumen",
        permission: "marketing.document-review.manage",
    },
    {
        title: "Distribusi Lead",
        icon: Share2,
        link: "/admin/marketing/tools/distribusi-lead",
        permission: "marketing.lead-distribution.manage",
    },
    {
        title: "Target KPI & Komisi",
        icon: Target,
        link: "/admin/marketing/operasional/target-komisi",
        permission: "marketing.target-commission.manage",
    },
];

const marketingAnalyticsItems = [
    {
        title: "Monitoring Aktivitas",
        icon: BellRing,
        link: "/admin/marketing/tools/monitoring-aktivitas",
        permission: "marketing.activity.view",
    },
    {
        title: "Leaderboard Sales",
        icon: BarChart3,
        link: "/admin/marketing/tools/leaderboard-sales",
        permission: "marketing.leaderboard.view",
    },
    {
        title: "Tagihan & Kwitansi",
        icon: WalletCards,
        link: "/admin/marketing/operasional/piutang",
        permission: "marketing.receivable.view",
    },
];

const projectUnitItems = [
    {
        title: "Kapling / Unit",
        icon: Home,
        link: "/admin/unit-rumah",
    },
    {
        title: "Progress Pembangunan",
        icon: Boxes,
        link: "/admin/progress-pembangunan",
    },
];

const projectContractItems = [
    {
        title: "Kontraktor",
        icon: Users,
        link: "/admin/kontraktor",
    },
    {
        title: "SPK Kontraktor",
        icon: FileText,
        items: [
            {
                title: "Input SPK",
                icon: FileText,
                link: "/admin/spk-kontraktor",
                exact: true,
            },
            {
                title: "Pencairan Pembayaran SPK",
                icon: ReceiptText,
                link: "/admin/spk-kontraktor/pencairan",
            },
        ],
    },
];

const projectMonitoringItems = [
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
];

const financeItems = [
    {
        title: "Dashboard Keuangan",
        icon: WalletCards,
        link: "/admin/keuangan/dashboard",
    },
    {
        title: "Input Kas Masuk / Keluar",
        icon: ReceiptText,
        link: "/admin/keuangan/transaksi-kas-bank",
    },
    {
        title: "Jurnal Umum",
        icon: FileText,
        link: "/admin/keuangan/jurnal-umum",
    },
    {
        title: "Buku Besar",
        icon: Library,
        link: "/admin/keuangan/buku-besar",
    },
    {
        title: "Neraca Saldo",
        icon: Scale,
        link: "/admin/keuangan/neraca-saldo",
    },
    {
        title: "Laba Rugi",
        icon: BarChart3,
        link: "/admin/keuangan/laba-rugi",
    },
    {
        title: "Neraca",
        icon: Scale,
        link: "/admin/keuangan/neraca",
    },
    {
        title: "Arus Kas",
        icon: Activity,
        link: "/admin/keuangan/arus-kas",
    },
    {
        title: "Piutang Pelanggan",
        icon: ReceiptText,
        link: "/admin/keuangan/piutang",
    },
    {
        title: "Hutang Supplier & Kontraktor",
        icon: Banknote,
        link: "/admin/keuangan/hutang",
    },
];

const masterDataItems = [
    {
        title: "Manajemen User",
        icon: Users,
        items: [
            {
                title: "Users",
                icon: Users,
                link: "/admin/management/user",
            },
            {
                title: "Role Permission",
                icon: ShieldCheck,
                link: "/admin/management/role-permission",
            },
        ],
    },
    {
        title: "Profil Perusahaan",
        icon: Building2,
        link: "/admin/management/cabang-perusahaan",
    },
    {
        title: "Setting Perumahan",
        icon: Building2,
        items: [
            {
                title: "Perumahan",
                icon: Building2,
                link: "/admin/management/perumahan",
            },
            {
                title: "Master Dokument",
                icon: FileText,
                link: "/admin/management/dokumen-legalitas-rumah",
            },
            {
                title: "Kelompok HPP Perumahan",
                icon: FileCheck2,
                link: "/admin/management/kelompok-hpp",
            },
        ],
    },
    {
        title: "Dokumen Pelanggan",
        icon: FileText,
        link: "/admin/management/master-dokumen-customer",
    },
    {
        title: "Manajemen Bank",
        icon: Banknote,
        items: [
            {
                title: "Master Bank Perusahaan",
                icon: Building2,
                link: "/admin/management/master-bank",
            },
            {
                title: "Mutasi & Saldo Rekening",
                icon: ReceiptText,
                link: "/admin/rekening-bank",
            },
            {
                title: "Bank Kredit",
                icon: Banknote,
                link: "/admin/management/bank-kredit",
            },
        ],
    },
    {
        title: "Tipe Pemasukan / Pengeluaran",
        icon: Banknote,
        link: "/admin/management/tipe-post",
    },
];

const warehouseItems = [
    {
        title: "Gudang",
        icon: Building2,
        link: "/admin/gudang",
    },
    {
        title: "Master Material",
        icon: Boxes,
        link: "/admin/master-material",
    },
    {
        title: "Harga Material",
        icon: Banknote,
        link: "/admin/harga-material",
    },
    {
        title: "Stok Material",
        icon: Boxes,
        link: "/admin/stok-material",
    },
    {
        title: "Stok Opname",
        icon: Boxes,
        link: "/admin/stok-material",
    },
    {
        title: "Permintaan Barang",
        icon: ClipboardCheck,
        link: "/admin/permintaan-barang",
    },
    {
        title: "Mutasi Gudang",
        icon: Boxes,
        link: "/admin/logistik",
    },
    {
        title: "Pemakaian Material",
        icon: Boxes,
        link: "/admin/pemakaian-material",
    },
    {
        title: "Pengembalian Stok",
        icon: RotateCcw,
        link: "/admin/pengembalian-material",
    },
    {
        title: "Inventaris Aset",
        icon: Wrench,
        link: "/admin/inventaris-aset",
        permission: "asset-inventory.view",
    },
];

export const ownerSidebarSections = [
    {
        title: "Menu Utama",
        items: [
            dashboardItem,
            {
                title: "Approval & Review",
                icon: ClipboardCheck,
                items: approvalItems,
            },
            {
                title: "Pemasaran",
                icon: BarChart3,
                items: [
                    {
                        title: "Prospek & Laporan",
                        icon: Share2,
                        items: marketingLeadAndReportItems,
                    },
                    {
                        title: "Operasional Marketing",
                        icon: Megaphone,
                        items: marketingOperationsItems,
                    },
                    {
                        title: "Analitik & Tagihan",
                        icon: WalletCards,
                        items: marketingAnalyticsItems,
                    },
                ],
            },
            {
                title: "Proyek",
                icon: Boxes,
                items: [
                    {
                        title: "Unit Rumah & Perumahan",
                        icon: Home,
                        items: projectUnitItems,
                    },
                    {
                        title: "Kontrak & SPK",
                        icon: FileText,
                        items: projectContractItems,
                    },
                    {
                        title: "Pengawasan Lapangan",
                        icon: ShieldCheck,
                        items: projectMonitoringItems,
                    },
                ],
            },
            {
                title: "Keuangan & Akuntansi",
                icon: WalletCards,
                items: financeItems,
            },
            {
                title: "Setting Master Data",
                icon: ShieldCheck,
                items: masterDataItems,
            },
            {
                title: "Gudang",
                icon: Boxes,
                items: warehouseItems,
            },
        ],
    },
];

export const managerSidebarSections = [
    {
        title: "Menu Manajemen",
        items: [
            dashboardItem,
            {
                title: "Approval & Review",
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
                        title: "Daftar Approval",
                        icon: ShieldCheck,
                        link: "/admin/approval",
                    },
                    {
                        title: "Permintaan Pembelian Gudang",
                        icon: ReceiptText,
                        link: "/admin/daftar-permintaan-pembelian",
                    },
                    {
                        title: "Approval Material",
                        icon: Boxes,
                        link: "/admin/permintaan-barang",
                    },
                ],
            },
            {
                title: "Pemasaran",
                icon: BarChart3,
                items: [
                    {
                        title: "Prospek & Laporan",
                        icon: Share2,
                        items: marketingLeadAndReportItems,
                    },
                    {
                        title: "Operasional Marketing",
                        icon: Megaphone,
                        items: marketingOperationsItems,
                    },
                    {
                        title: "Analitik & Tagihan",
                        icon: WalletCards,
                        items: marketingAnalyticsItems,
                    },
                ],
            },
            {
                title: "Proyek",
                icon: Boxes,
                items: [
                    {
                        title: "Unit Rumah & Perumahan",
                        icon: Home,
                        items: projectUnitItems,
                    },
                    {
                        title: "Kontrak & SPK",
                        icon: FileText,
                        items: projectContractItems,
                    },
                    {
                        title: "Pengawasan Lapangan",
                        icon: ShieldCheck,
                        items: projectMonitoringItems,
                    },
                ],
            },
        ],
    },
];
