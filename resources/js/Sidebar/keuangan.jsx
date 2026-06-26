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
        title: "Menu Admin Keuangan",
        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                link: "/admin/keuangan/dashboard",
            },
            {
                title: "Master Akuntansi",
                icon: Library,
                items: [
                    {
                        title: "Daftar Akun / COA",
                        icon: ListTree,
                        link: "/admin/keuangan/daftar-akun",
                    },
                    {
                        title: "Master Rekening Bank",
                        icon: Landmark,
                        link: "/admin/management/master-bank",
                    },
                    {
                        title: "Tipe Pemasukan / Pengeluaran",
                        icon: ReceiptText,
                        link: "/admin/management/tipe-post",
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
                    },
                    {
                        title: "Mutasi & Saldo Rekening",
                        icon: Banknote,
                        link: "/admin/rekening-bank",
                    },
                ],
            },
            {
                title: "Transaksi Pemasukan",
                icon: ReceiptText,
                items: [
                    {
                        title: "Konfirmasi Booking Fee & Uang Muka",
                        icon: ShieldCheck,
                        link: "/admin/keuangan/pembayaran-spr?tab=booking",
                    },
                    {
                        title: "Piutang Customer",
                        icon: TrendingUp,
                        link: "/admin/keuangan/piutang",
                    },
                ],
            },
            {
                title: "Transaksi Pengeluaran",
                icon: Banknote,
                items: [
                    {
                        title: "Permintaan Pembelian Gudang",
                        icon: ClipboardList,
                        link: "/admin/daftar-permintaan-pembelian",
                    },
                    {
                        title: "Pembelian Barang",
                        icon: ShoppingCart,
                        link: "/admin/pembelian-material",
                    },
                    {
                        title: "Refund Booking Fee & Uang Muka",
                        icon: RotateCcw,
                        link: "/admin/keuangan/refund-spr",
                    },
                    {
                        title: "Approval Pengeluaran",
                        icon: ReceiptText,
                        link: "/admin/approval",
                    },
                    {
                        title: "Hutang Supplier & Kontraktor",
                        icon: TrendingDown,
                        link: "/admin/keuangan/hutang",
                    },
                    {
                        title: "Pembayaran SPK Kontraktor",
                        icon: WalletCards,
                        link: "/admin/keuangan/pembayaran-spk",
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
                ],
            },
        ],
    },
];

export default keuanganSidebar;
