import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowRight, Lock, MessageSquarePlus, PencilLine, Search, Save, Unlock, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button, CurrencyInput, Dropdown, Form, Input, Modal, Textarea } from '../../../Components/UI';
import AuditCell from '../../../Components/UI/AuditCell';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function Pagination({ links = [] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-silver-deep/60 px-5 py-4 dark:border-white/10">
            {links.map((link, index) => (
                <Button
                    className={!link.url ? 'pointer-events-none opacity-45' : ''}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                    key={`${link.label}-${index}`}
                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })}
                    size="sm"
                    type="button"
                    variant={link.active ? 'dark' : 'outline'}
                />
            ))}
        </div>
    );
}

function EditKprModal({ open, onClose, row, baseUrl, banks, options }) {
    const form = useForm({
        bank_kredit_id: row?.bank_kredit_id ? String(row.bank_kredit_id) : '',
        tanggal_pengajuan: row?.tanggal_pengajuan ?? new Date().toISOString().slice(0, 10),
        nilai_pengajuan: row?.nilai_pengajuan ? String(row.nilai_pengajuan) : '',
        status: row?.status ?? 'pengumpulan_dokumen',
        catatan: row?.catatan ?? '',
    });

    useEffect(() => {
        if (!row) {
            return;
        }

        form.setData({
            bank_kredit_id: row.bank_kredit_id ? String(row.bank_kredit_id) : '',
            tanggal_pengajuan: row.tanggal_pengajuan ?? new Date().toISOString().slice(0, 10),
            nilai_pengajuan: row.nilai_pengajuan ? String(row.nilai_pengajuan) : '',
            status: row.status ?? 'pengumpulan_dokumen',
            catatan: row.catatan ?? '',
        });
    }, [row?.id]);

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const submit = (event) => {
        event.preventDefault();
        form.put(`${baseUrl}/${row.id}`, { preserveScroll: true, onSuccess: close });
    };

    if (!row) {
        return null;
    }

    return (
        <Modal open={open} onClose={close} title={`Update KPR ${row.kode_kpr}`} size="lg">
            <Form
                collapsible={false}
                className="border-0 bg-transparent p-0 shadow-none dark:bg-transparent"
                contentClassName="md:grid-cols-2"
                onSubmit={submit}
                actions={
                    <>
                        <Button variant="outline" type="button" onClick={close}><XCircle size={17} /> Batal</Button>
                        <Button disabled={form.processing} type="submit"><Save size={17} /> Simpan</Button>
                    </>
                }
            >
                <div className="grid gap-2 md:col-span-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Bank</span>
                    <Dropdown value={form.data.bank_kredit_id} label="Pilih Bank Kredit" options={banks} onChange={(value) => form.setData('bank_kredit_id', value)} />
                    {form.errors.bank_kredit_id && <span className="text-xs font-bold text-red-600">{form.errors.bank_kredit_id}</span>}
                </div>
                <Input label="Tanggal Pengajuan" type="date" value={form.data.tanggal_pengajuan} error={form.errors.tanggal_pengajuan} onChange={(event) => form.setData('tanggal_pengajuan', event.target.value)} />
                <CurrencyInput label="Nilai Pengajuan" value={form.data.nilai_pengajuan} error={form.errors.nilai_pengajuan} onChange={(value) => form.setData('nilai_pengajuan', value)} />
                <div className="grid gap-2 md:col-span-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Status KPR</span>
                    <Dropdown value={form.data.status} options={options.statusOptions} onChange={(value) => form.setData('status', value)} />
                    {form.errors.status && <span className="text-xs font-bold text-red-600">{form.errors.status}</span>}
                </div>
                <Textarea className="md:col-span-2" label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
            </Form>
        </Modal>
    );
}

function FollowUpModal({ open, onClose, row, baseUrl, options }) {
    const form = useForm({
        tanggal_follow_up: new Date().toISOString().slice(0, 10),
        metode_follow_up: 'telephone',
        status_kpr: row?.status ?? 'pengumpulan_dokumen',
        catatan: '',
        rencana_follow_up_at: '',
    });

    useEffect(() => {
        if (!row) {
            return;
        }

        form.setData({
            tanggal_follow_up: new Date().toISOString().slice(0, 10),
            metode_follow_up: 'telephone',
            status_kpr: row.status ?? 'pengumpulan_dokumen',
            catatan: '',
            rencana_follow_up_at: '',
        });
    }, [row?.id]);

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(`${baseUrl}/${row.id}/follow-up`, { preserveScroll: true, onSuccess: close });
    };

    if (!row) {
        return null;
    }

    return (
        <Modal open={open} onClose={close} title={`Follow Up KPR ${row.kode_kpr}`} size="lg">
            <Form
                collapsible={false}
                className="border-0 bg-transparent p-0 shadow-none dark:bg-transparent"
                contentClassName="md:grid-cols-2"
                onSubmit={submit}
                actions={
                    <>
                        <Button variant="outline" type="button" onClick={close}><XCircle size={17} /> Batal</Button>
                        <Button disabled={form.processing} type="submit"><MessageSquarePlus size={17} /> Simpan Follow Up</Button>
                    </>
                }
            >
                <Input label="Tanggal Follow Up" type="date" value={form.data.tanggal_follow_up} error={form.errors.tanggal_follow_up} onChange={(event) => form.setData('tanggal_follow_up', event.target.value)} />
                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Metode</span>
                    <Dropdown value={form.data.metode_follow_up} options={options.methodOptions} onChange={(value) => form.setData('metode_follow_up', value)} />
                    {form.errors.metode_follow_up && <span className="text-xs font-bold text-red-600">{form.errors.metode_follow_up}</span>}
                </div>
                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Status KPR</span>
                    <Dropdown value={form.data.status_kpr} options={options.statusOptions} onChange={(value) => form.setData('status_kpr', value)} />
                    {form.errors.status_kpr && <span className="text-xs font-bold text-red-600">{form.errors.status_kpr}</span>}
                </div>
                <Input label="Rencana Berikutnya" type="date" value={form.data.rencana_follow_up_at} error={form.errors.rencana_follow_up_at} onChange={(event) => form.setData('rencana_follow_up_at', event.target.value)} />
                <Textarea className="md:col-span-2" label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
            </Form>
        </Modal>
    );
}

export default function Index({ title, description, baseUrl, rows, filters = {}, banks = [], options = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editRow, setEditRow] = useState(null);

    const submitSearch = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const lockRow = (row) => {
        if (!window.confirm(`Lock data KPR ${row.kode_kpr}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };

    const unlockRow = (row) => {
        if (!window.confirm(`Buka lock data KPR ${row.kode_kpr}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">Admin KPR</p>
                    <h2 className="mt-1 text-xl font-extrabold text-ink dark:text-white">{title}</h2>
                    <p className="mt-1 max-w-2xl text-sm leading-6 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-end md:justify-between" onSubmit={submitSearch}>
                        <Input className="w-full md:max-w-md" icon={<Search size={17} />} label="Cari KPR" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['Kode KPR', 'SPR', 'Customer', 'Unit', 'Bank', 'Nilai', 'Audit', 'Lock', 'Status', 'Follow Up', 'Aksi'].map((column) => (
                                        <th className={`px-4 py-3 font-extrabold ${column === 'Aksi' ? 'text-right' : ''}`} key={column}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id}>
                                        <td className="px-4 py-3 font-bold">{row.kode_kpr}</td>
                                        <td className="px-4 py-3 font-semibold">{row.kode_spr}</td>
                                        <td className="px-4 py-3 font-semibold">{row.customer}</td>
                                        <td className="px-4 py-3 font-semibold">{row.unit}</td>
                                        <td className="px-4 py-3 font-semibold">{row.bank}</td>
                                        <td className="px-4 py-3 font-semibold">{money(row.nilai_pengajuan)}</td>
                                        <td className="px-4 py-3 font-semibold"><AuditCell createdBy={row.created_at} updatedBy={row.updated_at} /></td>
                                        <td className="px-4 py-3 font-semibold">{row.record_status_label}</td>
                                        <td className="px-4 py-3 font-semibold">{row.status_label}</td>
                                        <td className="px-4 py-3 font-semibold">{row.follow_ups_count}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button as={Link} href={row.detail_url} size="sm" variant="ghost">
                                                    <ArrowRight size={15} /> Detail
                                                </Button>
                                                {row.record_status === 'locked' ? (
                                                    <Button size="sm" type="button" variant="outline" onClick={() => unlockRow(row)}><Unlock size={15} /></Button>
                                                ) : (
                                                    <Button size="sm" type="button" variant="outline" onClick={() => lockRow(row)}><Lock size={15} /></Button>
                                                )}
                                                <Button as={Link} href={row.edit_url} size="sm" variant="outline"><PencilLine size={15} /></Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50" colSpan={11}>Belum ada pengajuan KPR.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Pengajuan KPR'}>{page}</AdminLayout>;
