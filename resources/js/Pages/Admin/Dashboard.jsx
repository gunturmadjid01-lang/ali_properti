import { Head } from '@inertiajs/react';
import { Building2, Eye, Home, Image, Phone, TrendingUp, Video } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';

const stats = [
    ['Total Unit', '24', Home],
    ['Foto Galeri', '9', Image],
    ['Video Promosi', '8', Video],
    ['Prospek Bulan Ini', '36', Phone],
];

function Dashboard() {
    return (
        <>
            <Head title="Admin Dashboard" />
            <div className="grid gap-6">
                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="grid gap-5 lg:grid-cols-[1fr_0.38fr] lg:items-center">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">Ringkasan Pemasaran</p>
                            <h2 className="mt-1 text-xl font-extrabold leading-snug">Dashboard Admin</h2>
                            <p className="mt-1 max-w-2xl text-sm leading-6 text-ink-soft dark:text-white/62">Kelola unit rumah, galeri, video promosi, brosur, dan data prospek calon pembeli.</p>
                        </div>
                        <div className="rounded-lg border border-white/80 bg-white/75 p-4 shadow-[0_16px_44px_rgba(31,37,43,0.08)] dark:border-white/10 dark:bg-white/8">
                            <Building2 className="text-ink-soft" size={22} />
                            <strong className="mt-3 block text-base font-extrabold">Sidratul Muntaha</strong>
                            <p className="mt-2 text-sm leading-6 text-ink-soft dark:text-white/58">Panel internal PT Ali Properti Indonesia.</p>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {stats.map(([label, value, Icon]) => (
                        <article className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8" key={label}>
                            <div className="flex items-center justify-between">
                                <span className="grid h-11 w-11 place-items-center rounded-lg bg-silver text-ink-soft"><Icon size={21} /></span>
                                <TrendingUp size={18} className="text-ink-soft" />
                            </div>
                            <strong className="mt-4 block text-2xl">{value}</strong>
                            <p className="mt-1 text-sm font-bold text-ink-soft dark:text-white/58">{label}</p>
                        </article>
                    ))}
                </section>

                <section className="grid gap-6 xl:grid-cols-[1.08fr_0.92fr]">
                    <div className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-extrabold">Aktivitas Prospek</h3>
                            <Eye size={20} className="text-ink-soft" />
                        </div>
                        <div className="mt-6 grid gap-3">
                            {['Permintaan brosur dari halaman kontak', 'Pengunjung membuka video promosi', 'Calon pembeli melihat site plan', 'Tim marketing menyiapkan jadwal survei'].map((item, index) => (
                                <div className="flex items-center justify-between rounded-lg bg-silver-soft px-4 py-3 dark:bg-white/8" key={item}>
                                    <span className="font-bold text-ink/78 dark:text-white/70">{item}</span>
                                    <span className="rounded-full bg-silver px-3 py-1 text-xs font-extrabold text-ink-soft">{index + 1}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <h3 className="text-lg font-extrabold">Konten Prioritas</h3>
                        <p className="mt-2 text-sm leading-6 text-ink-soft dark:text-white/60">Bagian ini bisa dikembangkan untuk CRUD unit rumah, galeri, brosur, dan video promosi.</p>
                        <div className="mt-6 grid gap-3">
                            {['Update ketersediaan unit', 'Tambah foto progres rumah', 'Ganti video utama landing page'].map((item) => (
                                <button className="rounded-lg border border-silver-deep/70 bg-silver-soft px-4 py-3 text-left font-extrabold text-ink transition hover:bg-silver dark:bg-white/8 dark:text-white dark:hover:bg-white/12" type="button" key={item}>
                                    {item}
                                </button>
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = (page) => <AdminLayout title="Dashboard Admin">{page}</AdminLayout>;

export default Dashboard;

