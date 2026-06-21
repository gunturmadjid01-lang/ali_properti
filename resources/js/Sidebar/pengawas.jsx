import {
    Bell,
    Boxes,
    CalendarClock,
    FileText,
    Home,
    LayoutDashboard,
    PackagePlus,
    RotateCcw,
    ShieldCheck,
    TrendingUp,
    Warehouse,
} from "lucide-react";

const pengawasSidebar = [
    {
        title: "Menu Pengawas",
        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                link: "/admin/dashboard",
            },
            {
                title: "Management Proyek",
                icon: Home,
                items: [
                    {
                        title: "Kapling / Unit",
                        icon: Home,
                        link: "/admin/unit-rumah",
                    },
                    {
                        title: "Progress Pembangunan",
                        icon: TrendingUp,
                        link: "/admin/progress-pembangunan",
                    },
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
                ],
            },
            {
                title: "Management Logistik",
                icon: Boxes,
                items: [
                    {
                        title: "Permintaan Barang",
                        icon: PackagePlus,
                        link: "/admin/permintaan-barang",
                    },
                    {
                        title: "Sisa Material Lokasi",
                        icon: Warehouse,
                        link: "/admin/sisa-material-lokasi",
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
                ],
            },
            {
                title: "Notifikasi",
                icon: Bell,
                link: "/admin/notifications",
            },
        ],
    },
];

export default pengawasSidebar;
