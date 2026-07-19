import {
    Bath,
    BedDouble,
    Building2,
    Home,
    LandPlot,
    MapPin,
    ShieldCheck,
    Sparkles,
    Trees,
} from 'lucide-react';

const storage = (path) => `/media/${path.split('/').map(encodeURIComponent).join('/')}`;

export const assets = {
    logo: '/favicon.ico',
    hero: storage('gambar/WhatsApp Image 2026-06-14 at 02.00.02.jpeg'),
    house: [
        'gambar/WhatsApp Image 2026-06-14 at 02.00.02.jpeg',
        'gambar/WhatsApp Image 2026-06-14 at 02.00.02 (1).jpeg',
        'gambar/WhatsApp Image 2026-06-14 at 02.00.02 (2).jpeg',
        'gambar/WhatsApp Image 2026-06-14 at 02.00.03.jpeg',
        'gambar/WhatsApp Image 2026-06-14 at 02.00.03 (1).jpeg',
        'gambar/WhatsApp Image 2026-06-14 at 02.00.03 (2).jpeg',
        'gambar/WhatsApp Image 2026-06-14 at 02.00.04.jpeg',
        'gambar/WhatsApp Image 2026-06-14 at 02.00.04 (1).jpeg',
        'gambar/WhatsApp Image 2026-06-14 at 02.00.04 (2).jpeg',
    ].map(storage),
    brochures: ['brosur/brosur_persegi.jpeg', 'brosur/brosur_potrait1.jpeg', 'brosur/brosur_potrait2.jpeg'].map(storage),
    plans: [
        storage('dena/Site Plan.png'),
        storage('dena/DENAH PERUMAHAN SIDRATUL MUNTAHA_potrait.png'),
        storage('dena/Dena_3d_real.jpeg'),
    ],
    videos: [
        'video/WhatsApp Video 2026-06-14 at 02.00.07.mp4',
        'video/WhatsApp Video 2026-06-14 at 02.00.07 (1).mp4',
        'video/WhatsApp Video 2026-06-14 at 02.00.08.mp4',
        'video/WhatsApp Video 2026-06-14 at 02.00.08 (1).mp4',
        'video/WhatsApp Video 2026-06-14 at 02.00.08 (2).mp4',
        'video/WhatsApp Video 2026-06-14 at 02.00.09.mp4',
        'video/WhatsApp Video 2026-06-14 at 02.00.09 (1).mp4',
        'video/WhatsApp Video 2026-06-14 at 02.00.09 (2).mp4',
    ].map(storage),
};

export const navItems = [
    ['Beranda', '/'],
    ['Profil', '/profil'],
    ['Tipe Rumah', '/tipe-rumah'],
    ['Galeri', '/galeri'],
    ['Kontak', '/kontak'],
];

export const highlights = [
    {
        title: 'Lingkungan tertata',
        desc: 'Kawasan perumahan dibuat rapi untuk keluarga yang mencari hunian tenang, mudah dirawat, dan nyaman ditempati.',
        icon: Trees,
    },
    {
        title: 'Nilai investasi',
        desc: 'Visual rumah, site plan, dan brosur membantu calon pembeli menilai potensi properti dengan lebih percaya diri.',
        icon: ShieldCheck,
    },
    {
        title: 'Tampilan elegan',
        desc: 'Fasad rumah dipresentasikan dengan gaya silver dan champagne gold agar citra brand terasa premium.',
        icon: Sparkles,
    },
];

export const units = [
    {
        name: 'Rumah Keluarga Sidratul Muntaha',
        image: assets.house[1],
        desc: 'Rumah tapak untuk keluarga muda dengan ruang yang efisien, fasad bersih, dan teras depan yang mudah ditata.',
        specs: ['2 kamar tidur', '1 kamar mandi', 'Ruang keluarga', 'Carport depan'],
    },
    {
        name: 'Unit Siap Huni',
        image: assets.house[4],
        desc: 'Pilihan unit untuk pembeli yang ingin segera menyiapkan tempat tinggal atau aset sewa keluarga.',
        specs: ['Bangunan compact', 'Sirkulasi baik', 'Area servis', 'Akses jalan perumahan'],
    },
    {
        name: 'Unit Investasi',
        image: assets.house[7],
        desc: 'Rumah dengan tampilan rapi untuk kebutuhan investasi jangka panjang di kawasan hunian berkembang.',
        specs: ['Layout efisien', 'Teras teduh', 'Material rapi', 'Mudah dipasarkan ulang'],
    },
];

export const specs = [
    ['Kamar Tidur', 'Ruang privat untuk keluarga inti.', BedDouble],
    ['Kamar Mandi', 'Area fungsional yang praktis dirawat.', Bath],
    ['Lahan Efisien', 'Komposisi bangunan dan lahan untuk aktivitas harian.', LandPlot],
    ['Rumah Tapak', 'Hunian keluarga dengan akses langsung ke lingkungan.', Home],
    ['Kawasan', 'Site plan membantu pembeli memahami posisi unit.', MapPin],
    ['Developer', 'Dikelola sebagai materi pemasaran PT Ali Properti Indonesia.', Building2],
];
