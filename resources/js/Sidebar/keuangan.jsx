import {
    Banknote,
    ClipboardList,
    LayoutDashboard,
    ReceiptText,
    ShieldCheck,
    ShoppingCart,
} from "lucide-react";

const keuanganSidebar = [
    {
        title: "Menu Admin Keuangan",
        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                link: "/admin/dashboard",
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
                        title: "Approval Pengeluaran",
                        icon: ReceiptText,
                        link: "/admin/approval",
                    },
                    {
                        title: "Mutasi & Saldo Rekening",
                        icon: Banknote,
                        link: "/admin/rekening-bank",
                    },
                ],
            },
        ],
    },
];

export default keuanganSidebar;
