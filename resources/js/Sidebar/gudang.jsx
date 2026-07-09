import {
    Banknote,
    Boxes,
    Building2,
    ClipboardCheck,
    LayoutDashboard,
    PackageCheck,
    RotateCcw,
    ShoppingCart,
    Warehouse,
    Wrench,
} from "lucide-react";

const gudangSidebar = [
    {
        title: "Gudang",
        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                link: "/admin/dashboard",
                permission: "dashboard.view",
            },
            {
                title: "Master Data Gudang",
                icon: Warehouse,
                items: [
                    {
                        title: "Data Gudang",
                        icon: Building2,
                        link: "/admin/gudang",
                        permission: "master-material.view",
                    },
                    {
                        title: "Kelola Item Material",
                        icon: Boxes,
                        link: "/admin/master-material",
                        permission: "master-material.view",
                    },
                    {
                        title: "Harga Material",
                        icon: Banknote,
                        link: "/admin/harga-material",
                        permission: "material-price.view",
                    },
                    {
                        title: "Saldo Awal Material",
                        icon: PackageCheck,
                        link: "/admin/saldo-awal-material",
                        permission: "material-opening-balance.view",
                    },
                    {
                        title: "Stok Material",
                        icon: Warehouse,
                        link: "/admin/stok-material",
                        permission: "site-material-stock.view",
                    },
                ],
            },
            {
                title: "Transaksi Gudang",
                icon: ClipboardCheck,
                items: [
                    {
                        title: "Permintaan Material",
                        icon: ClipboardCheck,
                        link: "/admin/permintaan-barang",
                        permission: "material-request.view",
                    },
                    {
                        title: "Permintaan Pembelian Gudang",
                        icon: ShoppingCart,
                        link: "/admin/permintaan-pembelian",
                        permission: "material-purchase.view",
                    },
                    {
                        title: "Pemeriksaan Barang Masuk",
                        icon: PackageCheck,
                        link: "/admin/pemeriksaan-barang-masuk",
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
                title: "Operasional Aset",
                icon: Wrench,
                items: [
                    {
                        title: "Inventaris Aset",
                        icon: Wrench,
                        link: "/admin/inventaris-aset",
                        permission: "asset-inventory.view",
                    },
                    {
                        title: "Mutasi Material",
                        icon: Boxes,
                        link: "/admin/logistik",
                        permission: "material-usage.view",
                    },
                ],
            },
        ],
    },
];

export default gudangSidebar;
