import {
    ClipboardCheck,
    CalendarClock,
    FileText,
    LayoutDashboard,
    ReceiptText,
    ShieldCheck,
    TrendingUp,
} from "lucide-react";

const managerSidebar = [
    {
        title: "Menu Manager",
        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                link: "/admin/dashboard",
            },
            {
                title: "Approval",
                icon: ClipboardCheck,
                items: [
                    {
                        title: "Approval SPK",
                        icon: FileText,
                        link: "/admin/spk-kontraktor/approval",
                    },
                    {
                        title: "Approval SPR",
                        icon: ReceiptText,
                        link: "/admin/marketing/spr",
                    },
                    {
                        title: "Approval Pengeluaran",
                        icon: ShieldCheck,
                        items: [
                            {
                                title: "Daftar Approval",
                                icon: ShieldCheck,
                                link: "/admin/approval",
                            },
                        ],
                    },
                    {
                        title: "Permintaan Pembelian Gudang",
                        icon: ReceiptText,
                        link: "/admin/daftar-permintaan-pembelian",
                    },
                ],
            },
            {
                title: "Manajemen Proyek",
                icon: FileText,
                items: [
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
        ],
    },
];

export default managerSidebar;
