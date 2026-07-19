import {
    Activity,
    AlertTriangle,
    ArrowDownToLine,
    BadgeDollarSign,
    Banknote,
    BarChart3,
    BellRing,
    BookOpen,
    Boxes,
    Building2,
    CalendarClock,
    ClipboardCheck,
    FileBarChart,
    FileText,
    HardHat,
    Home,
    KeyRound,
    LayoutDashboard,
    Library,
    Megaphone,
    PackageCheck,
    ReceiptText,
    RotateCcw,
    Scale,
    ShieldCheck,
    ShoppingCart,
    Target,
    TrendingDown,
    TrendingUp,
    UserPlus,
    Users,
    WalletCards,
    Warehouse,
    Wrench,
} from "lucide-react";

const inventoryItems = [
    ["Dasbor", "dashboard"],
    ["Kategori Barang", "categories"],
    ["Data Barang", "items"],
    ["Unit Aset", "units"],
    ["Lokasi Inventaris", "locations"],
    ["Penerimaan / Penambahan Aset", "receipts"],
    ["Pengambilan & Penyerahan", "loans"],
    ["Pengembalian & Pemeriksaan", "returns"],
    ["Mutasi", "transfers"],
    ["Barang Rusak", "damages"],
    ["Barang Hilang", "losses"],
    ["Stock Opname", "stock-opname"],
    ["Kartu Pergerakan", "ledger"],
    ["Laporan Pengambilan", "reports"],
].map(([title, path]) => ({
    title,
    icon: Boxes,
    link: `/admin/inventaris-perusahaan/${path}`,
    permission: `company-inventory.${path}.view`,
}));

const heavyEquipmentItems = [
    ["Dasbor", "dashboard"],
    ["Data Alat Berat", "equipment"],
    ["Jenis Alat", "types"],
    ["Komponen", "components"],
    ["Penggantian Komponen", "replacements"],
    ["Penggunaan Alat", "usage"],
    ["Operator", "operators"],
    ["Maintenance", "maintenance"],
    ["Kerusakan", "damages"],
    ["Pengisian BBM", "fuel"],
    ["Laporan", "reports"],
].map(([title, path]) => ({
    title,
    icon: HardHat,
    link: `/admin/alat-berat/${path}`,
    permission: `heavy-equipment.${path}.view`,
}));

const cashInstallmentItems = [
    ["Master Skema", "schemes", "schemes"],
    ["Kontrak", "contracts", "contracts"],
    ["Tagihan & Tunggakan", "billings", "billings"],
    ["Laporan Tunai Bertahap", "reports", "reports"],
].map(([title, path, permission]) => ({
    title,
    icon: CalendarClock,
    link: `/admin/penjualan-terintegrasi/${path}`,
    permission: `cash-installment.${permission}.view`,
}));

const developerKprItems = [
    ["Master Produk", "developer-products", "products"],
    ["Pengajuan", "developer-applications", "applications"],
    ["Piutang & Pembayaran", "developer-receivables", "receivables"],
    ["Laporan KPR Developer", "developer-reports", "reports"],
].map(([title, path, permission]) => ({
    title,
    icon: Home,
    link: `/admin/penjualan-terintegrasi/${path}`,
    permission: `developer-kpr.${permission}.view`,
}));

const bankKprProcessItems = [
    ["Pengajuan", "bank-applications", "applications"],
    ["Proses KPR", "bank-document-validation", "document-validation"],
    ["Akad & Pencairan", "bank-contract-schedule", "contract-schedule"],
    ["Laporan KPR Bank", "bank-reports", "reports"],
].map(([title, path, permission]) => ({
    title,
    icon: Banknote,
    link: `/admin/penjualan-terintegrasi/${path}`,
    permission: `bank-kpr.${permission}.view`,
}));

const sidebarMenu = [
    {
        title: "Utama",
        items: [
            {
                title: "Dasbor",
                icon: LayoutDashboard,
                link: "/admin/dashboard",
            },
        ],
    },
    {
        title: "Organisasi & Master Data",
        items: [
            {
                title: "Pengguna & Akses",
                icon: Users,
                items: [
                    {
                        title: "Data Pengguna",
                        icon: Users,
                        link: "/admin/management/user",
                        permission: "users.view",
                    },
                    {
                        title: "Peran & Hak Akses",
                        icon: ShieldCheck,
                        link: "/admin/management/role-permission",
                        permissionsAny: ["roles.view", "roles.manage"],
                    },
                ],
            },
            {
                title: "Pegawai & Absensi",
                icon: CalendarClock,
                items: [
                    {
                        title: "Absensi Pegawai",
                        icon: Users,
                        link: "/admin/absensi-pegawai",
                        permission: "attendance.view",
                    },
                    {
                        title: "Pengaturan Jam Absensi",
                        icon: CalendarClock,
                        link: "/admin/pengaturan-absensi",
                        permission: "attendance.settings",
                    },
                ],
            },
            {
                title: "Perusahaan & Properti",
                icon: Building2,
                items: [
                    {
                        title: "Cabang Perusahaan",
                        icon: Building2,
                        link: "/admin/management/cabang-perusahaan",
                        permission: "cabang.view",
                    },
                    {
                        title: "Perumahan",
                        icon: Home,
                        link: "/admin/management/perumahan",
                        permission: "perumahan.view",
                    },
                    {
                        title: "Kapling / Unit",
                        icon: Home,
                        link: "/admin/unit-rumah",
                        permission: "detail-rumah.view",
                    },
                    {
                        title: "Data Pemilik Unit",
                        icon: KeyRound,
                        link: "/admin/pemilik-unit",
                        permission: "unit-ownership.view",
                    },
                ],
            },
            {
                title: "Dokumen & Referensi",
                icon: FileText,
                items: [
                    {
                        title: "Dokumen Legalitas",
                        icon: FileText,
                        link: "/admin/management/dokumen-legalitas-rumah",
                        permission: "dokumen-legalitas.view",
                    },
                    {
                        title: "Dokumen Pelanggan",
                        icon: FileText,
                        link: "/admin/management/master-dokumen-customer",
                        permission: "dokumen-customer.view",
                    },
                    {
                        title: "Repositori Dokumen Pelanggan",
                        icon: FileText,
                        link: "/admin/repository-dokumen-customer",
                        permissionsAny: ["customer.view", "booking.view"],
                    },
                    {
                        title: "Dokumen Baku Penjualan",
                        icon: FileText,
                        link: "/admin/master-dokumen",
                        permission: "document-template.view",
                    },
                    {
                        title: "Master Rekening Bank",
                        icon: Banknote,
                        link: "/admin/management/master-bank",
                        permission: "master-bank.view",
                    },
                    {
                        title: "Tipe Pemasukan / Pengeluaran",
                        icon: ReceiptText,
                        link: "/admin/management/tipe-post",
                        permission: "tipe-post.view",
                    },
                    {
                        title: "Daftar Tukang",
                        icon: Wrench,
                        link: "/admin/tukang",
                        permission: "tukang.view",
                    },
                ],
            },
            {
                title: "Master Kredit Bank",
                icon: Banknote,
                items: [
                    {
                        title: "Master Bank Kredit",
                        icon: Banknote,
                        link: "/admin/bank-kredit",
                        permission: "bank-credit-master.view",
                    },
                    {
                        title: "Cabang Bank",
                        icon: Building2,
                        link: "/admin/cabang-bank",
                        permission: "bank-branch.view",
                    },
                    {
                        title: "Produk Kredit Bank",
                        icon: ReceiptText,
                        link: "/admin/produk-kredit-bank",
                        permission: "bank-credit-product.view",
                    },
                    {
                        title: "Kerja Sama Bank dan Perumahan",
                        icon: Home,
                        link: "/admin/kerja-sama-bank",
                        permission: "bank-housing-partnership.view",
                    },
                    {
                        title: "Paket Persyaratan Dokumen",
                        icon: FileText,
                        link: "/admin/paket-persyaratan-dokumen",
                        permission: "bank-document-requirement.view",
                    },
                    {
                        title: "Riwayat / Versi Kerja Sama",
                        icon: RotateCcw,
                        link: "/admin/riwayat-kerja-sama-bank",
                        permission: "bank-partnership-history.view",
                    },
                ],
            },
        ],
    },
    {
        title: "Proyek & Pembangunan",
        items: [
            {
                title: "Perencanaan Biaya",
                icon: FileText,
                items: [
                    {
                        title: "RAB Perumahan",
                        icon: FileText,
                        link: "/admin/management/perumahan",
                        permission: "rab-perumahan.view",
                    },
                    {
                        title: "HPP Unit Rumah",
                        icon: FileText,
                        link: "/admin/hpp-unit-rumah",
                        permission: "rab-unit.view",
                    },
                    {
                        title: "Template RAB / Pekerjaan SPK",
                        icon: FileText,
                        link: "/admin/spk-template",
                        permissionsAny: [
                            "spk-template-perumahan.view",
                            "spk-template-unit.view",
                        ],
                    },
                    {
                        title: "Kelompok Material",
                        icon: Boxes,
                        link: "/admin/kelompok-material",
                        permission: "material-group.view",
                    },
                ],
            },
            {
                title: "Pelaksanaan Lapangan",
                icon: HardHat,
                items: [
                    {
                        title: "Kemajuan Pembangunan",
                        icon: TrendingUp,
                        link: "/admin/progress-pembangunan",
                        permission: "progress.view",
                    },
                    {
                        title: "Jadwal Lapangan",
                        icon: CalendarClock,
                        link: "/admin/jadwal-lapangan",
                        permission: "site-schedule.view",
                    },
                    {
                        title: "Laporan Lapangan",
                        icon: FileBarChart,
                        link: "/admin/laporan-lapangan",
                        permission: "site-report.view",
                    },
                    {
                        title: "Kontrol Kualitas",
                        icon: ShieldCheck,
                        link: "/admin/kontrol-kualitas",
                        permission: "quality-inspection.view",
                    },
                    {
                        title: "Pengawasan Lapangan",
                        icon: ClipboardCheck,
                        link: "/admin/pengawasan/defect",
                        permission: "field-supervision.view",
                    },
                ],
            },
            {
                title: "Kontrak & SPK",
                icon: FileText,
                items: [
                    {
                        title: "Input SPK",
                        icon: FileText,
                        link: "/admin/spk-kontraktor",
                        exact: true,
                        permission: "spk-kontraktor.view",
                    },
                    {
                        title: "Pembayaran SPK",
                        icon: ReceiptText,
                        link: "/admin/keuangan/pembayaran-spk",
                        permission: "spk-payment.view",
                    },
                ],
            },
        ],
    },
    {
        title: "Penjualan & Marketing",
        items: [
            {
                title: "Prospek & Pelanggan",
                icon: Users,
                items: [
                    {
                        title: "Data Pelanggan",
                        icon: UserPlus,
                        link: "/admin/marketing/calon-konsumen",
                        permission: "customer.view",
                    },
                    {
                        title: "Tindak Lanjut",
                        icon: BellRing,
                        link: "/admin/marketing/jejak-follow-up",
                        permission: "customer.follow-up",
                    },
                    {
                        title: "Pengingat Tindak Lanjut",
                        icon: BellRing,
                        link: "/admin/marketing/operasional/reminder",
                        badgeKey: "reminder_follow_up",
                        permission: "marketing.reminder.manage",
                    },
                ],
            },
            {
                title: "Transaksi Penjualan",
                icon: ReceiptText,
                items: [
                    {
                        title: "Reservasi Perumahan",
                        icon: Home,
                        link: "/admin/marketing/reservasi-perumahan",
                        permission: "housing-reservation.view",
                    },
                    {
                        title: "SPR",
                        icon: FileText,
                        link: "/admin/marketing/spr",
                        permission: "booking.view",
                    },
                    {
                        title: "Daftar Transaksi Penjualan",
                        icon: ReceiptText,
                        link: "/admin/penjualan-terintegrasi/transactions",
                        permission: "sales.transactions.view",
                    },
                    {
                        title: "Penanganan Proses Gagal",
                        icon: AlertTriangle,
                        link: "/admin/penanganan-penjualan-gagal",
                        permissionsAny: ["booking.view", "booking.manage"],
                    },
                    {
                        title: "Pembayaran Pelanggan",
                        icon: Banknote,
                        link: "/admin/keuangan/penerimaan-customer",
                        permission: "customer-receipts.view",
                    },
                ],
            },
            {
                title: "Tunai Bertahap",
                icon: CalendarClock,
                items: cashInstallmentItems,
            },
            { title: "KPR Developer", icon: Home, items: developerKprItems },
            {
                title: "Proses KPR Bank",
                icon: Banknote,
                items: bankKprProcessItems,
            },
            {
                title: "Tools & Analitik Marketing",
                icon: Target,
                items: [
                    {
                        title: "Unit Tersedia",
                        icon: Home,
                        link: "/admin/marketing/tools/unit-stock",
                        permission: "unit-stock.view",
                    },
                    {
                        title: "Daftar Harga",
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
                    {
                        title: "Sumber Lead",
                        icon: Megaphone,
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
                        title: "Alur Penjualan Marketing",
                        icon: Target,
                        link: "/admin/marketing/operasional/pipeline",
                        permissionsAny: [
                            "marketing.pipeline.view",
                            "marketing.pipeline-report.view",
                        ],
                    },
                    {
                        title: "Kampanye",
                        icon: Megaphone,
                        link: "/admin/marketing/operasional/campaign",
                        permission: "marketing.campaign.manage",
                    },
                    {
                        title: "Validasi Berkas",
                        icon: FileText,
                        link: "/admin/marketing/operasional/dokumen",
                        permission: "marketing.document-review.manage",
                    },
                    {
                        title: "Monitoring Aktivitas",
                        icon: Activity,
                        link: "/admin/marketing/tools/monitoring-aktivitas",
                        permission: "marketing.activity.view",
                    },
                    {
                        title: "Statistik & Ranking Marketing",
                        icon: BarChart3,
                        link: "/admin/marketing/tools/leaderboard-sales",
                        permissionsAny: [
                            "marketing.leaderboard.view",
                            "booking.view",
                        ],
                    },
                ],
            },
        ],
    },
    {
        title: "Gudang, Logistik & Aset",
        items: [
            {
                title: "Material & Persediaan",
                icon: Warehouse,
                items: [
                    {
                        title: "Dasbor Gudang",
                        icon: LayoutDashboard,
                        link: "/admin/gudang",
                        permissionsAny: [
                            "site-material-stock.view",
                            "material-request.view",
                            "material-purchase.view",
                        ],
                    },
                    {
                        title: "Master Material",
                        icon: Boxes,
                        link: "/admin/master-material",
                        permission: "master-material.view",
                    },
                    {
                        title: "Jenis Material",
                        icon: Boxes,
                        link: "/admin/referensi-material/jenis",
                        permission: "material-type.view",
                    },
                    {
                        title: "Merk Material",
                        icon: Boxes,
                        link: "/admin/referensi-material/merk",
                        permission: "material-brand.view",
                    },
                    {
                        title: "Satuan Material",
                        icon: Boxes,
                        link: "/admin/referensi-material/satuan",
                        permission: "material-unit.view",
                    },
                    {
                        title: "Saldo Awal Material",
                        icon: ArrowDownToLine,
                        link: "/admin/saldo-awal-material",
                        permission: "material-opening-balance.view",
                    },
                    {
                        title: "Stok Material",
                        icon: Boxes,
                        link: "/admin/stok-material",
                        permission: "site-material-stock.view",
                    },
                    {
                        title: "Kartu Stok",
                        icon: ReceiptText,
                        link: "/admin/kartu-stok",
                        permission: "site-material-stock.view",
                    },
                    {
                        title: "Stock Opname Material",
                        icon: PackageCheck,
                        link: "/admin/stock-opname",
                        permission: "material-stock-opname.view",
                    },
                    {
                        title: "Permintaan Material",
                        icon: ClipboardCheck,
                        link: "/admin/permintaan-barang",
                        permission: "material-request.view",
                    },
                    {
                        title: "Pembelian Material",
                        icon: ShoppingCart,
                        link: "/admin/pembelian-material",
                        permission: "material-purchase.view",
                    },
                    {
                        title: "Pemakaian Material",
                        icon: Boxes,
                        link: "/admin/pemakaian-material",
                        permission: "material-usage.view",
                    },
                    {
                        title: "Pengembalian Material",
                        icon: RotateCcw,
                        link: "/admin/pengembalian-material",
                        permission: "material-return.view",
                    },
                ],
            },
            {
                title: "Supplier",
                icon: Users,
                link: "/admin/supplier",
                permission: "supplier.view",
            },
            {
                title: "Inventaris Perusahaan",
                icon: Boxes,
                permissionsAny: inventoryItems.map((item) => item.permission),
                items: inventoryItems,
            },
            {
                title: "Alat Berat",
                icon: HardHat,
                permissionsAny: heavyEquipmentItems.map(
                    (item) => item.permission,
                ),
                items: heavyEquipmentItems,
            },
        ],
    },
    {
        title: "Keuangan & Akuntansi",
        items: [
            {
                title: "Kas & Bank",
                icon: WalletCards,
                items: [
                    {
                        title: "Dasbor Keuangan",
                        icon: LayoutDashboard,
                        link: "/admin/keuangan/dashboard",
                        permission: "keuangan.view",
                    },
                    {
                        title: "Pemasukan Kas & Bank",
                        icon: TrendingUp,
                        link: "/admin/keuangan/pemasukan",
                        permission: "keuangan.create",
                    },
                    {
                        title: "Pengeluaran Kas & Bank",
                        icon: TrendingDown,
                        link: "/admin/keuangan/pengeluaran",
                        permission: "keuangan.create",
                    },
                    {
                        title: "Mutasi & Saldo Rekening",
                        icon: Banknote,
                        link: "/admin/rekening-bank",
                        permission: "bank-account-ledger.view",
                    },
                    {
                        title: "Kas Kecil",
                        icon: WalletCards,
                        permission: "petty-cash.view",
                        items: [
                            {
                                title: "Saldo",
                                icon: WalletCards,
                                link: "/admin/kas-kecil/saldo",
                            },
                            {
                                title: "Pengisian Dana",
                                icon: Banknote,
                                link: "/admin/kas-kecil/pengisian",
                            },
                            {
                                title: "Pengeluaran",
                                icon: ReceiptText,
                                link: "/admin/kas-kecil/pengeluaran",
                            },
                            {
                                title: "Laporan",
                                icon: FileBarChart,
                                link: "/admin/kas-kecil/laporan",
                            },
                        ],
                    },
                ],
            },
            {
                title: "Akuntansi",
                icon: BookOpen,
                items: [
                    {
                        title: "Daftar Gaji Pegawai",
                        icon: Banknote,
                        link: "/admin/daftar-gaji-pegawai",
                        permission: "payroll.view",
                    },
                    {
                        title: "Transaksi Penggajian",
                        icon: ReceiptText,
                        link: "/admin/gaji-pegawai",
                        permission: "payroll.view",
                    },
                    {
                        title: "Panjar Pegawai",
                        icon: WalletCards,
                        link: "/admin/panjar-pegawai",
                        permission: "payroll.view",
                    },
                    {
                        title: "Jurnal Umum",
                        icon: BookOpen,
                        link: "/admin/keuangan/jurnal-umum",
                        permission: "keuangan.view",
                    },
                    {
                        title: "Buku Besar",
                        icon: Library,
                        link: "/admin/keuangan/buku-besar",
                        permission: "buku-besar.view",
                    },
                    {
                        title: "Neraca Saldo",
                        icon: Scale,
                        link: "/admin/keuangan/neraca-saldo",
                        permission: "neraca-saldo.view",
                    },
                    {
                        title: "Laba Rugi",
                        icon: BarChart3,
                        link: "/admin/keuangan/laba-rugi",
                        permission: "laba-rugi.view",
                    },
                    {
                        title: "Neraca",
                        icon: Scale,
                        link: "/admin/keuangan/neraca",
                        permission: "neraca.view",
                    },
                    {
                        title: "Arus Kas",
                        icon: Activity,
                        link: "/admin/keuangan/arus-kas",
                        permission: "arus-kas.view",
                    },
                    {
                        title: "Piutang Pelanggan",
                        icon: TrendingUp,
                        link: "/admin/keuangan/piutang",
                        permission: "receivables.view",
                    },
                    {
                        title: "Tagihan & Talangan Customer",
                        icon: BadgeDollarSign,
                        link: "/admin/keuangan/tagihan-talangan-customer",
                        permission: "customer-charges.view",
                    },
                    {
                        title: "Refund Booking Fee & DP",
                        icon: BadgeDollarSign,
                        link: "/admin/keuangan/refund-customer",
                        permission: "customer-refunds.view",
                    },
                    {
                        title: "Monitoring Jatuh Tempo",
                        icon: CalendarClock,
                        link: "/admin/keuangan/monitoring-jatuh-tempo",
                        permission: "receivables.view",
                    },
                    {
                        title: "Penerimaan Pelanggan",
                        icon: ReceiptText,
                        link: "/admin/keuangan/penerimaan-customer",
                        permission: "customer-receipts.view",
                    },
                    {
                        title: "Hutang Supplier & Kontraktor",
                        icon: TrendingDown,
                        link: "/admin/keuangan/hutang",
                        permission: "hutang.view",
                    },
                ],
            },
        ],
    },
    {
        title: "Persetujuan & Laporan",
        items: [
            {
                title: "Persetujuan",
                icon: ClipboardCheck,
                items: [
                    {
                        title: "Daftar Persetujuan",
                        icon: ClipboardCheck,
                        link: "/admin/approval",
                        permission: "approval.view",
                    },
                    {
                        title: "Persetujuan SPK",
                        icon: FileText,
                        link: "/admin/spk-kontraktor/approval",
                        permission: "spk-kontraktor.approve",
                    },
                    {
                        title: "Pengaturan Persetujuan",
                        icon: ShieldCheck,
                        link: "/admin/approval/settings",
                        permission: "approval.settings",
                    },
                    {
                        title: "Pengaturan Cetak",
                        icon: ReceiptText,
                        link: "/admin/pengaturan-cetak",
                        permission: "approval.settings",
                    },
                ],
            },
            {
                title: "Laporan",
                icon: FileBarChart,
                items: [
                    {
                        title: "Pusat Laporan",
                        icon: FileBarChart,
                        link: "/admin/laporan",
                        permission: "laporan.view",
                    },
                    {
                        title: "Kemajuan Pembangunan",
                        icon: TrendingUp,
                        link: "/admin/laporan-progress-pembangunan",
                        permissionsAny: ["laporan.view", "progress.view"],
                    },
                    {
                        title: "Master Data",
                        icon: FileText,
                        link: "/admin/laporan/master-data",
                        permissionsAny: [
                            "laporan.view",
                            "laporan-master-data.view",
                        ],
                    },
                    {
                        title: "Pembelian",
                        icon: ShoppingCart,
                        link: "/admin/laporan/pembelian",
                        permissionsAny: [
                            "laporan.view",
                            "laporan-pembelian.view",
                        ],
                    },
                    {
                        title: "Persediaan Material",
                        icon: Boxes,
                        link: "/admin/laporan/persediaan-material",
                        permissionsAny: [
                            "laporan.view",
                            "laporan-persediaan-material.view",
                        ],
                    },
                    {
                        title: "Marketing",
                        icon: BarChart3,
                        link: "/admin/laporan/marketing",
                        permissionsAny: [
                            "laporan.view",
                            "laporan-marketing.view",
                        ],
                    },
                ],
            },
        ],
    },
];

export default sidebarMenu;
