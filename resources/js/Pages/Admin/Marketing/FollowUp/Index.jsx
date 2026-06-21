import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, Edit3, Lock, MessageSquarePlus, Search, Trash2, Unlock, UserRoundCheck, XCircle } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button, Dropdown, Form, Input, Modal, Textarea } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

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

function CustomerSearch({ customers = [], selectedCustomer, onSelect, error }) {
    const [query, setQuery] = useState('');
    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (needle === '') {
            return customers.slice(0, 8);
        }

        return customers.filter((customer) => customer.search.includes(needle)).slice(0, 8);
    }, [customers, query]);

    return (
        <div className="grid gap-3 md:col-span-2">
            <Input
                icon={<Search size={17} />}
                label="Cari Customer"
                value={query}
                placeholder="Ketik nama, no identitas, kode customer, atau telepon"
                onChange={(event) => setQuery(event.target.value)}
            />

            <div className="grid gap-2 rounded-lg border border-silver-deep/70 bg-white/70 p-2 dark:border-white/10 dark:bg-white/8">
                {filtered.map((customer) => (
                    <button
                        className={`rounded-lg px-3 py-2 text-left transition ${
                            selectedCustomer?.id === customer.id
                                ? 'bg-ink text-white'
                                : 'hover:bg-silver dark:hover:bg-white/10'
                        }`}
                        key={customer.id}
                        type="button"
                        onClick={() => onSelect(customer)}
                    >
                        <span className="block text-sm font-extrabold">{customer.nama}</span>
                        <span className={`mt-0.5 block text-xs font-bold ${selectedCustomer?.id === customer.id ? 'text-white/70' : 'text-ink-soft dark:text-white/50'}`}>
                            {customer.no_identitas || '-'} - {customer.telepon || '-'} - {customer.kode_costumer || '-'}
                        </span>
                    </button>
                ))}
                {filtered.length === 0 && (
                    <p className="px-3 py-4 text-center text-sm font-bold text-ink-soft dark:text-white/50">
                        Customer tidak ditemukan.
                    </p>
                )}
            </div>
            {error && <span className="text-xs font-bold text-red-600 dark:text-red-300">{error}</span>}
        </div>
    );
}

function SelectedCustomerCard({ customer }) {
    if (!customer) {
        return null;
    }

    return (
        <div className="grid gap-2 rounded-lg border border-silver-deep/70 bg-silver-soft p-4 text-sm dark:border-white/10 dark:bg-white/6 md:col-span-2 md:grid-cols-2">
            <div>
                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">Nama</p>
                <p className="mt-1 font-extrabold text-ink dark:text-white">{customer.nama}</p>
            </div>
            <div>
                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">No Identitas</p>
                <p className="mt-1 font-extrabold text-ink dark:text-white">{customer.no_identitas || '-'}</p>
            </div>
            <div>
                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">Telepon</p>
                <p className="mt-1 font-extrabold text-ink dark:text-white">{customer.telepon || '-'}</p>
            </div>
            <div>
                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">Pekerjaan</p>
                <p className="mt-1 font-extrabold text-ink dark:text-white">{customer.pekerjaan || '-'}</p>
            </div>
        </div>
    );
}

function FollowUpModal({ open, onClose, baseUrl, customers, options, initialCustomerId = '' }) {
    const form = useForm({
        costumer_id: initialCustomerId ? String(initialCustomerId) : '',
        tanggal_follow_up: new Date().toISOString().slice(0, 10),
        metode_follow_up: '',
        status_serius: '0',
        progress_kemampuan: '',
        catatan: '',
        rencana_follow_up_at: '',
    });

    const selectedCustomer = customers.find((customer) => Number(customer.id) === Number(form.data.costumer_id));
    const selectedProgress = options.progressOptions.find((option) => option.value === form.data.progress_kemampuan);

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData('costumer_id', initialCustomerId ? String(initialCustomerId) : '');
    }, [open, initialCustomerId]);

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(baseUrl, {
            preserveScroll: true,
            onSuccess: close,
        });
    };

    return (
        <Modal open={open} onClose={close} title="Tambah Follow Up" size="xl">
            <Form
                collapsible={false}
                className="border-0 bg-transparent p-0 shadow-none dark:bg-transparent"
                contentClassName="md:grid-cols-2"
                onSubmit={submit}
                actions={
                    <>
                        <Button variant="outline" type="button" onClick={close}>
                            <XCircle size={17} /> Batal
                        </Button>
                        <Button disabled={form.processing} type="submit">
                            <MessageSquarePlus size={17} /> {form.processing ? 'Menyimpan...' : 'Simpan Follow Up'}
                        </Button>
                    </>
                }
            >
                <CustomerSearch
                    customers={customers}
                    selectedCustomer={selectedCustomer}
                    error={form.errors.costumer_id}
                    onSelect={(customer) => form.setData('costumer_id', customer.id)}
                />
                <SelectedCustomerCard customer={selectedCustomer} />

                <Input
                    label="Tanggal Follow Up"
                    type="date"
                    value={form.data.tanggal_follow_up}
                    error={form.errors.tanggal_follow_up}
                    onChange={(event) => form.setData('tanggal_follow_up', event.target.value)}
                />

                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Media Follow Up</span>
                    <Dropdown
                        value={form.data.metode_follow_up}
                        label="Pilih media"
                        options={options.methodOptions}
                        onChange={(value) => form.setData('metode_follow_up', value)}
                    />
                    {form.errors.metode_follow_up && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.metode_follow_up}</span>}
                </div>

                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Status Serius</span>
                    <Dropdown
                        value={String(form.data.status_serius)}
                        label="Pilih status"
                        options={options.seriousOptions}
                        onChange={(value) => form.setData('status_serius', value)}
                    />
                    {form.errors.status_serius && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.status_serius}</span>}
                </div>

                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Progress Kemampuan</span>
                    <Dropdown
                        value={form.data.progress_kemampuan}
                        label="Pilih progress"
                        options={options.progressOptions}
                        onChange={(value) => form.setData('progress_kemampuan', value)}
                    />
                    {selectedProgress?.hint && (
                        <span className="rounded-lg bg-champagne px-3 py-2 text-xs font-bold text-gold-deep dark:bg-white/8 dark:text-white/62">
                            {selectedProgress.hint}
                        </span>
                    )}
                    {form.errors.progress_kemampuan && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.progress_kemampuan}</span>}
                </div>

                <Input
                    label="Rencana Follow Up Berikutnya"
                    type="date"
                    value={form.data.rencana_follow_up_at}
                    error={form.errors.rencana_follow_up_at}
                    onChange={(event) => form.setData('rencana_follow_up_at', event.target.value)}
                />

                <Textarea
                    className="md:col-span-2"
                    label="Catatan Follow Up"
                    value={form.data.catatan}
                    error={form.errors.catatan}
                    placeholder="Catatan hasil chat, kunjungan, atau telephone"
                    onChange={(event) => form.setData('catatan', event.target.value)}
                />
            </Form>
        </Modal>
    );
}

function EditFollowUpModal({ open, onClose, baseUrl, customers, options, row }) {
    const form = useForm({
        costumer_id: row?.costumer_id ? String(row.costumer_id) : '',
        tanggal_follow_up: row?.tanggal_follow_up ?? new Date().toISOString().slice(0, 10),
        metode_follow_up: row?.metode_key ?? '',
        status_serius: row?.status_serius_value ?? '0',
        progress_kemampuan: row?.progress_key ?? '',
        catatan: row?.catatan ?? '',
        rencana_follow_up_at: row?.rencana_follow_up_at ?? '',
    });

    const selectedCustomer = customers.find((customer) => Number(customer.id) === Number(form.data.costumer_id));
    const selectedProgress = options.progressOptions.find((option) => option.value === form.data.progress_kemampuan);

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    const submit = (event) => {
        event.preventDefault();
        form.put(`${baseUrl}/${row.id}`, {
            preserveScroll: true,
            onSuccess: close,
        });
    };

    if (!row) {
        return null;
    }

    return (
        <Modal open={open} onClose={close} title={`Edit Follow Up ${row.customer}`} size="xl">
            <Form
                collapsible={false}
                className="border-0 bg-transparent p-0 shadow-none dark:bg-transparent"
                contentClassName="md:grid-cols-2"
                onSubmit={submit}
                actions={(
                    <>
                        <Button variant="outline" type="button" onClick={close}>
                            <XCircle size={17} /> Batal
                        </Button>
                        <Button disabled={form.processing} type="submit">
                            <MessageSquarePlus size={17} /> Simpan Perubahan
                        </Button>
                    </>
                )}
            >
                <CustomerSearch
                    customers={customers}
                    selectedCustomer={selectedCustomer}
                    error={form.errors.costumer_id}
                    onSelect={(customer) => form.setData('costumer_id', customer.id)}
                />
                <SelectedCustomerCard customer={selectedCustomer} />

                <Input
                    label="Tanggal Follow Up"
                    type="date"
                    value={form.data.tanggal_follow_up}
                    error={form.errors.tanggal_follow_up}
                    onChange={(event) => form.setData('tanggal_follow_up', event.target.value)}
                />

                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Media Follow Up</span>
                    <Dropdown
                        value={form.data.metode_follow_up}
                        label="Pilih media"
                        options={options.methodOptions}
                        onChange={(value) => form.setData('metode_follow_up', value)}
                    />
                    {form.errors.metode_follow_up && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.metode_follow_up}</span>}
                </div>

                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Status Serius</span>
                    <Dropdown
                        value={String(form.data.status_serius)}
                        label="Pilih status"
                        options={options.seriousOptions}
                        onChange={(value) => form.setData('status_serius', value)}
                    />
                    {form.errors.status_serius && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.status_serius}</span>}
                </div>

                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Progress Kemampuan</span>
                    <Dropdown
                        value={form.data.progress_kemampuan}
                        label="Pilih progress"
                        options={options.progressOptions}
                        onChange={(value) => form.setData('progress_kemampuan', value)}
                    />
                    {selectedProgress?.hint && (
                        <span className="rounded-lg bg-champagne px-3 py-2 text-xs font-bold text-gold-deep dark:bg-white/8 dark:text-white/62">
                            {selectedProgress.hint}
                        </span>
                    )}
                    {form.errors.progress_kemampuan && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.progress_kemampuan}</span>}
                </div>

                <Input
                    label="Rencana Follow Up Berikutnya"
                    type="date"
                    value={form.data.rencana_follow_up_at}
                    error={form.errors.rencana_follow_up_at}
                    onChange={(event) => form.setData('rencana_follow_up_at', event.target.value)}
                />

                <Textarea
                    className="md:col-span-2"
                    label="Catatan Follow Up"
                    value={form.data.catatan}
                    error={form.errors.catatan}
                    placeholder="Catatan hasil chat, kunjungan, atau telephone"
                    onChange={(event) => form.setData('catatan', event.target.value)}
                />
            </Form>
        </Modal>
    );
}

export default function Index({ title, description, baseUrl, rows, filters = {}, customers = [], options = {} }) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editRow, setEditRow] = useState(null);
    const [repeatCustomerId, setRepeatCustomerId] = useState('');
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const destroy = (row) => {
        if (!window.confirm(`Hapus follow up ${row.customer}?`)) {
            return;
        }

        router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    };

    const lockRow = (row) => {
        if (!window.confirm(`Lock follow up ${row.customer}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };

    const unlockRow = (row) => {
        if (!window.confirm(`Buka lock follow up ${row.customer}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };

    const editRowHandler = (row) => {
        setEditRow(row);
    };

    const openCreateModal = (customerId = '') => {
        setRepeatCustomerId(customerId ? String(customerId) : '');
        setModalOpen(true);
    };

    const closeCreateModal = () => {
        setModalOpen(false);
        setRepeatCustomerId('');
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">Marketing</p>
                            <h2 className="mt-1 text-xl font-extrabold text-ink dark:text-white">{title}</h2>
                            <p className="mt-1 max-w-2xl text-sm leading-6 text-ink-soft dark:text-white/60">{description}</p>
                        </div>
                        <Button type="button" onClick={() => openCreateModal()}>
                            <MessageSquarePlus size={18} /> Tambah Follow Up
                        </Button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-end md:justify-between" onSubmit={submitSearch}>
                        <Input
                            className="w-full md:max-w-md"
                            icon={<Search size={17} />}
                            label="Cari Follow Up"
                            value={search}
                            placeholder="Nama, no identitas, media, atau catatan"
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Button type="submit">
                            <Search size={17} /> Cari
                        </Button>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    <th className="px-4 py-3 font-extrabold">Tanggal</th>
                                    <th className="px-4 py-3 font-extrabold">Customer</th>
                                    <th className="px-4 py-3 font-extrabold">No Identitas</th>
                                    <th className="px-4 py-3 font-extrabold">Media</th>
                                    <th className="px-4 py-3 font-extrabold">Status</th>
                                    <th className="px-4 py-3 font-extrabold">Progress</th>
                                    <th className="px-4 py-3 font-extrabold">Lock</th>
                                    <th className="px-4 py-3 font-extrabold">Rencana</th>
                                    <th className="px-4 py-3 text-right font-extrabold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id}>
                                        <td className="px-4 py-3 font-semibold text-ink/80 dark:text-white/72">
                                            <span className="inline-flex items-center gap-2">
                                                <CalendarDays size={15} /> {row.tanggal_follow_up}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 font-semibold text-ink/80 dark:text-white/72">{row.customer}</td>
                                        <td className="px-4 py-3 font-semibold text-ink/80 dark:text-white/72">{row.no_identitas}</td>
                                        <td className="px-4 py-3 font-semibold text-ink/80 dark:text-white/72">{row.metode_follow_up}</td>
                                        <td className="px-4 py-3 font-semibold text-ink/80 dark:text-white/72">
                                            <span className="inline-flex items-center gap-2">
                                                <UserRoundCheck size={15} /> {row.status_serius}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 font-semibold text-ink/80 dark:text-white/72">{row.progress_kemampuan}</td>
                                        <td className="px-4 py-3 font-semibold text-ink/80 dark:text-white/72">{row.record_status_label}</td>
                                        <td className="px-4 py-3 font-semibold text-ink/80 dark:text-white/72">{row.rencana_follow_up_at || '-'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button type="button" size="sm" variant="outline" onClick={() => openCreateModal(row.costumer_id)}><MessageSquarePlus size={15} /> Lagi</Button>
                                                {row.record_status === 'locked' ? (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => unlockRow(row)}><Unlock size={15} /> Unlock</Button>
                                                ) : (
                                                    <>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => lockRow(row)}><Lock size={15} /> Lock</Button>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => editRowHandler(row)}><Edit3 size={15} /> Edit</Button>
                                                        <Button
                                                            className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-300 dark:hover:bg-red-500/10"
                                                            size="sm"
                                                            type="button"
                                                            variant="ghost"
                                                            onClick={() => destroy(row)}
                                                        >
                                                            <Trash2 size={15} />
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50" colSpan={9}>
                                            Belum ada data follow up.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination links={rows.links} />
                </section>
            </div>

            <FollowUpModal
                open={modalOpen}
                onClose={closeCreateModal}
                baseUrl={baseUrl}
                customers={customers}
                options={options}
                initialCustomerId={repeatCustomerId}
            />
            <EditFollowUpModal
                open={Boolean(editRow)}
                onClose={() => setEditRow(null)}
                baseUrl={baseUrl}
                customers={customers}
                options={options}
                row={editRow}
            />
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Jejak Follow Up'}>{page}</AdminLayout>;
