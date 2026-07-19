import { Link, usePage } from '@inertiajs/react';
import { Phone } from 'lucide-react';
import { assets, navItems } from '../Data/site';

export default function GuestLayout({ children }) {
    const { url } = usePage();

    return (
        <div className="guest-theme-scope min-h-screen overflow-x-hidden">
            <header className="sticky top-0 z-50 border-b border-ink/30 bg-silver-soft/90 backdrop-blur-xl">
                <div className="mx-auto flex min-h-20 w-[min(1180px,calc(100%-32px))] flex-col gap-4 py-4 lg:flex-row lg:items-center lg:justify-between lg:gap-6 lg:py-0">
                    <Link href="/" className="flex items-center gap-3">
                        <img className="h-12 w-12 rounded-lg border border-gold/50 bg-white object-cover" src={assets.logo} alt="Logo Sidratul Muntaha" />
                        <span className="font-display text-xl font-extrabold text-ink">
                            Sidratul Muntaha
                            <small className="block font-sans text-xs font-bold text-ink-soft">PT Ali Properti Indonesia</small>
                        </span>
                    </Link>

                    <nav className="flex gap-1 overflow-x-auto text-sm font-extrabold text-ink/75" aria-label="Navigasi utama">
                        {navItems.map(([label, href]) => (
                            <Link
                                className={`shrink-0 rounded-lg px-3 py-2 transition ${
                                    url === href ? 'bg-champagne text-gold-deep' : 'hover:bg-champagne/70 hover:text-gold-deep'
                                }`}
                                href={href}
                                key={href}
                            >
                                {label}
                            </Link>
                        ))}
                    </nav>

                    <Link
                        className="hidden min-h-11 items-center justify-center gap-2 rounded-lg border border-gold-deep bg-gradient-to-br from-champagne via-gold to-gold-deep px-5 text-sm font-extrabold text-[#241a08] transition hover:-translate-y-0.5 lg:inline-flex"
                        href="/kontak"
                    >
                        <Phone size={17} /> Hubungi Marketing
                    </Link>
                </div>
            </header>

            <main>{children}</main>

            <footer className="bg-[radial-gradient(circle_at_12%_0%,rgba(216,186,114,0.28),transparent_22rem),linear-gradient(135deg,#1d2329,#11161b)] py-12 text-white/72">
                <div className="mx-auto grid w-[min(1180px,calc(100%-32px))] gap-8 md:grid-cols-[1.2fr_0.8fr_0.8fr]">
                    <div>
                        <strong className="font-display text-2xl text-champagne">Sidratul Muntaha</strong>
                        <p className="mt-3 leading-7">Website pemasaran resmi untuk menampilkan rumah, brosur, site plan, denah, dan video promosi secara rapi.</p>
                    </div>
                    <div>
                        <strong className="text-champagne">Navigasi</strong>
                        <p className="mt-3 leading-7">Profil<br />Tipe Rumah<br />Galeri<br />Kontak</p>
                    </div>
                    <div>
                        <strong className="text-champagne">Marketing</strong>
                        <p className="mt-3 leading-7">Survei lokasi, cek unit tersedia, konsultasi pembelian, dan permintaan brosur digital.</p>
                    </div>
                </div>
            </footer>
        </div>
    );
}
