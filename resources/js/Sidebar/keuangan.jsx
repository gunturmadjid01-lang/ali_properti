import {
    Banknote,
    ClipboardList,
    LayoutDashboard,
    ReceiptText,
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
