import {
    Bell,
    Boxes,
    CalendarClock,
    ClipboardCheck,
    ClipboardList,
    FileText,
    Home,
    LayoutDashboard,
    PackagePlus,
    RotateCcw,
    ShieldCheck,
    Siren,
    UserCheck,
    UsersRound,
    TrendingUp,
    Warehouse,
    Wrench,
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
                title: "Manajemen Proyek",
                icon: Home,
                items: [
                    {
                        title: "Kapling / Unit",
                        icon: Home,
                        link: "/admin/unit-rumah",
                    },
                    {
                        title: "Jadwal Lapangan",
                        icon: CalendarClock,
                        link: "/admin/jadwal-lapangan",
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
                        title: "Tenaga Kerja & Alat",
                        icon: UsersRound,
                        link: "/admin/pengawasan/tenaga-kerja-alat",
                    },
                    {
                        title: "Kontrol Kualitas",
                        icon: ShieldCheck,
                        link: "/admin/kontrol-kualitas",
                    },
                    {
                        title: "Defect / Punch List",
                        icon: ClipboardList,
                        link: "/admin/pengawasan/defect",
                    },
                    {
                        title: "Opname Kontraktor",
                        icon: ClipboardCheck,
                        link: "/admin/pengawasan/opname-kontraktor",
                    },
                    {
                        title: "Perubahan Pekerjaan",
                        icon: Wrench,
                        link: "/admin/pengawasan/perubahan-pekerjaan",
                    },
                    {
                        title: "K3 / Safety",
                        icon: Siren,
                        link: "/admin/pengawasan/k3",
                    },
                    {
                        title: "Serah Terima Internal",
                        icon: UserCheck,
                        link: "/admin/pengawasan/serah-terima-internal",
                    },
                ],
            },
            {
                title: "Manajemen Marketing",
                icon: UsersRound,
                items: [
                    {
                        title: "Campaign & Promosi",
                        icon: ClipboardCheck,
                        link: "/admin/marketing/operasional/campaign",
                        permission: "marketing.campaign.manage",
                    },
                ],
            },
            {
                title: "Manajemen Logistik",
                icon: Boxes,
                items: [
                    {
                        title: "Permintaan Barang",
                        icon: PackagePlus,
                        link: "/admin/permintaan-barang",
                        permission: "material-request.view",
                    },
                    {
                        title: "Sisa Material Lokasi",
                        icon: Warehouse,
                        link: "/admin/sisa-material-lokasi",
                        permission: "site-material-stock.view",
                    },
                    {
                        title: "Pemakaian Material",
                        icon: Boxes,
                        link: "/admin/pemakaian-material",
                        permission: "material-usage.view",
                    },
                    {
                        title: "Inventaris Aset",
                        icon: Wrench,
                        link: "/admin/inventaris-aset",
                        permission: "asset-inventory.view",
                    },
                    {
                        title: "Pengembalian Stok",
                        icon: RotateCcw,
                        link: "/admin/pengembalian-material",
                        permission: "material-return.view",
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
