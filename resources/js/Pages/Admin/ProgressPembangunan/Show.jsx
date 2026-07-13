import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Image as ImageIcon } from 'lucide-react';
import { Button } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const number = (value) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });

function Info({ label, value }) {
    return (
        <div className="rounded-lg border border-silver-deep/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
            <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">{label}</p>
            <p className="mt-1 font-extrabold">{value || '-'}</p>
        </div>
    );
}

function TextBlock({ label, value }) {
    return (
        <div>
            <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">{label}</p>
            <div className="mt-2 min-h-16 rounded-lg border border-silver-deep/60 bg-white/70 p-4 text-sm leading-6 dark:border-white/10 dark:bg-white/5">
                {value || '-'}
            </div>
        </div>
    );
}

function Section({ title, children }) {
    return (
        <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                <h3 className="font-display text-xl font-extrabold">{title}</h3>
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

export default function Show({ title, indexUrl, progress = {}, siteReport = null, materialRequests = [], materialUsages = [] }) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Detail Progress</p>
                            <h2 className="mt-2 font-display text-3xl font-extrabold">{progress.nama_progress || title}</h2>
                            <p className="mt-2 leading-7 text-ink-soft dark:text-white/60">
                                {progress.perumahan} - {progress.unit}
                            </p>
                        </div>
                        <Button type="button" variant="outline" onClick={() => router.visit(indexUrl)}>
                            <ArrowLeft size={16} />
                            Kembali
                        </Button>
                    </div>
                </section>

                <Section title="Progress Pembangunan">
                    <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
                        <Info label="Tanggal" value={progress.tanggal} />
                        <Info label="Jadwal Kerja" value={progress.jadwal} />
                        <Info label="Tahap Jadwal" value={progress.tahap_jadwal} />
                        <Info label="Item Pekerjaan" value={progress.item_pekerjaan} />
                        <Info label="Target Item" value={`${number(progress.target_item)}%`} />
                        <Info label="Progress Input" value={`${number(progress.progress)}%`} />
                        <Info label="Kontribusi Total" value={`${number(progress.kontribusi_total)}%`} />
                        <Info label="Approval" value={progress.approval_label} />
                        <Info label="Status Record" value={progress.record_status} />
                        <Info label="Input Oleh" value={progress.input_oleh} />
                        <Info label="Dibuat Oleh" value={progress.created_by_name} />
                        <Info label="Disetujui Oleh" value={progress.approved_by} />
                    </div>
                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <TextBlock label="Keterangan Progress" value={progress.keterangan} />
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">Bukti Foto</p>
                            <div className="mt-2 rounded-lg border border-silver-deep/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                {progress.foto_url ? (
                                    <a className="inline-flex items-center gap-2 font-extrabold text-emerald-700 underline decoration-dotted underline-offset-4 dark:text-emerald-300" href={progress.foto_url} target="_blank" rel="noreferrer">
                                        <ImageIcon size={17} />
                                        Lihat Foto Progress
                                    </a>
                                ) : '-'}
                            </div>
                        </div>
                    </div>
                </Section>

                <Section title="Laporan Lapangan">
                    {siteReport ? (
                        <div className="grid gap-5">
                            <div className="grid gap-4 md:grid-cols-4">
                                <Info label="Kode Laporan" value={siteReport.kode_laporan} />
                                <Info label="Cuaca" value={siteReport.cuaca} />
                                <Info label="Jumlah Pekerja" value={siteReport.jumlah_pekerja} />
                                <Info label="Kontraktor / Tukang" value={siteReport.kontraktor} />
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <TextBlock label="Pekerjaan Selesai" value={siteReport.pekerjaan_selesai} />
                                <TextBlock label="Pekerjaan Tertahan" value={siteReport.pekerjaan_tertahan} />
                                <TextBlock label="Kendala" value={siteReport.kendala} />
                                <TextBlock label="Koordinasi" value={siteReport.koordinasi} />
                                <TextBlock label="Rencana Berikutnya" value={siteReport.rencana_berikutnya} />
                                <div>
                                    <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">Lampiran</p>
                                    <div className="mt-2 rounded-lg border border-silver-deep/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                        {siteReport.lampiran_url ? (
                                            <a className="font-extrabold text-emerald-700 underline decoration-dotted underline-offset-4 dark:text-emerald-300" href={siteReport.lampiran_url} target="_blank" rel="noreferrer">
                                                Lihat Lampiran
                                            </a>
                                        ) : '-'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <p className="font-bold text-ink-soft dark:text-white/50">Belum ada laporan lapangan pada progress ini.</p>
                    )}
                </Section>

                <Section title="Permintaan Material Terkait">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['Kode', 'Tanggal', 'Keluar Gudang', 'Status', 'Material'].map((column) => <th className="px-4 py-3 font-extrabold" key={column}>{column}</th>)}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {materialRequests.map((request) => (
                                    <tr key={request.id}>
                                        <td className="px-4 py-3 font-extrabold">{request.kode_request}</td>
                                        <td className="px-4 py-3">{request.tanggal}</td>
                                        <td className="px-4 py-3">{request.issued_at || '-'}</td>
                                        <td className="px-4 py-3 font-bold">{request.status}</td>
                                        <td className="px-4 py-3">
                                            {(request.items ?? []).map((item) => (
                                                <div key={`${request.id}-${item.kode}-${item.material}`} className="font-semibold">
                                                    {item.kode} - {item.material}: {number(item.qty)} {item.satuan}
                                                </div>
                                            ))}
                                        </td>
                                    </tr>
                                ))}
                                {materialRequests.length === 0 && (
                                    <tr><td className="px-4 py-8 text-center font-bold text-ink-soft dark:text-white/45" colSpan={5}>Tidak ada permintaan material terkait.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Section>

                <Section title="Pemakaian Material & HPP/RAB">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['Kode Pemakaian', 'Tanggal', 'Material', 'Jumlah', 'Item HPP/RAB Unit'].map((column) => <th className="px-4 py-3 font-extrabold" key={column}>{column}</th>)}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {materialUsages.flatMap((usage) => (usage.items ?? []).map((item, index) => (
                                    <tr key={`${usage.id}-${index}`}>
                                        <td className="px-4 py-3 font-extrabold">{usage.kode_pemakaian}</td>
                                        <td className="px-4 py-3">{usage.tanggal}</td>
                                        <td className="px-4 py-3">{item.kode} - {item.material}</td>
                                        <td className="px-4 py-3 font-bold">{number(item.qty)} {item.satuan}</td>
                                        <td className="px-4 py-3">{item.hpp_item}</td>
                                    </tr>
                                )))}
                                {materialUsages.length === 0 && (
                                    <tr><td className="px-4 py-8 text-center font-bold text-ink-soft dark:text-white/45" colSpan={5}>Tidak ada pemakaian material pada progress ini.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Section>
            </div>
        </>
    );
}

Show.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Detail Progress Pembangunan'}>{page}</AdminLayout>;
