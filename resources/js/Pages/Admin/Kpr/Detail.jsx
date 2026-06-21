import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, Lock, PencilLine, PlusCircle, Trash2, Unlock, Upload, XCircle } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Accordion, Button, CurrencyInput, Dropdown, Form, Input, Modal, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function formatBytes(bytes = 0) {
    const value = Number(bytes || 0);
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function HeaderAction({ submission, baseUrl }) {
    const lock = () => router.post(`${baseUrl}/${submission.id}/lock`, {}, { preserveScroll: true });
    const unlock = () => router.post(`${baseUrl}/${submission.id}/unlock`, {}, { preserveScroll: true });

    return (
        <div className="flex flex-wrap gap-2">
            <Button as="a" href={baseUrl} variant="outline">
                <ArrowLeft size={16} /> Kembali
            </Button>
            {submission.record_status === 'locked' ? (
                <Button type="button" variant="outline" onClick={unlock}>
                    <Unlock size={16} /> Unlock
                </Button>
            ) : (
                <Button type="button" variant="outline" onClick={lock}>
                    <Lock size={16} /> Lock
                </Button>
            )}
        </div>
    );
}

function FollowUpForm({ submission, options }) {
    const form = useForm({
        tanggal_follow_up: new Date().toISOString().slice(0, 10),
        metode_follow_up: 'telephone',
        status_kpr: submission.status ?? 'pengumpulan_dokumen',
        hasil_follow_up: '',
        kendala: '',
        tindak_lanjut: '',
        catatan: '',
        rencana_follow_up_at: '',
    });

    useEffect(() => {
        form.setData({
            tanggal_follow_up: new Date().toISOString().slice(0, 10),
            metode_follow_up: 'telephone',
            status_kpr: submission.status ?? 'pengumpulan_dokumen',
            hasil_follow_up: '',
            kendala: '',
            tindak_lanjut: '',
            catatan: '',
            rencana_follow_up_at: '',
        });
    }, [submission.id]);

    const submit = (event) => {
        event.preventDefault();
        form.post(`/admin/kpr/${submission.id}/follow-up`, { preserveScroll: true, onSuccess: () => form.reset('hasil_follow_up', 'kendala', 'tindak_lanjut', 'catatan', 'rencana_follow_up_at') });
    };

    return (
        <form className="grid gap-4 rounded-lg border border-silver-deep/70 bg-white/75 p-4 dark:border-white/10 dark:bg-white/5" onSubmit={submit}>
            <div className="grid gap-4 md:grid-cols-2">
                <Input label="Tanggal Follow Up" type="date" value={form.data.tanggal_follow_up} onChange={(event) => form.setData('tanggal_follow_up', event.target.value)} />
                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Metode Follow Up</span>
                    <Dropdown value={form.data.metode_follow_up} options={options.methodOptions} onChange={(value) => form.setData('metode_follow_up', value)} />
                </div>
                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Status KPR</span>
                    <Dropdown value={form.data.status_kpr} options={options.statusOptions} onChange={(value) => form.setData('status_kpr', value)} />
                </div>
                <Input label="Rencana Follow Up Berikutnya" type="date" value={form.data.rencana_follow_up_at} onChange={(event) => form.setData('rencana_follow_up_at', event.target.value)} />
            </div>
            <div className="grid gap-4 md:grid-cols-3">
                <Textarea label="Hasil Follow Up" value={form.data.hasil_follow_up} onChange={(event) => form.setData('hasil_follow_up', event.target.value)} />
                <Textarea label="Kendala" value={form.data.kendala} onChange={(event) => form.setData('kendala', event.target.value)} />
                <Textarea label="Tindak Lanjut" value={form.data.tindak_lanjut} onChange={(event) => form.setData('tindak_lanjut', event.target.value)} />
            </div>
            <Textarea label="Catatan Follow Up" value={form.data.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    <PlusCircle size={16} /> {form.processing ? 'Menyimpan...' : 'Tambah Follow Up'}
                </Button>
            </div>
        </form>
    );
}

function FollowUpEditModal({ submission, row, options, open, onClose }) {
    const form = useForm({
        tanggal_follow_up: row?.tanggal_follow_up ?? new Date().toISOString().slice(0, 10),
        metode_follow_up: row?.metode_follow_up_key ?? 'telephone',
        status_kpr: row?.status_kpr ?? submission.status ?? 'pengumpulan_dokumen',
        hasil_follow_up: row?.hasil_follow_up ?? '',
        kendala: row?.kendala ?? '',
        tindak_lanjut: row?.tindak_lanjut ?? '',
        catatan: row?.catatan ?? '',
        rencana_follow_up_at: row?.rencana_follow_up_at ?? '',
    });

    useEffect(() => {
        if (!row) {
            return;
        }

        form.setData({
            tanggal_follow_up: row.tanggal_follow_up ?? new Date().toISOString().slice(0, 10),
            metode_follow_up: row.metode_follow_up_key ?? 'telephone',
            status_kpr: row.status_kpr ?? submission.status ?? 'pengumpulan_dokumen',
            hasil_follow_up: row.hasil_follow_up ?? '',
            kendala: row.kendala ?? '',
            tindak_lanjut: row.tindak_lanjut ?? '',
            catatan: row.catatan ?? '',
            rencana_follow_up_at: row.rencana_follow_up_at ?? '',
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [row?.id]);

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const submit = (event) => {
        event.preventDefault();
        form.put(`/admin/kpr/${submission.id}/follow-up/${row.id}`, { preserveScroll: true, onSuccess: close });
    };

    if (!row) {
        return null;
    }

    return (
        <Modal open={open} onClose={close} title={`Edit Follow Up ${submission.kode_kpr}`} size="lg">
            <Form
                collapsible={false}
                className="border-0 bg-transparent p-0 shadow-none dark:bg-transparent"
                contentClassName="md:grid-cols-2"
                onSubmit={submit}
                actions={(
                    <>
                        <Button type="button" variant="outline" onClick={close}>
                            <XCircle size={17} /> Batal
                        </Button>
                        <Button disabled={form.processing} type="submit">
                            <PencilLine size={17} /> {form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    </>
                )}
            >
                <Input label="Tanggal Follow Up" type="date" value={form.data.tanggal_follow_up} error={form.errors.tanggal_follow_up} onChange={(event) => form.setData('tanggal_follow_up', event.target.value)} />
                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Metode Follow Up</span>
                    <Dropdown value={form.data.metode_follow_up} options={options.methodOptions} onChange={(value) => form.setData('metode_follow_up', value)} />
                    {form.errors.metode_follow_up && <span className="text-xs font-bold text-red-600">{form.errors.metode_follow_up}</span>}
                </div>
                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Status KPR</span>
                    <Dropdown value={form.data.status_kpr} options={options.statusOptions} onChange={(value) => form.setData('status_kpr', value)} />
                    {form.errors.status_kpr && <span className="text-xs font-bold text-red-600">{form.errors.status_kpr}</span>}
                </div>
                <Input label="Rencana Berikutnya" type="date" value={form.data.rencana_follow_up_at} error={form.errors.rencana_follow_up_at} onChange={(event) => form.setData('rencana_follow_up_at', event.target.value)} />
                <Textarea className="md:col-span-2" label="Hasil Follow Up" value={form.data.hasil_follow_up} error={form.errors.hasil_follow_up} onChange={(event) => form.setData('hasil_follow_up', event.target.value)} />
                <Textarea className="md:col-span-2" label="Kendala" value={form.data.kendala} error={form.errors.kendala} onChange={(event) => form.setData('kendala', event.target.value)} />
                <Textarea className="md:col-span-2" label="Tindak Lanjut" value={form.data.tindak_lanjut} error={form.errors.tindak_lanjut} onChange={(event) => form.setData('tindak_lanjut', event.target.value)} />
                <Textarea className="md:col-span-2" label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
            </Form>
        </Modal>
    );
}

function BerkasForm({ submission, dokumenOptions }) {
    const emptyRow = () => ({
        dokumen_costumer_id: dokumenOptions[0]?.value ?? '',
        file_upload: null,
        keterangan: '',
        file_name: '',
    });

    const [rows, setRows] = useState([emptyRow()]);
    const form = useForm({ berkas: [] });

    useEffect(() => {
        if (dokumenOptions.length > 0 && !rows[0]?.dokumen_costumer_id) {
            setRows([{
                ...rows[0],
                dokumen_costumer_id: dokumenOptions[0].value,
            }]);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [dokumenOptions.length]);

    useEffect(() => {
        form.setData('berkas', rows.map(({ file_name, ...item }) => item));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [rows]);

    const updateRow = (index, key, value) => {
        setRows((current) => current.map((row, rowIndex) => (rowIndex === index ? { ...row, [key]: value } : row)));
    };

    const addRow = () => {
        setRows((current) => [...current, emptyRow()]);
    };

    const removeRow = (index) => {
        setRows((current) => (current.length === 1 ? current : current.filter((_, rowIndex) => rowIndex !== index)));
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(`/admin/kpr/${submission.id}/berkas`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setRows([emptyRow()]),
        });
    };

    return (
        <form className="grid gap-4 rounded-lg border border-silver-deep/70 bg-white/75 p-4 dark:border-white/10 dark:bg-white/5" onSubmit={submit}>
            <div className="flex items-center justify-between gap-2">
                <div>
                    <p className="text-sm font-extrabold text-ink dark:text-white">Upload Berkas KPR</p>
                    <p className="text-xs font-bold text-ink-soft dark:text-white/55">Klik tombol tambah untuk menambah jenis dokumen. Sekali submit untuk semua file.</p>
                </div>
                <Button type="button" variant="outline" size="sm" onClick={addRow}>
                    <PlusCircle size={16} /> Tambah Dokumen
                </Button>
            </div>

            <div className="grid gap-4">
                {rows.map((row, index) => (
                    <div className="grid gap-4 rounded-xl border border-silver-deep/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5 md:grid-cols-[1.1fr_1fr_1fr_auto]" key={index}>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Jenis Dokumen</span>
                            <Dropdown value={row.dokumen_costumer_id} options={dokumenOptions} onChange={(value) => updateRow(index, 'dokumen_costumer_id', value)} />
                        </div>
                        <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                            <span>File Upload</span>
                            <input
                                className="min-h-11 w-full rounded-lg border border-silver-deep/70 bg-white/85 px-4 py-2.5 font-semibold text-ink outline-none ring-4 ring-transparent transition file:mr-4 file:rounded-md file:border-0 file:bg-ink file:px-4 file:py-2 file:font-extrabold file:text-white hover:file:bg-graphite dark:border-white/10 dark:bg-white/8 dark:text-white"
                                type="file"
                                onChange={(event) => {
                                    const file = event.target.files?.[0] ?? null;
                                    updateRow(index, 'file_upload', file);
                                    updateRow(index, 'file_name', file?.name ?? '');
                                }}
                            />
                            {row.file_name && <span className="text-xs font-bold text-emerald-600 dark:text-emerald-300">{row.file_name}</span>}
                        </label>
                        <Input
                            label="Keterangan"
                            value={row.keterangan}
                            onChange={(event) => updateRow(index, 'keterangan', event.target.value)}
                        />
                        <div className="flex items-end">
                            <Button type="button" variant="ghost" className="text-red-600 dark:text-red-300" onClick={() => removeRow(index)}>
                                <XCircle size={16} />
                            </Button>
                        </div>
                    </div>
                ))}
            </div>

            {form.errors.berkas && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.berkas}</span>}

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    <Upload size={16} /> {form.processing ? 'Mengupload...' : 'Upload Semua Berkas'}
                </Button>
            </div>
        </form>
    );
}

export default function Detail({ title, description, baseUrl, submission, options = {}, dokumenOptions = [] }) {
    const [editFollowUpRow, setEditFollowUpRow] = useState(null);

    const lockFollowUp = (item) => {
        if (!window.confirm(`Lock follow up tanggal ${item.tanggal_follow_up}?`)) {
            return;
        }

        router.post(`/admin/kpr/${submission.id}/follow-up/${item.id}/lock`, {}, { preserveScroll: true });
    };

    const unlockFollowUp = (item) => {
        if (!window.confirm(`Buka lock follow up tanggal ${item.tanggal_follow_up}?`)) {
            return;
        }

        router.post(`/admin/kpr/${submission.id}/follow-up/${item.id}/unlock`, {}, { preserveScroll: true });
    };

    const deleteFollowUp = (item) => {
        if (!window.confirm(`Hapus follow up tanggal ${item.tanggal_follow_up}?`)) {
            return;
        }

        router.delete(`/admin/kpr/${submission.id}/follow-up/${item.id}`, { preserveScroll: true });
    };

    const detailItems = useMemo(() => [
        {
            title: 'Detail Pengajuan',
            content: (
                <div className="grid gap-4">
                    <div className="flex flex-wrap gap-2">
                        <MetaPill label="KPR" value={submission.kode_kpr} />
                        <MetaPill label="SPR" value={submission.kode_spr} />
                        <MetaPill label="Status" value={submission.status_label} />
                        <MetaPill label="Follow Up" value={`${submission.follow_ups_count ?? 0} data`} />
                        <MetaPill label="Berkas" value={`${(submission.spr_berkas_costumers?.length ?? 0) + (submission.berkas_costumers_count ?? 0)} file`} />
                    </div>
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <InfoCard label="Customer" value={submission.customer} />
                        <InfoCard label="No Identitas" value={submission.no_identitas} />
                        <InfoCard label="Telepon" value={submission.telepon} />
                        <InfoCard label="Perumahan" value={submission.perumahan} />
                        <InfoCard label="Unit" value={submission.unit} />
                        <InfoCard label="Bank" value={submission.bank} />
                        <InfoCard label="Nilai Pengajuan" value={money(submission.nilai_pengajuan)} />
                    </div>
                    <div className="rounded-lg border border-silver-deep/70 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                        <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft">Catatan</p>
                        <p className="mt-2 text-sm leading-7 text-ink dark:text-white/75">{submission.catatan ?? '-'}</p>
                    </div>
                </div>
            ),
        },
        {
            title: 'Timeline Tahapan KPR Bank',
            content: (
                <div className="grid gap-3">
                    {(submission.stage_histories ?? []).map((item, index) => (
                        <div className="grid gap-3 rounded-xl border border-silver-deep/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5 md:grid-cols-[48px_1fr_auto]" key={item.id}>
                            <div className="grid h-10 w-10 place-items-center rounded-full bg-ink text-sm font-black text-white dark:bg-white dark:text-ink">{index + 1}</div>
                            <div>
                                <p className="font-extrabold">{item.status_label}</p>
                                <p className="mt-1 text-xs uppercase tracking-[0.12em] text-ink-soft">{item.tahapan.replaceAll('_', ' ')} · {item.user}</p>
                                {item.catatan && <p className="mt-2 text-sm text-ink-soft dark:text-white/60">{item.catatan}</p>}
                            </div>
                            <span className="text-xs font-bold text-ink-soft">{item.tanggal_status}</span>
                        </div>
                    ))}
                    {(submission.stage_histories ?? []).length === 0 && (
                        <p className="text-sm font-bold text-ink-soft dark:text-white/55">Belum ada histori perubahan tahap KPR.</p>
                    )}
                </div>
            ),
        },
        {
            title: 'Akad dan Serah Terima',
            content: (
                <div className="grid gap-4 md:grid-cols-2">
                    {(submission.milestones ?? []).map((item) => (
                        <article className="rounded-xl border border-silver-deep/60 bg-white/70 p-5 dark:border-white/10 dark:bg-white/5" key={item.id}>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-bold uppercase tracking-[0.14em] text-ink-soft">{item.jenis_label}</p>
                                    <h3 className="mt-1 text-lg font-extrabold">{item.tanggal}</h3>
                                </div>
                                <span className="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-extrabold text-emerald-600 dark:text-emerald-300">Tercatat</span>
                            </div>
                            <div className="mt-4 grid gap-2 text-sm text-ink-soft dark:text-white/60">
                                <p><b className="text-ink dark:text-white">Lokasi:</b> {item.lokasi || '-'}</p>
                                <p><b className="text-ink dark:text-white">Nomor Dokumen:</b> {item.nomor_dokumen || '-'}</p>
                                <p><b className="text-ink dark:text-white">Pihak Terkait:</b> {item.pihak_terkait || '-'}</p>
                                <p><b className="text-ink dark:text-white">Input Oleh:</b> {item.created_by}</p>
                                <p><b className="text-ink dark:text-white">Catatan:</b> {item.catatan || '-'}</p>
                            </div>
                            <div className="mt-4 flex flex-wrap gap-2">
                                {item.documents.map((document) => (
                                    <Button as="a" href={document.url} target="_blank" rel="noreferrer" size="sm" variant="outline" key={document.id}>
                                        <Download size={14} /> {document.nama_file}
                                    </Button>
                                ))}
                            </div>
                        </article>
                    ))}
                    {(submission.milestones ?? []).length === 0 && (
                        <p className="text-sm font-bold text-ink-soft dark:text-white/55">Akad dan serah terima belum dicatat.</p>
                    )}
                </div>
            ),
        },
        {
            title: 'Follow Up KPR',
            content: (
                <div className="grid gap-4">
                    <FollowUpForm submission={submission} options={options} />
                    <div className="rounded-lg border border-silver-deep/70 bg-white/75 p-4 text-sm font-semibold text-ink-soft dark:border-white/10 dark:bg-white/5 dark:text-white/60">
                        Setelah tahap <span className="font-extrabold text-ink dark:text-white">Akad</span>, follow up KPR tetap dilakukan di menu ini sampai status <span className="font-extrabold text-ink dark:text-white">Menunggu Serah Terima</span> dan <span className="font-extrabold text-ink dark:text-white">Serah Terima Selesai</span>.
                        {' '}
                        Walaupun data KPR di-lock, penambahan follow up tetap diperbolehkan agar riwayat marketing selalu lengkap.
                    </div>
                    {(submission.follow_ups ?? []).length > 0 ? (
                        <Accordion
                            defaultOpen={0}
                            items={(submission.follow_ups ?? []).map((item, index) => ({
                                title: (
                                    <div className="flex flex-wrap items-center gap-3 py-1">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-ink text-xs font-black text-white dark:bg-white dark:text-graphite">
                                            {index + 1}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-extrabold text-ink dark:text-white">
                                                {item.tanggal_follow_up} | {item.metode_follow_up}
                                            </p>
                                            <p className="mt-1 text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">
                                                Petugas: {item.user}
                                            </p>
                                        </div>
                                        <div className="ml-auto flex flex-wrap items-center gap-2">
                                            <span className="rounded-full bg-silver-soft px-3 py-1 text-xs font-extrabold text-ink-soft dark:bg-white/10 dark:text-white/70">
                                                {item.status_label}
                                            </span>
                                            {item.rencana_follow_up_at && <span className="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-600 dark:text-emerald-300">Rencana: {item.rencana_follow_up_at}</span>}
                                        </div>
                                    </div>
                                ),
                                content: (
                                    <div className="grid gap-4">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex flex-wrap gap-2">
                                                {item.record_status === 'locked' ? (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => unlockFollowUp(item)}>
                                                        <Unlock size={15} /> Unlock
                                                    </Button>
                                                ) : (
                                                    <>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => lockFollowUp(item)}>
                                                            <Lock size={15} /> Lock
                                                        </Button>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => setEditFollowUpRow(item)}>
                                                            <PencilLine size={15} /> Edit
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                                            onClick={() => deleteFollowUp(item)}
                                                        >
                                                            <Trash2 size={15} /> Hapus
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                            <span className="rounded-full bg-silver-soft px-3 py-1 text-xs font-extrabold text-ink-soft dark:bg-white/10 dark:text-white/70">
                                                {item.record_status_label}
                                            </span>
                                        </div>
                                        <div className="grid gap-3 md:grid-cols-2">
                                            <StatChip label="Status" value={item.status_kpr} />
                                            <StatChip label="Tanggal Input" value={item.tanggal_follow_up} />
                                        </div>
                                        <div className="grid gap-3 md:grid-cols-3">
                                            <FieldBox label="Hasil Follow Up" value={item.hasil_follow_up} />
                                            <FieldBox label="Kendala" value={item.kendala} />
                                            <FieldBox label="Tindak Lanjut" value={item.tindak_lanjut} />
                                        </div>
                                        <div className="rounded-xl border border-silver-deep/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft">Catatan</p>
                                            <p className="mt-2 text-sm leading-7 text-ink dark:text-white/75">{item.catatan ?? '-'}</p>
                                        </div>
                                    </div>
                                ),
                            }))}
                        />
                    ) : (
                        <p className="text-sm font-bold text-ink-soft dark:text-white/55">Belum ada follow up KPR.</p>
                    )}
                </div>
            ),
        },
        {
            title: 'Berkas Customer',
            content: (
                <div className="grid gap-4">
                    <div className="rounded-lg border border-silver-deep/70 bg-white/75 p-4 dark:border-white/10 dark:bg-white/5">
                        <p className="text-sm font-extrabold text-ink dark:text-white">Berkas dari SPR</p>
                        <p className="mt-1 text-xs font-bold text-ink-soft dark:text-white/55">Dokumen wajib yang diupload saat pembuatan SPR.</p>
                    </div>
                    <div className="grid gap-3">
                        {(submission.spr_berkas_costumers ?? []).map((item) => (
                            <div className="rounded-lg border border-silver-deep/60 bg-white/80 p-4 dark:border-white/10 dark:bg-white/5" key={item.id}>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-extrabold text-ink dark:text-white">{item.nama_dokumen}</p>
                                        <p className="text-xs font-bold text-ink-soft dark:text-white/55">{item.nama_file}</p>
                                    </div>
                                    <span className={`rounded-full px-3 py-1 text-xs font-extrabold ${item.record_status === 'locked' ? 'bg-ink text-white dark:bg-white dark:text-graphite' : 'bg-silver-soft text-ink-soft dark:bg-white/10 dark:text-white/70'}`}>
                                        {item.record_status_label}
                                    </span>
                                </div>
                                <div className="mt-3 grid gap-1 text-xs font-bold text-ink-soft dark:text-white/55">
                                    <span>Upload oleh: {item.uploaded_by}</span>
                                    <span>Ukuran: {formatBytes(item.file_size)}</span>
                                    <span>Keterangan: {item.keterangan ?? '-'}</span>
                                </div>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Button as="a" href={item.path_file} target="_blank" rel="noreferrer" size="sm" variant="outline">
                                        <Download size={15} /> Lihat File
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                        onClick={() => {
                                            if (!window.confirm(`Hapus berkas ${item.nama_file}?`)) {
                                                return;
                                            }

                                            router.delete(`/admin/kpr/${submission.id}/berkas/${item.id}`, { preserveScroll: true });
                                        }}
                                    >
                                        <XCircle size={15} /> Hapus
                                    </Button>
                                </div>
                            </div>
                        ))}
                        {(submission.spr_berkas_costumers ?? []).length === 0 && <p className="text-sm font-bold text-ink-soft dark:text-white/55">Belum ada berkas dari SPR.</p>}
                    </div>
                    <div className="rounded-lg border border-silver-deep/70 bg-white/75 p-4 dark:border-white/10 dark:bg-white/5">
                        <p className="text-sm font-extrabold text-ink dark:text-white">Berkas Tambahan KPR</p>
                        <p className="mt-1 text-xs font-bold text-ink-soft dark:text-white/55">Jika ada dokumen tambahan setelah pengajuan KPR, bisa diupload di sini.</p>
                    </div>
                    <BerkasForm submission={submission} dokumenOptions={dokumenOptions} />
                    <div className="grid gap-3">
                        {(submission.berkas_costumers ?? []).map((item) => (
                            <div className="rounded-lg border border-silver-deep/60 bg-white/80 p-4 dark:border-white/10 dark:bg-white/5" key={item.id}>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-extrabold text-ink dark:text-white">{item.nama_dokumen}</p>
                                        <p className="text-xs font-bold text-ink-soft dark:text-white/55">{item.nama_file}</p>
                                    </div>
                                    <span className={`rounded-full px-3 py-1 text-xs font-extrabold ${item.record_status === 'locked' ? 'bg-ink text-white dark:bg-white dark:text-graphite' : 'bg-silver-soft text-ink-soft dark:bg-white/10 dark:text-white/70'}`}>
                                        {item.record_status_label}
                                    </span>
                                </div>
                                <div className="mt-3 grid gap-1 text-xs font-bold text-ink-soft dark:text-white/55">
                                    <span>Upload oleh: {item.uploaded_by}</span>
                                    <span>Ukuran: {formatBytes(item.file_size)}</span>
                                    <span>Keterangan: {item.keterangan ?? '-'}</span>
                                </div>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Button as="a" href={item.path_file} target="_blank" rel="noreferrer" size="sm" variant="outline">
                                        <Download size={15} /> Lihat File
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                        onClick={() => {
                                            if (!window.confirm(`Hapus berkas ${item.nama_file}?`)) {
                                                return;
                                            }

                                            router.delete(`/admin/kpr/${submission.id}/berkas/${item.id}`, { preserveScroll: true });
                                        }}
                                    >
                                        <XCircle size={15} /> Hapus
                                    </Button>
                                </div>
                            </div>
                        ))}
                        {(submission.berkas_costumers ?? []).length === 0 && <p className="text-sm font-bold text-ink-soft dark:text-white/55">Belum ada berkas tambahan KPR.</p>}
                    </div>
                </div>
            ),
        },
    ], [dokumenOptions, options, submission]);

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">Admin KPR</p>
                            <h2 className="mt-1 text-xl font-extrabold text-ink dark:text-white">{title}</h2>
                            <p className="mt-1 max-w-2xl text-sm leading-6 text-ink-soft dark:text-white/60">{description}</p>
                        </div>
                        <HeaderAction submission={submission} baseUrl={baseUrl} />
                    </div>
                </section>

                <Accordion defaultOpen={0} items={detailItems} />
            </div>
            <FollowUpEditModal
                open={Boolean(editFollowUpRow)}
                onClose={() => setEditFollowUpRow(null)}
                options={options}
                row={editFollowUpRow}
                submission={submission}
            />
        </>
    );
}

function InfoCard({ label, value }) {
    return (
        <div className="rounded-lg border border-silver-deep/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
            <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft">{label}</p>
            <p className="mt-2 text-sm font-extrabold text-ink dark:text-white">{value}</p>
        </div>
    );
}

function MetaPill({ label, value }) {
    return (
        <div className="flex min-w-0 items-center gap-2 rounded-full border border-silver-deep/60 bg-white/70 px-3 py-2 text-xs font-bold text-ink-soft dark:border-white/10 dark:bg-white/5 dark:text-white/65">
            <span className="uppercase tracking-[0.12em] text-ink-soft/80 dark:text-white/40">{label}</span>
            <span className="truncate font-extrabold text-ink dark:text-white">{value}</span>
        </div>
    );
}

function StatChip({ label, value }) {
    return (
        <div className="rounded-xl border border-silver-deep/60 bg-white/70 px-4 py-3 dark:border-white/10 dark:bg-white/5">
            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">{label}</p>
            <p className="mt-1 text-sm font-extrabold text-ink dark:text-white">{value}</p>
        </div>
    );
}

function FieldBox({ label, value }) {
    return (
        <div className="rounded-xl border border-silver-deep/60 bg-white/70 p-3 dark:border-white/10 dark:bg-white/5">
            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">{label}</p>
            <p className="mt-1 whitespace-pre-line text-sm font-semibold leading-6 text-ink dark:text-white/75">
                {value?.trim?.() ? value : '-'}
            </p>
        </div>
    );
}

Detail.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Detail KPR'}>{page}</AdminLayout>;
