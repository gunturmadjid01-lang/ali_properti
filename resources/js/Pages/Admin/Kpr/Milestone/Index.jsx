import { Head, router, useForm } from '@inertiajs/react';
import { CalendarCheck, Eye, FileUp, KeyRound, Lock, PencilLine, Plus, Search, Trash2, Unlock, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button, Dropdown, Input, Modal, Textarea } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

function Pagination({ links = [] }) {
    if (links.length <= 3) return null;

    return (
        <div className="flex flex-wrap justify-end gap-2 border-t border-silver-deep/60 px-5 py-4 dark:border-white/10">
            {links.map((link, index) => (
                <Button
                    key={`${link.label}-${index}`}
                    size="sm"
                    variant={link.active ? 'dark' : 'outline'}
                    className={!link.url ? 'pointer-events-none opacity-45' : ''}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })}
                />
            ))}
        </div>
    );
}

function MilestoneModal({ open, onClose, row, type, baseUrl, submissionOptions = [] }) {
    const milestone = row?.milestone;
    const form = useForm({
        kpr_submission_id: row?.id ? String(row.id) : '',
        tanggal_proses: milestone?.tanggal_proses ?? new Date().toISOString().slice(0, 16),
        lokasi: milestone?.lokasi ?? '',
        nomor_dokumen: milestone?.nomor_dokumen ?? '',
        pihak_terkait: milestone?.pihak_terkait ?? '',
        catatan: milestone?.catatan ?? '',
        dokumen: [],
    });

    useEffect(() => {
        if (!open) return;

        form.setData({
            kpr_submission_id: row?.id ? String(row.id) : '',
            tanggal_proses: row?.milestone?.tanggal_proses ?? new Date().toISOString().slice(0, 16),
            lokasi: row?.milestone?.lokasi ?? '',
            nomor_dokumen: row?.milestone?.nomor_dokumen ?? '',
            pihak_terkait: row?.milestone?.pihak_terkait ?? '',
            catatan: row?.milestone?.catatan ?? '',
            dokumen: [],
        });
    }, [open, row?.id, row?.milestone?.id]);

    if (!open) return null;

    const submit = (event) => {
        event.preventDefault();
        const url = milestone ? `${baseUrl}/${milestone.id}` : row ? `${baseUrl}/submission/${row.id}` : baseUrl;
        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.clearErrors();
                onClose();
            },
        };

        if (milestone) {
            form.post(url, options);
        } else {
            form.post(url, options);
        }
    };

    const label = type === 'akad' ? 'Akad KPR' : 'Serah Terima Unit';
    const selectedSubmission = row ?? submissionOptions.find((option) => option.value === form.data.kpr_submission_id);

    return (
        <Modal open={open} onClose={onClose} title={`${milestone ? 'Edit' : 'Input'} ${label}${selectedSubmission?.kode_kpr ? ` - ${selectedSubmission.kode_kpr}` : ''}`} size="full">
            <div className="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
                <section className="rounded-xl border border-silver-deep/60 bg-silver-soft/60 p-5 dark:border-white/10 dark:bg-white/5">
                    <p className="text-xs font-extrabold uppercase tracking-[0.14em] text-ink-soft">Informasi Customer</p>
                    {!row && (
                        <div className="mt-4 grid gap-2">
                            <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Customer Siap {type === 'akad' ? 'Akad' : 'Serah Terima'}</span>
                            <Dropdown
                                value={form.data.kpr_submission_id}
                                label={type === 'akad' ? 'Pilih customer status SP3K Keluar' : 'Pilih customer yang sudah akad'}
                                options={submissionOptions}
                                onChange={(value) => form.setData('kpr_submission_id', value)}
                            />
                            {form.errors.kpr_submission_id && <span className="text-xs font-bold text-red-600">{form.errors.kpr_submission_id}</span>}
                        </div>
                    )}
                    {selectedSubmission ? (
                        <>
                            <h3 className="mt-5 text-xl font-extrabold">{selectedSubmission.customer}</h3>
                            <div className="mt-5 grid gap-3 text-sm">
                                <div><b>KPR:</b> {selectedSubmission.kode_kpr}</div>
                                <div><b>SPR:</b> {selectedSubmission.kode_spr}</div>
                                <div><b>Unit:</b> {selectedSubmission.unit} - {selectedSubmission.perumahan}</div>
                                <div><b>Bank:</b> {selectedSubmission.bank}</div>
                                <div><b>Status KPR:</b> {selectedSubmission.status_kpr}</div>
                            </div>
                        </>
                    ) : (
                        <p className="mt-5 rounded-lg border border-dashed border-silver-deep/70 p-4 text-sm font-bold text-ink-soft dark:border-white/10">
                            {submissionOptions.length === 0
                                ? `Belum ada customer yang siap untuk ${type === 'akad' ? 'akad' : 'serah terima'}.`
                                : 'Pilih customer terlebih dahulu.'}
                        </p>
                    )}
                    {milestone?.documents?.length > 0 && (
                        <div className="mt-6 grid gap-2">
                            <p className="text-xs font-extrabold uppercase tracking-[0.14em] text-ink-soft">Dokumentasi Tersimpan</p>
                            {milestone.documents.map((document) => (
                                <div className="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 dark:bg-white/7" key={document.id}>
                                    <a className="truncate text-sm font-bold text-emerald-600 underline" href={document.url} target="_blank" rel="noreferrer">{document.nama_file}</a>
                                    {milestone.record_status !== 'locked' && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => router.delete(`${baseUrl}/${milestone.id}/document/${document.id}`, { preserveScroll: true })}
                                        >
                                            <Trash2 size={14} />
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                <form className="grid gap-4 rounded-xl border border-silver-deep/60 bg-white/80 p-5 dark:border-white/10 dark:bg-white/5 md:grid-cols-2" onSubmit={submit}>
                    <Input label={`Tanggal ${type === 'akad' ? 'Akad' : 'Serah Terima'}`} type="datetime-local" value={form.data.tanggal_proses} error={form.errors.tanggal_proses} onChange={(event) => form.setData('tanggal_proses', event.target.value)} />
                    <Input label="Lokasi" value={form.data.lokasi} error={form.errors.lokasi} onChange={(event) => form.setData('lokasi', event.target.value)} />
                    <Input label={type === 'akad' ? 'Nomor Akta / Dokumen' : 'Nomor Berita Acara'} value={form.data.nomor_dokumen} error={form.errors.nomor_dokumen} onChange={(event) => form.setData('nomor_dokumen', event.target.value)} />
                    <Input label={type === 'akad' ? 'Notaris / Pihak Bank' : 'Pihak yang Menyerahkan'} value={form.data.pihak_terkait} error={form.errors.pihak_terkait} onChange={(event) => form.setData('pihak_terkait', event.target.value)} />
                    <label className="grid gap-2 md:col-span-2">
                        <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Dokumentasi</span>
                        <input
                            className="min-h-12 rounded-lg border border-silver-deep/70 bg-white/85 px-4 py-2 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-ink file:px-4 file:py-2 file:font-bold file:text-white dark:border-white/10 dark:bg-white/8"
                            type="file"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                            onChange={(event) => form.setData('dokumen', Array.from(event.target.files ?? []))}
                        />
                        <span className="text-xs font-semibold text-ink-soft">Bisa memilih beberapa foto atau dokumen sekaligus.</span>
                        {form.errors.dokumen && <span className="text-xs font-bold text-red-600">{form.errors.dokumen}</span>}
                    </label>
                    <Textarea className="md:col-span-2" label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                    <div className="flex justify-end gap-2 md:col-span-2">
                        <Button type="button" variant="outline" onClick={onClose}><XCircle size={16} /> Batal</Button>
                        <Button disabled={form.processing || (!row && !form.data.kpr_submission_id)} type="submit"><FileUp size={16} /> {form.processing ? 'Menyimpan...' : 'Simpan'}</Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}

function DetailModal({ row, onClose, baseUrl }) {
    if (!row?.milestone) return null;
    const item = row.milestone;

    return (
        <Modal open onClose={onClose} title={`Detail ${row.type === 'akad' ? 'Akad' : 'Serah Terima'} - ${row.kode_kpr}`} size="lg">
            <div className="grid gap-4">
                <div className="grid gap-3 md:grid-cols-2">
                    {[
                        ['Customer', row.customer],
                        ['Unit', `${row.unit} - ${row.perumahan}`],
                        ['Tanggal', item.tanggal_label],
                        ['Lokasi', item.lokasi],
                        ['Nomor Dokumen', item.nomor_dokumen || '-'],
                        ['Pihak Terkait', item.pihak_terkait || '-'],
                        ['Audit', `Dibuat: ${item.created_by ?? '-'} | Diubah: ${item.updated_by ?? '-'}`],
                    ].map(([label, value]) => <div className="rounded-xl bg-silver-soft p-4 dark:bg-white/5" key={label}><p className="text-xs font-bold uppercase text-ink-soft">{label}</p><p className="mt-1 font-extrabold">{value}</p></div>)}
                </div>
                <div className="rounded-xl bg-silver-soft p-4 dark:bg-white/5"><p className="text-xs font-bold uppercase text-ink-soft">Catatan</p><p className="mt-2">{item.catatan || '-'}</p></div>
                <div className="grid gap-2">
                    <p className="text-sm font-extrabold">Dokumentasi</p>
                    {item.documents.map((document) => <a className="rounded-lg border border-silver-deep/60 px-4 py-3 font-bold text-emerald-600 underline dark:border-white/10" href={document.url} target="_blank" rel="noreferrer" key={document.id}>{document.nama_file}</a>)}
                    {item.documents.length === 0 && <p className="text-sm font-bold text-ink-soft">Belum ada dokumentasi.</p>}
                </div>
                <div className="flex justify-end"><Button variant="outline" onClick={onClose}>Tutup</Button></div>
            </div>
        </Modal>
    );
}

export default function Index({ title, description, type, baseUrl, rows, filters = {}, submissionOptions = [] }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [formRow, setFormRow] = useState(null);
    const [createOpen, setCreateOpen] = useState(false);
    const [detailRow, setDetailRow] = useState(null);
    const Icon = type === 'akad' ? CalendarCheck : KeyRound;

    const submitSearch = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveState: true, replace: true });
    };

    const toggleLock = (row) => {
        const action = row.milestone.record_status === 'locked' ? 'unlock' : 'lock';
        router.post(`${baseUrl}/${row.milestone.id}/${action}`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-2xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/7">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-4">
                            <span className="grid h-12 w-12 place-items-center rounded-xl bg-ink text-white dark:bg-white dark:text-ink"><Icon size={22} /></span>
                            <div><h1 className="font-display text-3xl font-extrabold">{title}</h1><p className="mt-1 text-ink-soft dark:text-white/60">{description}</p></div>
                        </div>
                        <Button type="button" onClick={() => setCreateOpen(true)}>
                            <Plus size={16} /> Tambah {type === 'akad' ? 'Akad' : 'Serah Terima'}
                        </Button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/7">
                    <form className="flex flex-col gap-3 border-b border-silver-deep/60 p-5 dark:border-white/10 md:flex-row md:items-end" onSubmit={submitSearch}>
                        <Input className="flex-1" icon={<Search size={16} />} label="Cari KPR / SPR / Customer / Unit" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button><Search size={16} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto"><table className="min-w-full text-sm">
                        <thead className="bg-silver-soft text-left text-xs uppercase tracking-wider text-ink-soft dark:bg-white/5"><tr>{['KPR / SPR', 'Customer', 'Unit', 'Bank', 'Tanggal', 'Dokumen', 'Lock', 'Aksi'].map((label) => <th className="px-4 py-3" key={label}>{label}</th>)}</tr></thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {rows.data.map((row) => (
                                <tr key={row.id}>
                                    <td className="px-4 py-4 font-extrabold">{row.kode_kpr}<br /><small className="text-ink-soft">{row.kode_spr}</small></td>
                                    <td className="px-4 py-4 font-bold">{row.customer}<br /><small className="text-ink-soft">{row.no_identitas}</small></td>
                                    <td className="px-4 py-4">{row.unit}<br /><small className="text-ink-soft">{row.perumahan}</small></td>
                                    <td className="px-4 py-4">{row.bank}</td>
                                    <td className="px-4 py-4">{row.milestone?.tanggal_label ?? 'Belum diinput'}</td>
                                    <td className="px-4 py-4">{row.milestone?.documents?.length ?? 0} file</td>
                                    <td className="px-4 py-4">{row.milestone?.record_status ?? '-'}</td>
                                    <td className="px-4 py-4"><div className="flex justify-end gap-2">
                                        {!row.milestone && <Button size="sm" onClick={() => setFormRow(row)}><Plus size={14} /> Input</Button>}
                                        {row.milestone && <>
                                            <Button size="sm" variant="outline" onClick={() => setDetailRow(row)}><Eye size={14} /></Button>
                                            {row.milestone.record_status !== 'locked' && <>
                                                <Button size="sm" variant="outline" onClick={() => setFormRow(row)}><PencilLine size={14} /></Button>
                                                <Button size="sm" variant="outline" onClick={() => window.confirm('Hapus data ini?') && router.delete(`${baseUrl}/${row.milestone.id}`, { preserveScroll: true })}><Trash2 size={14} /></Button>
                                            </>}
                                            {row.milestone.can_lock && (
                                                <Button size="sm" variant="outline" title="Lock Data" onClick={() => toggleLock(row)}><Lock size={14} /></Button>
                                            )}
                                            {row.milestone.can_unlock && (
                                                <Button size="sm" variant="outline" title="Buka Lock" onClick={() => toggleLock(row)}><Unlock size={14} /></Button>
                                            )}
                                        </>}
                                    </div></td>
                                </tr>
                            ))}
                            {rows.data.length === 0 && <tr><td className="px-5 py-12 text-center font-bold text-ink-soft" colSpan={8}>Belum ada data KPR yang dapat diproses.</td></tr>}
                        </tbody>
                    </table></div>
                    <Pagination links={rows.links} />
                </section>
            </div>
            <MilestoneModal open={createOpen} onClose={() => setCreateOpen(false)} row={null} type={type} baseUrl={baseUrl} submissionOptions={submissionOptions} />
            <MilestoneModal open={Boolean(formRow)} onClose={() => setFormRow(null)} row={formRow} type={type} baseUrl={baseUrl} submissionOptions={submissionOptions} />
            <DetailModal row={detailRow} onClose={() => setDetailRow(null)} baseUrl={baseUrl} />
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Proses KPR'}>{page}</AdminLayout>;
