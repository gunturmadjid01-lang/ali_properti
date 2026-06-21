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
        title: "Menu Gudang",
        items: [
            { title: "Dashboard", icon: LayoutDashboard, link: "/admin/dashboard" },
            {
                title: "Master Gudang",
                icon: Warehouse,
                items: [
                    { title: "Data Gudang", icon: Building2, link: "/admin/gudang" },
                    { title: "Master Material", icon: Boxes, link: "/admin/master-material" },
                    { title: "Stok Material", icon: Warehouse, link: "/admin/stok-material" },
                ],
            },
            {
                title: "Operasional Gudang",
                icon: PackageCheck,
                items: [
                    { title: "Permintaan Material", icon: ClipboardCheck, link: "/admin/permintaan-barang" },
                    { title: "Permintaan Pembelian", icon: ShoppingCart, link: "/admin/permintaan-pembelian" },
                    { title: "Pemeriksaan Barang Masuk", icon: PackageCheck, link: "/admin/pemeriksaan-barang-masuk" },
                    { title: "Pengembalian dari Lokasi", icon: RotateCcw, link: "/admin/pengembalian-material" },
                    { title: "Mutasi Gudang", icon: Boxes, link: "/admin/logistik" },
                ],
            },
        ],
    },
];

export default gudangSidebar;
