import {
    BadgeCheck,
    Banknote,
    Boxes,
    Building2,
    CalendarClock,
    ClipboardCheck,
    FileCheck2,
    FileText,
    Home,
    Landmark,
    LayoutDashboard,
    ReceiptText,
    RotateCcw,
    Settings,
    ShieldCheck,
    UserCog,
    Users,
} from "lucide-react";

const ownerSidebar = [
    {
        title: "Menu Owner",
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
                        title: "Daftar Approval",
                        icon: ClipboardCheck,
                        link: "/admin/approval",
                    },
                    {
                        title: "Permintaan Pembelian Gudang",
                        icon: Boxes,
                        link: "/admin/daftar-permintaan-pembelian",
                    },
                    {
                        title: "Approval SPK",
                        icon: FileText,
                        link: "/admin/spk-kontraktor/approval",
                    },
                    {
                        title: "Approval SPR",
                        icon: FileText,
                        link: "/admin/marketing/spr",
                    },
                    {
                        title: "Setting Approval",
                        icon: BadgeCheck,
                        link: "/admin/approval/settings",
                    },
                    {
                        title: "Approval Material",
                        icon: Boxes,
                        link: "/admin/permintaan-barang",
                    },
                ],
            },
            {
                title: "Manajemen Proyek",
                icon: Boxes,
                items: [
                    {
                        title: "Kapling / Unit",
                        icon: Home,
                        link: "/admin/unit-rumah",
                    },
                    {
                        title: "Kontraktor",
                        icon: Users,
                        link: "/admin/kontraktor",
                    },
                    {
                        title: "SPK Kontraktor",
                        icon: FileText,
                        items: [
                            {
                                title: "Input SPK",
                                icon: FileText,
                                link: "/admin/spk-kontraktor",
                            },
                            {
                                title: "Pembayaran SPK",
                                icon: ReceiptText,
                                link: "/admin/spk-kontraktor",
                            },
                        ],
                    },
                    {
                        title: "Progress Pembangunan",
                        icon: Boxes,
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
                title: "Transaksi Pengeluaran",
                icon: ReceiptText,
                items: [
                    {
                        title: "Permintaan Pembelian Gudang",
                        icon: ClipboardCheck,
                        link: "/admin/daftar-permintaan-pembelian",
                    },
                    {
                        title: "Pembelian Barang",
                        icon: ReceiptText,
                        link: "/admin/pembelian-material",
                    },
                ],
            },
            {
                title: "Setting Master Data",
                icon: Settings,
                items: [
                    {
                        title: "User Management",
                        icon: Users,
                        items: [
                            {
                                title: "Users",
                                icon: Users,
                                link: "/admin/management/user",
                            },
                            {
                                title: "Role Permission",
                                icon: ShieldCheck,
                                link: "/admin/management/role-permission",
                            },
                        ],
                    },
                    {
                        title: "Profil Perusahaan",
                        icon: Building2,
                        link: "/admin/management/cabang-perusahaan",
                    },
                    {
                        title: "Setting Perumahan",
                        icon: Building2,
                        items: [
                            {
                                title: "Perumahan",
                                icon: Building2,
                                link: "/admin/management/perumahan",
                            },
                            {
                                title: "Master Dokument",
                                icon: FileText,
                                link: "/admin/management/dokumen-legalitas-rumah",
                            },
                            {
                                title: "Manage HPP Perumahan",
                                icon: FileCheck2,
                                link: "/admin/management/kelompok-hpp",
                            },
                        ],
                    },
                    {
                        title: "Master Document Customer",
                        icon: FileText,
                        link: "/admin/management/master-dokumen-customer",
                    },
                    {
                        title: "Management Bank",
                        icon: Banknote,
                        items: [
                            {
                                title: "Master Bank Perusahaan",
                                icon: Landmark,
                                link: "/admin/management/master-bank",
                            },
                            {
                                title: "Mutasi & Saldo Rekening",
                                icon: ReceiptText,
                                link: "/admin/rekening-bank",
                            },
                            {
                                title: "Bank Kredit",
                                icon: Banknote,
                                link: "/admin/management/bank-kredit",
                            },
                        ],
                    },
                    {
                        title: "Tipe Pemasukan / Pengeluaran",
                        icon: Banknote,
                        link: "/admin/management/tipe-post",
                    },
                ],
            },
            {
                title: "Management Gudang",
                icon: Boxes,
                items: [
                    {
                        title: "Gudang",
                        icon: Building2,
                        link: "/admin/gudang",
                    },
                    {
                        title: "Master Material",
                        icon: Boxes,
                        link: "/admin/master-material",
                    },
                    {
                        title: "Harga Material",
                        icon: Banknote,
                        link: "/admin/harga-material",
                    },
                    {
                        title: "Stok Material",
                        icon: Boxes,
                        link: "/admin/stok-material",
                    },
                    {
                        title: "Stok Opname",
                        icon: Boxes,
                        link: "/admin/stok-material",
                    },
                    {
                        title: "Permintaan Barang",
                        icon: ClipboardCheck,
                        link: "/admin/permintaan-barang",
                    },
                    {
                        title: "Mutasi Gudang",
                        icon: Boxes,
                        link: "/admin/logistik",
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
        ],
    },
];

export default ownerSidebar;
