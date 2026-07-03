import {
    Boxes,
    Building2,
    ClipboardCheck,
    LayoutDashboard,
    PackageCheck,
    ShoppingCart,
    RotateCcw,
    Warehouse,
} from "lucide-react";

const gudangSidebar = [
    {
        title: "Logistik",
        items: [
            { title: "Dashboard", icon: LayoutDashboard, link: "/admin/dashboard" },
            {
                title: "Master Data Logistik",
                icon: Warehouse,
                items: [
                    { title: "Data Gudang", icon: Building2, link: "/admin/gudang", permission: "master-material.view" },
                    { title: "Master Material", icon: Boxes, link: "/admin/master-material", permission: "master-material.view" },
                    { title: "Stok Material", icon: Warehouse, link: "/admin/stok-material", permission: "site-material-stock.view" },
                ],
            },
            {
                title: "Transaksi Gudang",
                icon: PackageCheck,
                items: [
                    { title: "Permintaan Material", icon: ClipboardCheck, link: "/admin/permintaan-barang", permission: "material-request.view" },
                    { title: "Permintaan Pembelian Gudang", icon: ShoppingCart, link: "/admin/permintaan-pembelian", permission: "material-purchase.view" },
                    { title: "Pemeriksaan Barang Masuk", icon: PackageCheck, link: "/admin/pemeriksaan-barang-masuk", permission: "material-purchase.view" },
                    { title: "Pengembalian Material", icon: RotateCcw, link: "/admin/pengembalian-material", permission: "material-return.view" },
                    { title: "Mutasi Material", icon: Boxes, link: "/admin/logistik", permission: "material-usage.view" },
                ],
            },
        ],
    },
];

export default gudangSidebar;
