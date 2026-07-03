import {
    Activity,
    BarChart3,
    Banknote,
    BookOpen,
    ClipboardList,
    FileBarChart,
    Landmark,
    LayoutDashboard,
    Library,
    ListTree,
    ReceiptText,
    RotateCcw,
    Scale,
    ShieldCheck,
    ShoppingCart,
    TrendingDown,
    TrendingUp,
    WalletCards,
} from "lucide-react";

const keuanganSidebar = [
    {
        title: "Keuangan",
        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                link: "/admin/keuangan/dashboard",
                permission: "keuangan.view",
            },
            {
                title: "Master Data Akuntansi",
                icon: Library,
                items: [
                    {
                        title: "Daftar Akun / COA",
                        icon: ListTree,
                        link: "/admin/keuangan/daftar-akun",
                        permission: "keuangan.view",
                    },
                    {
                        title: "Master Rekening Bank",
                        icon: Landmark,
                        link: "/admin/management/master-bank",
                        permission: "master-bank.manage",
                    },
                    {
                        title: "Tipe Pemasukan / Pengeluaran",
                        icon: ReceiptText,
                        link: "/admin/management/tipe-post",
                        permission: "tipe-post.manage",
                    },
                ],
            },
            {
                title: "Kas & Bank",
                icon: Banknote,
                items: [
                    {
                        title: "Input Kas Masuk / Keluar",
                        icon: ReceiptText,
                        link: "/admin/keuangan/transaksi-kas-bank",
                        permission: "keuangan.create",
                    },
                    {
                        title: "Mutasi & Saldo Rekening",
                        icon: Banknote,
                        link: "/admin/rekening-bank",
                        permission: "bank-account-ledger.view",
                    },
                ],
            },
            {
                title: "Pemasukan",
                icon: ReceiptText,
                items: [
                    {
                        title: "Konfirmasi Booking Fee & Uang Muka",
                        icon: ShieldCheck,
                        link: "/admin/keuangan/pembayaran-spr?tab=booking",
                        permission: "spr-payment.view",
                    },
                    {
                        title: "Piutang Customer",
                        icon: TrendingUp,
                        link: "/admin/keuangan/piutang",
                        permission: "keuangan.view",
                    },
                ],
            },
            {
                title: "Pengeluaran",
                icon: Banknote,
                items: [
                    {
                        title: "Permintaan Pembelian Gudang",
                        icon: ClipboardList,
                        link: "/admin/daftar-permintaan-pembelian",
                        permission: "material-purchase.view",
                    },
                    {
                        title: "Pembelian Barang",
                        icon: ShoppingCart,
                        link: "/admin/pembelian-material",
                        permission: "material-purchase.view",
                    },
                    {
                        title: "Refund Booking Fee & Uang Muka",
                        icon: RotateCcw,
                        link: "/admin/keuangan/refund-spr",
                        permission: "refund-spr.view",
                    },
                    {
                        title: "Approval Pengeluaran",
                        icon: ReceiptText,
                        link: "/admin/approval",
                        permission: "approval.view",
                    },
                    {
                        title: "Hutang Supplier & Kontraktor",
                        icon: TrendingDown,
                        link: "/admin/keuangan/hutang",
                        permission: "keuangan.view",
                    },
                    {
                        title: "Pembayaran SPK Kontraktor",
                        icon: WalletCards,
                        link: "/admin/keuangan/pembayaran-spk",
                        permission: "spk-payment.view",
                    },
                ],
            },
            {
                title: "Akuntansi",
                icon: BookOpen,
                items: [
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
                        permission: "keuangan.view",
                    },
                    {
                        title: "Neraca Saldo",
                        icon: Scale,
                        link: "/admin/keuangan/neraca-saldo",
                        permission: "keuangan.view",
                    },
                ],
            },
            {
                title: "Laporan Keuangan",
                icon: FileBarChart,
                items: [
                    {
                        title: "Laba Rugi",
                        icon: BarChart3,
                        link: "/admin/keuangan/laba-rugi",
                        permission: "laporan.view",
                    },
                    {
                        title: "Neraca",
                        icon: Scale,
                        link: "/admin/keuangan/neraca",
                        permission: "laporan.view",
                    },
                    {
                        title: "Arus Kas",
                        icon: Activity,
                        link: "/admin/keuangan/arus-kas",
                        permission: "laporan.view",
                    },
                ],
            },
        ],
    },
];

export default keuanganSidebar;
