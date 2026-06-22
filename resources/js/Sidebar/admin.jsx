import {
    Boxes,
    Building2,
    FileText,
    Home,
    LayoutDashboard,
    Wrench,
    Users,
} from "lucide-react";

const adminSidebar = [
    {
        title: "Menu Admin",
        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                link: "/admin/dashboard",
            },
            {
                title: "Management Proyek",
                icon: Boxes,
                items: [
                    {
                        title: "Kapling / Unit",
                        icon: Home,
                        link: "/admin/unit-rumah",
                    },
                    {
                        title: "Inventaris Aset",
                        icon: Wrench,
                        link: "/admin/inventaris-aset",
                    },
                ],
            },
            {
                title: "Master Data",
                icon: Building2,
                items: [
                    {
                        title: "Perumahan",
                        icon: Building2,
                        link: "/admin/management/perumahan",
                    },
                    {
                        title: "Master Document Customer",
                        icon: FileText,
                        link: "/admin/management/master-dokumen-customer",
                    },
                    {
                        title: "Users",
                        icon: Users,
                        link: "/admin/management/user",
                    },
                ],
            },
        ],
    },
];

export default adminSidebar;
