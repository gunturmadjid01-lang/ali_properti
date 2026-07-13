import {
    Activity, Banknote, BarChart3, BellRing, BookOpen, Boxes, Building2,
    CalendarClock, ClipboardCheck, FileBarChart, FileText, HardHat, Home,
    KeyRound, LayoutDashboard, Library, Megaphone, PackageCheck, ReceiptText,
    RotateCcw, Scale, ShieldCheck, ShoppingCart, Target, TrendingDown,
    TrendingUp, UserPlus, Users, WalletCards, Warehouse, Wrench,
} from "lucide-react";

const inventoryItems = [
    ["Dashboard", "dashboard"], ["Kategori Barang", "categories"],
    ["Data Barang", "items"], ["Unit Aset", "units"],
    ["Lokasi Inventaris", "locations"], ["Peminjaman", "loans"],
    ["Pengembalian", "returns"], ["Mutasi", "transfers"],
    ["Barang Rusak", "damages"], ["Barang Hilang", "losses"],
    ["Stock Opname", "stock-opname"], ["Laporan", "reports"],
].map(([title, path]) => ({ title, icon: Boxes, link: `/admin/inventaris-perusahaan/${path}` }));

const heavyEquipmentItems = [
    ["Dashboard", "dashboard"], ["Data Alat Berat", "equipment"],
    ["Jenis Alat", "types"], ["Komponen", "components"],
    ["Penggantian Komponen", "replacements"], ["Penggunaan Alat", "usage"],
    ["Operator", "operators"], ["Maintenance", "maintenance"],
    ["Kerusakan", "damages"], ["Pengisian BBM", "fuel"], ["Laporan", "reports"],
].map(([title, path]) => ({ title, icon: HardHat, link: `/admin/alat-berat/${path}` }));

const sidebarMenu = [
    {
        title: "Utama",
        items: [
            { title: "Dashboard", icon: LayoutDashboard, link: "/admin/dashboard" },
        ],
    },
    {
        title: "Organisasi & Master Data",
        items: [
            {
                title: "Pengguna & Akses", icon: Users, items: [
                    { title: "Data Pengguna", icon: Users, link: "/admin/management/user", permission: "users.view" },
                    { title: "Role & Permission", icon: ShieldCheck, link: "/admin/management/role-permission", permissionsAny: ["roles.view", "roles.manage"] },
                    { title: "Gaji Pegawai", icon: Banknote, link: "/admin/gaji-pegawai", permission: "payroll.view" },
                ],
            },
            {
                title: "Perusahaan & Properti", icon: Building2, items: [
                    { title: "Cabang Perusahaan", icon: Building2, link: "/admin/management/cabang-perusahaan", permission: "cabang.view" },
                    { title: "Perumahan", icon: Home, link: "/admin/management/perumahan", permission: "perumahan.view" },
                    { title: "Kapling / Unit", icon: Home, link: "/admin/unit-rumah", permission: "detail-rumah.view" },
                    { title: "Data Pemilik Unit", icon: KeyRound, link: "/admin/pemilik-unit", permission: "unit-ownership.view" },
                ],
            },
            {
                title: "Dokumen & Referensi", icon: FileText, items: [
                    { title: "Dokumen Legalitas", icon: FileText, link: "/admin/management/dokumen-legalitas-rumah", permission: "dokumen-legalitas.view" },
                    { title: "Dokumen Pelanggan", icon: FileText, link: "/admin/management/master-dokumen-customer", permission: "dokumen-customer.view" },
                    { title: "Master Rekening Bank", icon: Banknote, link: "/admin/management/master-bank", permission: "master-bank.view" },
                    { title: "Tipe Pemasukan / Pengeluaran", icon: ReceiptText, link: "/admin/management/tipe-post", permission: "tipe-post.view" },
                    { title: "Daftar Tukang", icon: Wrench, link: "/admin/tukang", permission: "tukang.view" },
                ],
            },
        ],
    },
    {
        title: "Proyek & Pembangunan",
        items: [
            {
                title: "Perencanaan Biaya", icon: FileText, items: [
                    { title: "RAB Perumahan", icon: FileText, link: "/admin/management/perumahan", permission: "rab-perumahan.view" },
                    { title: "HPP Unit Rumah", icon: FileText, link: "/admin/hpp-unit-rumah", permission: "rab-unit.view" },
                ],
            },
            {
                title: "Pelaksanaan Lapangan", icon: HardHat, items: [
                    { title: "Progress Pembangunan", icon: TrendingUp, link: "/admin/progress-pembangunan", permission: "progress.view" },
                    { title: "Jadwal Lapangan", icon: CalendarClock, link: "/admin/jadwal-lapangan", permission: "site-schedule.view" },
                    { title: "Laporan Lapangan", icon: FileBarChart, link: "/admin/laporan-lapangan", permission: "site-report.view" },
                    { title: "Kontrol Kualitas", icon: ShieldCheck, link: "/admin/kontrol-kualitas", permission: "quality-inspection.view" },
                    { title: "Pengawasan Lapangan", icon: ClipboardCheck, link: "/admin/pengawasan/defect", permission: "field-supervision.view" },
                ],
            },
            {
                title: "Kontrak & SPK", icon: FileText, items: [
                    { title: "Input SPK", icon: FileText, link: "/admin/spk-kontraktor", exact: true, permission: "spk-kontraktor.view" },
                    { title: "Template Pekerjaan SPK", icon: FileText, link: "/admin/spk-template", permissionsAny: ["spk-template-perumahan.view", "spk-template-unit.view"] },
                    { title: "Pembayaran SPK", icon: ReceiptText, link: "/admin/keuangan/pembayaran-spk", permission: "spk-payment.view" },
                ],
            },
        ],
    },
    {
        title: "Penjualan & Marketing",
        items: [
            {
                title: "Prospek & Pelanggan", icon: Users, items: [
                    { title: "Data Pelanggan", icon: UserPlus, link: "/admin/marketing/calon-konsumen", permission: "customer.view" },
                    { title: "Tindak Lanjut", icon: BellRing, link: "/admin/marketing/jejak-follow-up", permission: "customer.follow-up" },
                    { title: "Pengingat Follow Up", icon: BellRing, link: "/admin/marketing/operasional/reminder", badgeKey: "reminder_follow_up", permission: "marketing.reminder.manage" },
                ],
            },
            {
                title: "Transaksi Penjualan", icon: ReceiptText, items: [
                    { title: "SPR", icon: FileText, link: "/admin/marketing/spr", permission: "booking.view" },
                    { title: "Pembayaran SPR", icon: Banknote, link: "/admin/marketing/pembayaran-spr", permission: "spr-payment.view" },
                    { title: "Pengajuan KPR", icon: ClipboardCheck, link: "/admin/kpr", permission: "kpr.view" },
                    { title: "Akad KPR", icon: FileText, link: "/admin/kpr/proses/akad", permission: "kpr-akad.view" },
                    { title: "Serah Terima", icon: KeyRound, link: "/admin/kpr/proses/serah_terima", permission: "handover-customer.view" },
                    { title: "Refund SPR", icon: RotateCcw, link: "/admin/refund-spr", permission: "refund-spr.view" },
                ],
            },
            {
                title: "Tools & Analitik Marketing", icon: Target, items: [
                    { title: "Unit Tersedia", icon: Home, link: "/admin/marketing/tools/unit-stock", permission: "unit-stock.view" },
                    { title: "Daftar Harga", icon: ReceiptText, link: "/admin/marketing/tools/pricelist", permission: "pricelist.view" },
                    { title: "Simulasi Pembayaran", icon: Banknote, link: "/admin/marketing/tools/simulasi-pembayaran", permission: "payment-simulation.view" },
                    { title: "Sumber Lead", icon: Megaphone, link: "/admin/marketing/sumber-lead", permission: "marketing.lead-source.manage" },
                    { title: "Laporan Lead", icon: BarChart3, link: "/admin/marketing/laporan-lead", permission: "marketing.lead-report.view" },
                    { title: "Pipeline Marketing", icon: Target, link: "/admin/marketing/operasional/pipeline", permissionsAny: ["marketing.pipeline.view", "marketing.pipeline-report.view"] },
                    { title: "Campaign", icon: Megaphone, link: "/admin/marketing/operasional/campaign", permission: "marketing.campaign.manage" },
                    { title: "Validasi Berkas", icon: FileText, link: "/admin/marketing/operasional/dokumen", permission: "marketing.document-review.manage" },
                    { title: "Monitoring Aktivitas", icon: Activity, link: "/admin/marketing/tools/monitoring-aktivitas", permission: "marketing.activity.view" },
                    { title: "Leaderboard", icon: BarChart3, link: "/admin/marketing/tools/leaderboard-sales", permission: "marketing.leaderboard.view" },
                ],
            },
        ],
    },
    {
        title: "Gudang, Logistik & Aset",
        items: [
            {
                title: "Material & Persediaan", icon: Warehouse, items: [
                    { title: "Dashboard Gudang", icon: LayoutDashboard, link: "/admin/gudang", permissionsAny: ["site-material-stock.view", "material-request.view", "material-purchase.view"] },
                    { title: "Master Material", icon: Boxes, link: "/admin/master-material", permission: "master-material.view" },
                    { title: "Stok Material", icon: Boxes, link: "/admin/stok-material", permission: "site-material-stock.view" },
                    { title: "Kartu Stok", icon: ReceiptText, link: "/admin/kartu-stok", permission: "site-material-stock.view" },
                    { title: "Stock Opname Material", icon: PackageCheck, link: "/admin/stock-opname", permission: "material-stock-opname.view" },
                    { title: "Permintaan Material", icon: ClipboardCheck, link: "/admin/permintaan-barang", permission: "material-request.view" },
                    { title: "Pembelian Material", icon: ShoppingCart, link: "/admin/pembelian-material", permission: "material-purchase.view" },
                    { title: "Pemakaian Material", icon: Boxes, link: "/admin/pemakaian-material", permission: "material-usage.view" },
                    { title: "Pengembalian Material", icon: RotateCcw, link: "/admin/pengembalian-material", permission: "material-return.view" },
                ],
            },
            { title: "Supplier", icon: Users, link: "/admin/supplier", permission: "supplier.view" },
            { title: "Inventaris Perusahaan", icon: Boxes, permission: "company-inventory.view", items: inventoryItems },
            { title: "Alat Berat", icon: HardHat, permission: "heavy-equipment.view", items: heavyEquipmentItems },
        ],
    },
    {
        title: "Keuangan & Akuntansi",
        items: [
            {
                title: "Kas & Bank", icon: WalletCards, items: [
                    { title: "Dashboard Keuangan", icon: LayoutDashboard, link: "/admin/keuangan/dashboard", permission: "keuangan.view" },
                    { title: "Pemasukan Kas & Bank", icon: TrendingUp, link: "/admin/keuangan/pemasukan", permission: "keuangan.create" },
                    { title: "Pengeluaran Kas & Bank", icon: TrendingDown, link: "/admin/keuangan/pengeluaran", permission: "keuangan.create" },
                    { title: "Mutasi & Saldo Rekening", icon: Banknote, link: "/admin/rekening-bank", permission: "bank-account-ledger.view" },
                    { title: "Kas Kecil", icon: WalletCards, permission: "petty-cash.view", items: [
                        { title: "Saldo", icon: WalletCards, link: "/admin/kas-kecil/saldo" },
                        { title: "Pengisian Dana", icon: Banknote, link: "/admin/kas-kecil/pengisian" },
                        { title: "Pengeluaran", icon: ReceiptText, link: "/admin/kas-kecil/pengeluaran" },
                        { title: "Laporan", icon: FileBarChart, link: "/admin/kas-kecil/laporan" },
                    ] },
                ],
            },
            {
                title: "Akuntansi", icon: BookOpen, items: [
                    { title: "Jurnal Umum", icon: BookOpen, link: "/admin/keuangan/jurnal-umum", permission: "keuangan.view" },
                    { title: "Buku Besar", icon: Library, link: "/admin/keuangan/buku-besar", permission: "buku-besar.view" },
                    { title: "Neraca Saldo", icon: Scale, link: "/admin/keuangan/neraca-saldo", permission: "neraca-saldo.view" },
                    { title: "Laba Rugi", icon: BarChart3, link: "/admin/keuangan/laba-rugi", permission: "laba-rugi.view" },
                    { title: "Neraca", icon: Scale, link: "/admin/keuangan/neraca", permission: "neraca.view" },
                    { title: "Arus Kas", icon: Activity, link: "/admin/keuangan/arus-kas", permission: "arus-kas.view" },
                    { title: "Piutang Pelanggan", icon: TrendingUp, link: "/admin/keuangan/piutang", permission: "piutang.view" },
                    { title: "Hutang Supplier & Kontraktor", icon: TrendingDown, link: "/admin/keuangan/hutang", permission: "hutang.view" },
                ],
            },
        ],
    },
    {
        title: "Approval & Laporan",
        items: [
            {
                title: "Approval", icon: ClipboardCheck, items: [
                    { title: "Daftar Approval", icon: ClipboardCheck, link: "/admin/approval", permission: "approval.view" },
                    { title: "Approval SPK", icon: FileText, link: "/admin/spk-kontraktor/approval", permission: "spk-kontraktor.approve" },
                    { title: "Setting Approval", icon: ShieldCheck, link: "/admin/approval/settings", permission: "approval.settings" },
                ],
            },
            {
                title: "Laporan", icon: FileBarChart, items: [
                    { title: "Pusat Laporan", icon: FileBarChart, link: "/admin/laporan", permission: "laporan.view" },
                    { title: "Progress Pembangunan", icon: TrendingUp, link: "/admin/laporan-progress-pembangunan", permissionsAny: ["laporan.view", "progress.view"] },
                    { title: "Master Data", icon: FileText, link: "/admin/laporan/master-data", permissionsAny: ["laporan.view", "laporan-master-data.view"] },
                    { title: "Pembelian", icon: ShoppingCart, link: "/admin/laporan/pembelian", permissionsAny: ["laporan.view", "laporan-pembelian.view"] },
                    { title: "Persediaan Material", icon: Boxes, link: "/admin/laporan/persediaan-material", permissionsAny: ["laporan.view", "laporan-persediaan-material.view"] },
                    { title: "Marketing", icon: BarChart3, link: "/admin/laporan/marketing", permissionsAny: ["laporan.view", "laporan-marketing.view"] },
                ],
            },
        ],
    },
];

export default sidebarMenu;
