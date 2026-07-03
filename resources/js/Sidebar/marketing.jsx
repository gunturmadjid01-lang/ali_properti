import {
    Banknote,
    BellRing,
    ClipboardCheck,
    Compass,
    FileText,
    KeyRound,
    LayoutDashboard,
    MessageCircle,
    ReceiptText,
    Target,
    UserPlus,
    Users,
} from "lucide-react";

const marketingSidebar = [
    {
        title: "Pemasaran",
        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                link: "/admin/dashboard",
            },
            {
                title: "Prospek & Pelanggan",
                icon: Users,
                items: [
                    {
                        title: "Data Pelanggan",
                        icon: UserPlus,
                        link: "/admin/marketing/calon-konsumen",
                        permission: "customer.view",
                    },
                    {
                        title: "Tindak Lanjut Pelanggan",
                        icon: MessageCircle,
                        link: "/admin/marketing/jejak-follow-up",
                        permission: "customer.follow-up",
                    },
                    {
                        title: "Jadwal Kunjungan",
                        icon: Compass,
                        link: "/admin/marketing/jadwal-survey",
                        permission: "marketing.reminder.manage",
                    },
                    {
                        title: "Pengingat Tindak Lanjut",
                        icon: BellRing,
                        link: "/admin/marketing/operasional/reminder",
                        badgeKey: "reminder_follow_up",
                        permission: "marketing.reminder.manage",
                    },
                ],
            },
            {
                title: "Transaksi Penjualan",
                icon: ReceiptText,
                items: [
                    {
                        title: "SPR",
                        icon: FileText,
                        link: "/admin/marketing/spr",
                        permission: "booking.view",
                    },
                    {
                        title: "Pembayaran Awal",
                        icon: Banknote,
                        link: "/admin/marketing/pembayaran-spr",
                        permission: "spr-payment.view",
                    },
                    {
                        title: "Pengajuan KPR",
                        icon: ClipboardCheck,
                        link: "/admin/kpr",
                        permission: "kpr.view",
                    },
                    {
                        title: "Akad",
                        icon: FileText,
                        link: "/admin/kpr/proses/akad",
                        permission: "kpr.view",
                    },
                    {
                        title: "Serah Terima",
                        icon: KeyRound,
                        link: "/admin/kpr/proses/serah_terima",
                        permission: "kpr.view",
                    },
                ],
            },
            {
                title: "Closing & Harga",
                icon: Target,
                items: [
                    {
                        title: "Unit Tersedia",
                        icon: ClipboardCheck,
                        link: "/admin/marketing/tools/unit-stock",
                        permission: "unit-stock.view",
                    },
                    {
                        title: "Daftar Harga Aktif",
                        icon: ReceiptText,
                        link: "/admin/marketing/tools/pricelist",
                        permission: "pricelist.view",
                    },
                    {
                        title: "Simulasi Cicilan",
                        icon: Banknote,
                        link: "/admin/marketing/tools/simulasi-pembayaran",
                        permission: "payment-simulation.view",
                    },
                ],
            },
        ],
    },
];

export default marketingSidebar;
