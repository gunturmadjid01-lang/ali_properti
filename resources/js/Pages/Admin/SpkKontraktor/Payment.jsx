import { Head, router } from '@inertiajs/react';
import { Banknote, Eye, Send, Search } from 'lucide-react';
import { useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, Input, Modal } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

export default function Payment({ title, description, baseUrl, pageUrl, rows, filters = {}, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [detail, setDetail] = useState(null);

    const requestPayment = (row, payment) => {
        let payload = {};

        if (!row.hpp_plan_exists) {
            const confirmed = window.confirm(
                `Rencana HPP ${row.hpp_plan_label} belum diisi. Pembayaran tetap dicatat sebagai realisasi. Tetap ajukan termin ini?`,
            );
            if (!confirmed) return;
            payload = { confirm_without_hpp: true };
        }

        router.post(`${baseUrl}/${row.id}/payments/${payment.id}/request`, payload, { preserveScroll: true });
    };

    const releasePayment = (row, payment) => {
        if (!window.confirm(`Catat pembayaran termin ${payment.termin_ke} sebesar ${money(payment.nominal)}?`)) return;
        router.post(`${baseUrl}/${row.id}/payments/${payment.id}/release`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Administrasi Pembayaran Proyek</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(pageUrl, { search }, { preserveScroll: true, preserveState: true, replace: true });
                        }}
                    >
                        <Input className="md:max-w-md" label="Cari Pembayaran SPK" value={search} placeholder="Nomor SPK, kontraktor, atau pekerjaan..." onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>

                    <div className="grid gap-4 border-t border-silver-deep/60 p-5 dark:border-white/10">
                        {rows.data.map((row) => (
                            <article className="rounded-lg border border-silver-deep/70 bg-white/70 p-5 dark:border-white/10 dark:bg-white/5" key={row.id}>
                                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                    <div className="grid gap-1">
                                        <p className="text-xs font-extrabold uppercase tracking-wider text-ink-soft">{row.nomor_spk}</p>
                                        <h3 className="text-xl font-extrabold">{row.judul_pekerjaan}</h3>
                                        <p className="font-semibold">{row.kontraktor} - {row.perumahan} / {row.unit}</p>
                                        <p className="text-sm text-ink-soft">Total SPK {money(row.nilai_kontrak)} | {row.status} | {row.record_status_label}</p>
                                        <p className={`text-sm font-bold ${row.hpp_plan_exists ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300'}`}>
                                            {row.hpp_plan_exists ? `Rencana HPP ${row.hpp_plan_label}: ${money(row.hpp_plan_total)}` : `Rencana HPP ${row.hpp_plan_label} belum diisi.`}
                                        </p>
                                    </div>
                                    <Button type="button" variant="outline" onClick={() => setDetail(row)}><Eye size={16} /> Detail SPK</Button>
                                </div>

                                <div className="mt-5 grid gap-3">
                                    {row.payments.map((payment) => (
                                        <div className="flex flex-col gap-3 rounded-lg border border-silver-deep/60 p-4 dark:border-white/10 lg:flex-row lg:items-center lg:justify-between" key={payment.id}>
                                            <div>
                                                <p className="font-extrabold">Termin {payment.termin_ke} - {money(payment.nominal)}</p>
                                                <p className="text-sm text-ink-soft">
                                                    Jatuh tempo {payment.tanggal_jatuh_tempo || '-'} | Bayar {payment.tanggal_pembayaran || '-'} | {payment.status_label}
                                                </p>
                                                {payment.opname && <p className="text-sm font-semibold">Opname: {payment.opname}</p>}
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {permissions.canRequestPayment && payment.status === 'menunggu_pengajuan' && (
                                                    <Button type="button" variant="outline" onClick={() => requestPayment(row, payment)}>
                                                        <Send size={16} /> Ajukan Pembayaran
                                                    </Button>
                                                )}
                                                {permissions.canReleasePayment && payment.status === 'menunggu_pembayaran_keuangan' && (
                                                    <Button type="button" onClick={() => releasePayment(row, payment)}>
                                                        <Banknote size={16} /> Catat Pembayaran
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </article>
                        ))}

                        {rows.data.length === 0 && <p className="py-10 text-center font-bold text-ink-soft">Belum ada termin pembayaran SPK.</p>}
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

            <Modal
                open={Boolean(detail)}
                onClose={() => setDetail(null)}
                title={detail ? `Detail ${detail.nomor_spk}` : 'Detail SPK'}
                footer={<Button type="button" variant="outline" onClick={() => setDetail(null)}>Tutup</Button>}
            >
                {detail && (
                    <div className="grid gap-4 text-sm">
                        <div className="grid gap-3 rounded-lg border border-silver-deep/60 p-4 dark:border-white/10 md:grid-cols-2">
                            <p><b>Kontraktor:</b> {detail.kontraktor}</p>
                            <p><b>Pekerjaan:</b> {detail.judul_pekerjaan}</p>
                            <p><b>Jenis:</b> {String(detail.jenis_pekerjaan).replaceAll('_', ' ')}</p>
                            <p><b>Lokasi:</b> {detail.perumahan} / {detail.unit}</p>
                            <p><b>Tanggal SPK:</b> {detail.tanggal_spk || '-'}</p>
                            <p><b>Periode:</b> {detail.tanggal_mulai || '-'} s/d {detail.tanggal_selesai || '-'}</p>
                            <p><b>Nilai dasar:</b> {money(detail.nilai_kontrak_dasar)}</p>
                            <p><b>Total SPK:</b> {money(detail.nilai_kontrak)}</p>
                            <p><b>Status:</b> {detail.status} / {detail.record_status_label}</p>
                        </div>
                        <div className="rounded-lg border border-silver-deep/60 p-4 dark:border-white/10">
                            <p className="font-extrabold">Lingkup Pekerjaan</p>
                            <p className="mt-1 whitespace-pre-wrap text-ink-soft">{detail.lingkup_pekerjaan || '-'}</p>
                        </div>
                        <div className="grid gap-3">
                            <p className="font-extrabold">Rincian Termin</p>
                            {detail.payments.map((payment) => (
                                <div className="rounded-lg border border-silver-deep/60 p-4 dark:border-white/10" key={payment.id}>
                                    <p className="font-extrabold">Termin {payment.termin_ke} - {money(payment.nominal)}</p>
                                    <p>Jatuh tempo: {payment.tanggal_jatuh_tempo || '-'}</p>
                                    <p>Tanggal bayar: {payment.tanggal_pembayaran || '-'}</p>
                                    <p>Status: {payment.status_label}</p>
                                    <p>Keterangan: {payment.keterangan || '-'}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </Modal>
        </>
    );
}

Payment.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Pembayaran SPK'}>{page}</AdminLayout>;
