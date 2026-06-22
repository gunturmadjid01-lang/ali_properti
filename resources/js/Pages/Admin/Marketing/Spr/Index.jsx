import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Edit3, Eye, FilePlus2, Lock, Search, ShieldCheck, Unlock, XCircle } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Accordion, Button, CurrencyInput, Dropdown, Form, Input, Modal, Textarea } from '../../../../Components/UI';
import DetailModal from '../../../../Components/UI/DetailModal';
import AdminLayout from '../../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function FormErrorSummary({ errors }) {
    const messages = Object.values(errors ?? {}).filter(Boolean);

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
            <p>Data belum bisa disimpan. Periksa bagian berikut:</p>
            <ul className="mt-2 list-disc space-y-1 pl-5">
                {messages.map((message, index) => <li key={`${message}-${index}`}>{message}</li>)}
            </ul>
        </div>
    );
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

function SearchPicker({ label, placeholder, rows = [], selected, onSelect, error, className = 'md:col-span-2' }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState(selected?.label ?? '');
    const rootRef = useRef(null);
    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (needle === '') {
            return rows.slice(0, 8);
        }

        return rows.filter((row) => row.search?.includes(needle) || row.label?.toLowerCase().includes(needle)).slice(0, 8);
    }, [query, rows]);

    useEffect(() => {
        if (selected) {
            setQuery(selected.label ?? '');
        } else {
            setQuery('');
        }
    }, [selected?.id, selected?.label]);

    useEffect(() => {
        const handlePointerDown = (event) => {
            if (rootRef.current && !rootRef.current.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handlePointerDown);

        return () => document.removeEventListener('mousedown', handlePointerDown);
    }, []);

    return (
        <div ref={rootRef} className={`relative grid gap-3 ${className}`}>
            <Input
                label={label}
                icon={<Search size={17} />}
                value={query}
                placeholder={placeholder}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onClick={() => setOpen(true)}
                onBlur={() => {
                    window.setTimeout(() => setOpen(false), 120);
                }}
            />
            {open && (
                <div className="absolute left-0 right-0 top-full z-30 mt-1 grid gap-2 rounded-lg border border-silver-deep/70 bg-white p-2 shadow-soft dark:border-white/10 dark:bg-graphite">
                    <div className="max-h-72 overflow-y-auto">
                        {filtered.map((row) => (
                            <button
                                className={`w-full rounded-lg px-3 py-2 text-left text-sm font-bold transition ${selected?.id === row.id ? 'bg-ink text-white' : row.is_available === false ? 'cursor-not-allowed bg-silver/70 text-ink-soft/70 dark:bg-white/6 dark:text-white/35' : 'text-ink hover:bg-silver dark:text-white dark:hover:bg-white/10'}`}
                                key={row.id}
                                disabled={row.is_available === false}
                                type="button"
                                onClick={() => {
                                    setQuery(row.label ?? '');
                                    setOpen(false);
                                    onSelect(row);
                                }}
                                onMouseDown={(event) => event.preventDefault()}
                            >
                                <span className="block">{row.label}</span>
                                {row.is_available === false && <span className="mt-1 block text-[11px] font-bold uppercase tracking-[0.12em] text-amber-600 dark:text-amber-300">{row.availability_label ?? 'Tidak tersedia'}</span>}
                            </button>
                        ))}
                        {filtered.length === 0 && <p className="px-3 py-4 text-center text-sm font-bold text-ink-soft dark:text-white/50">Data tidak ditemukan.</p>}
                    </div>
                </div>
            )}
            {error && <span className="text-xs font-bold text-red-600 dark:text-red-300">{error}</span>}
        </div>
    );
}

function CreateSprModal({ open, onClose, baseUrl, customers, units, options, dokumenOptions = [] }) {
    const [submitError, setSubmitError] = useState('');
    const [isMobile, setIsMobile] = useState(false);
    const buildInitialBerkasRows = () => dokumenOptions.map((dokumen) => ({
        dokumen_costumer_id: dokumen.value,
        file_upload: null,
        keterangan: '',
        file_name: '',
        dokumen_label: dokumen.label,
    }));
    const form = useForm({
        costumer_id: '',
        detail_rumah_id: '',
        tanggal_spr: new Date().toISOString().slice(0, 10),
        metode_pembayaran: 'kpr_bank',
        harga_jual: '',
        booking_fee: '',
        uang_muka: '',
        nilai_pengajuan_kpr: '',
        jumlah_termin: '',
        nominal_termin: '',
        catatan: '',
        berkas: buildInitialBerkasRows().map(({ file_name, dokumen_label, ...row }) => row),
    });
    const [berkasRows, setBerkasRows] = useState(buildInitialBerkasRows());

    const selectedCustomer = customers.find((customer) => Number(customer.id) === Number(form.data.costumer_id));
    const selectedUnit = units.find((unit) => Number(unit.id) === Number(form.data.detail_rumah_id));

    const normalizeBerkasRows = (rows) => rows.map(({ file_name, dokumen_label, ...row }) => row);
    const rowError = (index, field) => form.errors[`berkas.${index}.${field}`] ?? form.errors[`berkas.${index}`] ?? '';

    useEffect(() => {
        const media = window.matchMedia('(max-width: 767px)');
        const update = () => setIsMobile(media.matches);

        update();
        media.addEventListener('change', update);

        return () => media.removeEventListener('change', update);
    }, []);

    useEffect(() => {
        const nextRows = buildInitialBerkasRows();
        setBerkasRows(nextRows);
        form.setData('berkas', normalizeBerkasRows(nextRows));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [dokumenOptions]);

    useEffect(() => {
        form.setData('berkas', normalizeBerkasRows(berkasRows));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [berkasRows]);

    const close = () => {
        form.reset();
        form.clearErrors();
        setSubmitError('');
        setBerkasRows(buildInitialBerkasRows());
        onClose();
    };

    const selectUnit = (unit) => {
        form.setData({
            ...form.data,
            detail_rumah_id: unit.id,
            harga_jual: unit.harga_jual ? String(unit.harga_jual) : form.data.harga_jual,
        });
    };

    const syncBerkas = (rows) => {
        setBerkasRows(rows);
        form.setData('berkas', normalizeBerkasRows(rows));
    };

    const updateBerkasRow = (index, patch) => {
        const nextRows = berkasRows.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row));
        syncBerkas(nextRows);
    };

    const submit = (event) => {
        event.preventDefault();
        setSubmitError('');
        form.setData('berkas', normalizeBerkasRows(berkasRows));
        form.post(baseUrl, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                const firstError = Object.values(errors ?? {})[0];
                setSubmitError(firstError ?? 'Gagal menyimpan SPR. Silakan cek isian form.');
            },
            onSuccess: close,
        });
    };

    const sprFieldsContent = (
        <div className="grid gap-4 lg:grid-cols-3">
            {submitError && (
                <div className="lg:col-span-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                    {submitError}
                </div>
            )}
            <div className="lg:col-span-3 sticky top-0 z-20 -mx-5 -mt-2 border-b border-silver-deep/60 bg-white/95 px-5 py-4 backdrop-blur dark:border-white/10 dark:bg-graphite/95">
                <div className="grid gap-4 lg:grid-cols-2">
                    <SearchPicker
                        className="lg:col-span-1"
                        label="Pilih Customer"
                        placeholder="Cari nama, no identitas, atau telepon"
                        rows={customers}
                        selected={selectedCustomer}
                        error={form.errors.costumer_id}
                        onSelect={(customer) => form.setData('costumer_id', customer.id)}
                    />
                    <SearchPicker
                        className="lg:col-span-1"
                        label="Pilih Unit Rumah"
                        placeholder="Cari blok, nomor rumah, atau perumahan"
                        rows={units}
                        selected={selectedUnit}
                        error={form.errors.detail_rumah_id}
                        onSelect={selectUnit}
                    />
                </div>
                <div className="mt-3 rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-3 text-xs font-semibold text-ink-soft dark:border-white/10 dark:bg-white/5 dark:text-white/60">
                    Unit yang sudah memiliki SPR aktif tetap terlihat saat dicari, tetapi tidak bisa dipilih. Jadi marketing tetap tahu unit itu sudah terpakai.
                </div>
            </div>
            <Input label="Tanggal SPR" type="date" value={form.data.tanggal_spr} error={form.errors.tanggal_spr} onChange={(event) => form.setData('tanggal_spr', event.target.value)} />
            <div className="grid gap-2">
                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Metode Pembayaran</span>
                <Dropdown
                    value={form.data.metode_pembayaran}
                    options={options.paymentOptions}
                    onChange={(value) => {
                        form.setData('metode_pembayaran', value);

                        if (value !== 'bertahap') {
                            form.setData('jumlah_termin', '');
                            form.setData('nominal_termin', '');
                        }
                    }}
                />
                {form.errors.metode_pembayaran && <span className="text-xs font-bold text-red-600">{form.errors.metode_pembayaran}</span>}
            </div>
            <CurrencyInput label="Harga Jual" value={form.data.harga_jual} error={form.errors.harga_jual} onChange={(value) => form.setData('harga_jual', value)} />
            <CurrencyInput label="Booking Fee" value={form.data.booking_fee} error={form.errors.booking_fee} onChange={(value) => form.setData('booking_fee', value)} />
            <CurrencyInput label="Uang Muka" value={form.data.uang_muka} error={form.errors.uang_muka} onChange={(value) => form.setData('uang_muka', value)} />
            <CurrencyInput label="Nilai Pengajuan KPR" value={form.data.nilai_pengajuan_kpr} error={form.errors.nilai_pengajuan_kpr} onChange={(value) => form.setData('nilai_pengajuan_kpr', value)} />
            {form.data.metode_pembayaran === 'bertahap' && (
                <>
                    <Input
                        label="Jumlah Termin"
                        type="number"
                        min="1"
                        value={form.data.jumlah_termin}
                        error={form.errors.jumlah_termin}
                        onChange={(event) => form.setData('jumlah_termin', event.target.value)}
                    />
                    <CurrencyInput
                        label="Nominal Termin"
                        value={form.data.nominal_termin}
                        error={form.errors.nominal_termin}
                        onChange={(value) => form.setData('nominal_termin', value)}
                    />
                </>
            )}
            <Textarea className="lg:col-span-3" label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
        </div>
    );

    const berkasFieldsContent = (
        <div className="grid gap-4">
            <p className="text-xs font-bold text-ink-soft dark:text-white/55">Form otomatis dibuat dari master jenis dokumen. Tinggal upload file pada setiap item.</p>
            <div className="grid gap-4">
                {berkasRows.map((row, index) => (
                    <div className="grid gap-3 rounded-lg border border-silver-deep/60 bg-white/70 p-3 dark:border-white/10 dark:bg-white/5 xl:grid-cols-[1.2fr_1fr_1fr]" key={row.dokumen_costumer_id || index}>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Jenis Dokumen</span>
                            <Input
                                value={row.dokumen_label ?? row.dokumen_costumer_id}
                                readOnly
                                disabled
                                inputClassName="cursor-not-allowed bg-silver-soft/70 text-ink-soft dark:bg-white/6 dark:text-white/70"
                            />
                        </div>
                        <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                            <span>File</span>
                            <input
                                className="min-h-11 w-full rounded-lg border border-silver-deep/70 bg-white/85 px-4 py-2.5 font-semibold text-ink outline-none ring-4 ring-transparent transition file:mr-4 file:rounded-md file:border-0 file:bg-ink file:px-4 file:py-2 file:font-extrabold file:text-white hover:file:bg-graphite dark:border-white/10 dark:bg-white/8 dark:text-white"
                                type="file"
                                onChange={(event) => {
                                    const file = event.target.files?.[0] ?? null;
                                    updateBerkasRow(index, {
                                        file_upload: file,
                                        file_name: file?.name ?? '',
                                    });
                                }}
                            />
                            {row.file_name && <span className="text-xs font-bold text-emerald-600 dark:text-emerald-300">{row.file_name}</span>}
                            {rowError(index, 'file_upload') && <span className="text-xs font-bold text-red-600 dark:text-red-300">{rowError(index, 'file_upload')}</span>}
                        </label>
                        <Input
                            label="Keterangan"
                            value={row.keterangan}
                            onChange={(event) => updateBerkasRow(index, 'keterangan', event.target.value)}
                        />
                    </div>
                ))}
            </div>
            {form.errors.berkas && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.berkas}</span>}
        </div>
    );

    return (
        <Modal open={open} onClose={close} title="Buat SPR" size="full">
            <Form
                collapsible={false}
                className="border-0 bg-transparent p-0 shadow-none dark:bg-transparent"
                contentClassName="grid-cols-1"
                onSubmit={submit}
                actions={(
                    <div className="sticky bottom-0 z-20 -mx-5 mt-6 flex flex-wrap justify-end gap-3 border-t border-silver-deep/60 bg-white/95 px-5 py-4 backdrop-blur dark:border-white/10 dark:bg-graphite/95">
                        <Button variant="outline" type="button" onClick={close}>
                            <XCircle size={17} /> Batal
                        </Button>
                        <Button disabled={form.processing} type="submit">
                            <FilePlus2 size={17} /> {form.processing ? 'Menyimpan...' : 'Simpan SPR'}
                        </Button>
                    </div>
                )}
            >
                {isMobile ? (
                    <Accordion
                        defaultOpen={0}
                        items={[
                            { title: 'Data SPR', content: sprFieldsContent },
                            { title: 'Berkas Customer', content: berkasFieldsContent },
                        ]}
                    />
                ) : (
                    <div className="grid gap-5 xl:grid-cols-[1fr_1.1fr]">
                        <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                            <h3 className="text-lg font-extrabold text-ink dark:text-white">Data SPR</h3>
                            <div className="mt-4">{sprFieldsContent}</div>
                        </section>
                        <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                            <h3 className="text-lg font-extrabold text-ink dark:text-white">Berkas Customer</h3>
                            <div className="mt-4">{berkasFieldsContent}</div>
                        </section>
                    </div>
                )}
            </Form>
        </Modal>
    );
}

function EditSprModal({ open, onClose, baseUrl, row, customers, units, options, dokumenOptions = [] }) {
    const [submitError, setSubmitError] = useState('');
    const [isMobile, setIsMobile] = useState(false);
    const buildInitialBerkasRows = () => dokumenOptions.map((dokumen) => {
        const existing = row?.berkas?.find((item) => Number(item.dokumen_costumer_id) === Number(dokumen.value));

        return {
            dokumen_costumer_id: dokumen.value,
            file_upload: null,
            keterangan: existing?.keterangan ?? '',
            file_name: existing?.nama_file ?? '',
            dokumen_label: dokumen.label,
            existing_file: existing ? {
                id: existing.id,
                nama_file: existing.nama_file,
                path_file: existing.path_file,
            } : null,
        };
    });
    const form = useForm({
        costumer_id: row?.costumer_id ? String(row.costumer_id) : '',
        detail_rumah_id: row?.detail_rumah_id ? String(row.detail_rumah_id) : '',
        tanggal_spr: row?.tanggal_spr ?? new Date().toISOString().slice(0, 10),
        metode_pembayaran: row?.metode_key ?? 'kpr_bank',
        harga_jual: row?.harga_jual ? String(row.harga_jual) : '',
        booking_fee: row?.booking_fee ? String(row.booking_fee) : '',
        uang_muka: row?.uang_muka ? String(row.uang_muka) : '',
        nilai_pengajuan_kpr: row?.nilai_pengajuan_kpr ? String(row.nilai_pengajuan_kpr) : '',
        jumlah_termin: row?.jumlah_termin ? String(row.jumlah_termin) : '',
        nominal_termin: row?.nominal_termin ? String(row.nominal_termin) : '',
        catatan: row?.catatan ?? '',
        berkas: buildInitialBerkasRows().map(({ file_name, dokumen_label, existing_file, ...data }) => data),
    });
    const [berkasRows, setBerkasRows] = useState(buildInitialBerkasRows());

    const selectedCustomer = customers.find((customer) => Number(customer.id) === Number(form.data.costumer_id));
    const selectedUnit = units.find((unit) => Number(unit.id) === Number(form.data.detail_rumah_id));
    const normalizeBerkasRows = (rows) => rows.map(({ file_name, dokumen_label, existing_file, ...rowData }) => rowData);
    const rowError = (index, field) => form.errors[`berkas.${index}.${field}`] ?? form.errors[`berkas.${index}`] ?? '';
    const calcTanahQty = Number(form.data.penambahan_tanah || 0);
    const calcTanahPrice = Number(form.data.harga_penambahan_tanah || 0);
    const calcTanah = calcTanahQty * calcTanahPrice;
    const calcLain = Number(form.data.harga_penambahan_lain_lain || 0);
    const calcTotal = calcTanah + calcLain;
    const calcFinal = Number(form.data.nilai_pengajuan_kpr || 0) + calcTotal;
    const isBertahap = form.data.metode_pembayaran === 'bertahap';
    const calcNominalTermin = isBertahap && Number(form.data.jumlah_termin || 0) > 0 ? Math.round(calcFinal / Number(form.data.jumlah_termin || 1)) : 0;

    useEffect(() => {
        const media = window.matchMedia('(max-width: 767px)');
        const update = () => setIsMobile(media.matches);

        update();
        media.addEventListener('change', update);

        return () => media.removeEventListener('change', update);
    }, []);

    useEffect(() => {
        if (!row) {
            return;
        }

        form.setData({
            costumer_id: row.costumer_id ? String(row.costumer_id) : '',
            detail_rumah_id: row.detail_rumah_id ? String(row.detail_rumah_id) : '',
            tanggal_spr: row.tanggal_spr ?? new Date().toISOString().slice(0, 10),
            metode_pembayaran: row.metode_key ?? 'kpr_bank',
            harga_jual: row.harga_jual ? String(row.harga_jual) : '',
            booking_fee: row.booking_fee ? String(row.booking_fee) : '',
            booking_fee_includes_dp: row.booking_fee_includes_dp ? '1' : '0',
            tanggal_pembayaran_booking_fee: row.tanggal_pembayaran_booking_fee ?? '',
            uang_muka: row.uang_muka ? String(row.uang_muka) : '',
            uang_muka_jumlah_pembayaran: row.uang_muka_jumlah_pembayaran ? String(row.uang_muka_jumlah_pembayaran) : '',
            tanggal_jatuh_tempo_dp: row.tanggal_jatuh_tempo_dp ?? '',
            nilai_pengajuan_kpr: row.nilai_pengajuan_kpr ? String(row.nilai_pengajuan_kpr) : '',
            penambahan_tanah: row.penambahan_tanah ? String(row.penambahan_tanah) : '',
            harga_penambahan_tanah: row.harga_penambahan_tanah ? String(row.harga_penambahan_tanah) : '',
            penambahan_lain_lain: row.penambahan_lain_lain ?? '',
            harga_penambahan_lain_lain: row.harga_penambahan_lain_lain ? String(row.harga_penambahan_lain_lain) : '',
            total_penambahan_tanah: row.total_penambahan_tanah ? String(row.total_penambahan_tanah) : '',
            total_penambahan_lain_lain: row.total_penambahan_lain_lain ? String(row.total_penambahan_lain_lain) : '',
            total_penambahan: row.total_penambahan ? String(row.total_penambahan) : '',
            nilai_pengajuan_akhir: row.nilai_pengajuan_akhir ? String(row.nilai_pengajuan_akhir) : '',
            jumlah_termin: row.jumlah_termin ? String(row.jumlah_termin) : '',
            nominal_termin: row.nominal_termin ? String(row.nominal_termin) : '',
            tanggal_jatuh_tempo_angsuran: row.tanggal_jatuh_tempo_angsuran ?? '',
            catatan: row.catatan ?? '',
        });
        form.clearErrors();
        setSubmitError('');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [row?.id]);

    useEffect(() => {
        form.setData('total_penambahan_tanah', String(calcTanah));
        form.setData('total_penambahan_lain_lain', String(calcLain));
        form.setData('total_penambahan', String(calcTotal));
        form.setData('nilai_pengajuan_akhir', String(calcFinal));
        if (isBertahap) {
            form.setData('nominal_termin', String(calcNominalTermin));
        } else {
            form.setData('nominal_termin', '');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [calcTanah, calcLain, calcTotal, calcFinal, calcNominalTermin, isBertahap]);

    useEffect(() => {
        const nextRows = buildInitialBerkasRows();
        setBerkasRows(nextRows);
        form.setData('berkas', normalizeBerkasRows(nextRows));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [row?.id, dokumenOptions]);

    const close = () => {
        form.reset();
        form.clearErrors();
        setSubmitError('');
        onClose();
    };

    const selectUnit = (unit) => {
        form.setData({
            ...form.data,
            detail_rumah_id: unit.id,
            harga_jual: unit.harga_jual ? String(unit.harga_jual) : form.data.harga_jual,
        });
    };

    const syncBerkas = (rows) => {
        setBerkasRows(rows);
        form.setData('berkas', normalizeBerkasRows(rows));
    };

    const updateBerkasRow = (index, patch) => {
        const nextRows = berkasRows.map((berkas, rowIndex) => (rowIndex === index ? { ...berkas, ...patch } : berkas));
        syncBerkas(nextRows);
    };

    const submit = (event) => {
        event.preventDefault();
        setSubmitError('');
        form.put(`${baseUrl}/${row.id}`, {
            forceFormData: true,
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                const firstError = Object.values(errors ?? {})[0];
                setSubmitError(firstError ?? 'Gagal menyimpan perubahan SPR. Silakan cek isian form.');
            },
            onSuccess: close,
        });
    };

    if (!row) {
        return null;
    }

    const editFieldsContent = (
        <div className="grid gap-4 lg:grid-cols-3">
            <FormErrorSummary errors={form.errors} />
            {submitError && (
                <div className="lg:col-span-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                    {submitError}
                </div>
            )}
            <div className="lg:col-span-3 sticky top-0 z-20 -mx-5 -mt-2 border-b border-silver-deep/60 bg-white/95 px-5 py-4 backdrop-blur dark:border-white/10 dark:bg-graphite/95">
                <div className="grid gap-4 lg:grid-cols-2">
                    <SearchPicker
                        className="lg:col-span-1"
                        label="Pilih Customer"
                        placeholder="Cari nama, no identitas, atau telepon"
                        rows={customers}
                        selected={selectedCustomer}
                        error={form.errors.costumer_id}
                        onSelect={(customer) => form.setData('costumer_id', customer.id)}
                    />
                    <SearchPicker
                        className="lg:col-span-1"
                        label="Pilih Unit Rumah"
                        placeholder="Cari blok, nomor rumah, atau perumahan"
                        rows={units}
                        selected={selectedUnit}
                        error={form.errors.detail_rumah_id}
                        onSelect={selectUnit}
                    />
                </div>
            </div>
            <Input label="Tanggal SPR" type="date" value={form.data.tanggal_spr} error={form.errors.tanggal_spr} onChange={(event) => form.setData('tanggal_spr', event.target.value)} />
            <div className="grid gap-2">
                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Metode Pembayaran</span>
                <Dropdown
                    value={form.data.metode_pembayaran}
                    options={options.paymentOptions}
                    onChange={(value) => {
                        form.setData('metode_pembayaran', value);

                        if (value !== 'bertahap') {
                            form.setData('jumlah_termin', '');
                            form.setData('nominal_termin', '');
                        }
                    }}
                />
                {form.errors.metode_pembayaran && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.metode_pembayaran}</span>}
            </div>
            <CurrencyInput label="Harga Jual" value={form.data.harga_jual} error={form.errors.harga_jual} onChange={(value) => form.setData('harga_jual', value)} />
            <CurrencyInput label="Booking Fee" value={form.data.booking_fee} error={form.errors.booking_fee} onChange={(value) => form.setData('booking_fee', value)} />
            <div className="grid gap-2">
                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Booking Fee Termasuk DP?</span>
                <Dropdown value={form.data.booking_fee_includes_dp} options={[{ value: '1', label: 'Ya' }, { value: '0', label: 'Tidak' }]} onChange={(value) => form.setData('booking_fee_includes_dp', value)} />
                {form.errors.booking_fee_includes_dp && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.booking_fee_includes_dp}</span>}
            </div>
            <Input label="Tanggal Pembayaran Booking Fee" type="date" value={form.data.tanggal_pembayaran_booking_fee} error={form.errors.tanggal_pembayaran_booking_fee} onChange={(event) => form.setData('tanggal_pembayaran_booking_fee', event.target.value)} />
            <CurrencyInput label="Uang Muka" value={form.data.uang_muka} error={form.errors.uang_muka} onChange={(value) => form.setData('uang_muka', value)} />
            <Input label="Uang Muka Dibayar Berapa Kali" type="number" min="1" value={form.data.uang_muka_jumlah_pembayaran} error={form.errors.uang_muka_jumlah_pembayaran} onChange={(event) => form.setData('uang_muka_jumlah_pembayaran', event.target.value)} />
            <Input label="Tanggal Jatuh Tempo DP" type="date" value={form.data.tanggal_jatuh_tempo_dp} error={form.errors.tanggal_jatuh_tempo_dp} onChange={(event) => form.setData('tanggal_jatuh_tempo_dp', event.target.value)} />
            <CurrencyInput label="Nilai Pengajuan KPR" value={form.data.nilai_pengajuan_kpr} error={form.errors.nilai_pengajuan_kpr} onChange={(value) => form.setData('nilai_pengajuan_kpr', value)} />
            <Input label="Penambahan Tanah (m2)" type="number" min="0" value={form.data.penambahan_tanah} error={form.errors.penambahan_tanah} onChange={(event) => form.setData('penambahan_tanah', event.target.value)} />
            <CurrencyInput label="Harga Penambahan Tanah" value={form.data.harga_penambahan_tanah} error={form.errors.harga_penambahan_tanah} onChange={(value) => form.setData('harga_penambahan_tanah', value)} />
            <CurrencyInput label="Total Harga Penambahan Tanah" value={form.data.total_penambahan_tanah} readOnly disabled onChange={() => {}} />
            <Input label="Penambahan Lain-Lain" value={form.data.penambahan_lain_lain} error={form.errors.penambahan_lain_lain} onChange={(event) => form.setData('penambahan_lain_lain', event.target.value)} />
            <CurrencyInput label="Harga Penambahan Lain-Lain" value={form.data.harga_penambahan_lain_lain} error={form.errors.harga_penambahan_lain_lain} onChange={(value) => form.setData('harga_penambahan_lain_lain', value)} />
            <CurrencyInput label="Total Harga Penambahan Lain-Lain" value={form.data.total_penambahan_lain_lain} readOnly disabled onChange={() => {}} />
            <CurrencyInput label="Total Penambahan" value={form.data.total_penambahan} readOnly disabled onChange={() => {}} />
            <CurrencyInput label="Hasil Akhir Nilai Pengajuan" value={form.data.nilai_pengajuan_akhir} readOnly disabled onChange={() => {}} />
            {form.data.metode_pembayaran === 'bertahap' && (
                <div className="grid gap-4 md:grid-cols-2 lg:col-span-3">
                    <Input
                        label="Jumlah Termin"
                        type="number"
                        min="1"
                        value={form.data.jumlah_termin}
                        error={form.errors.jumlah_termin}
                        onChange={(event) => form.setData('jumlah_termin', event.target.value)}
                    />
                    <CurrencyInput
                        label="Nominal Termin"
                        value={form.data.nominal_termin}
                        error={form.errors.nominal_termin}
                        readOnly
                        disabled
                        onChange={() => {}}
                    />
                    <Input label="Tanggal Jatuh Tempo Angsuran" type="date" value={form.data.tanggal_jatuh_tempo_angsuran} error={form.errors.tanggal_jatuh_tempo_angsuran} onChange={(event) => form.setData('tanggal_jatuh_tempo_angsuran', event.target.value)} />
                </div>
            )}
            <Textarea className="lg:col-span-3" label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
        </div>
    );

    const berkasFieldsContent = (
        <div className="grid gap-4">
            <p className="text-xs font-bold text-ink-soft dark:text-white/55">Upload ulang di baris dokumen yang salah. Jika file tidak dipilih, sistem mempertahankan file lama.</p>
            <div className="grid gap-4">
                {berkasRows.map((berkas, index) => (
                    <div className="grid gap-3 rounded-lg border border-silver-deep/60 bg-white/70 p-3 dark:border-white/10 dark:bg-white/5 xl:grid-cols-[1.2fr_1fr_1fr]" key={berkas.dokumen_costumer_id || index}>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Jenis Dokumen</span>
                            <Input
                                value={berkas.dokumen_label ?? berkas.dokumen_costumer_id}
                                readOnly
                                disabled
                                inputClassName="cursor-not-allowed bg-silver-soft/70 text-ink-soft dark:bg-white/6 dark:text-white/70"
                            />
                            {berkas.existing_file && (
                                <a
                                    className="text-xs font-bold text-emerald-600 underline decoration-dotted underline-offset-4 dark:text-emerald-300"
                                    href={`/storage/${berkas.existing_file.path_file}`}
                                    rel="noreferrer"
                                    target="_blank"
                                >
                                    File lama: {berkas.existing_file.nama_file}
                                </a>
                            )}
                        </div>
                        <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                            <span>File Baru</span>
                            <input
                                className="min-h-11 w-full rounded-lg border border-silver-deep/70 bg-white/85 px-4 py-2.5 font-semibold text-ink outline-none ring-4 ring-transparent transition file:mr-4 file:rounded-md file:border-0 file:bg-ink file:px-4 file:py-2 file:font-extrabold file:text-white hover:file:bg-graphite dark:border-white/10 dark:bg-white/8 dark:text-white"
                                type="file"
                                onChange={(event) => {
                                    const file = event.target.files?.[0] ?? null;
                                    updateBerkasRow(index, {
                                        file_upload: file,
                                        file_name: file?.name ?? '',
                                    });
                                }}
                            />
                            {berkas.file_name && <span className="text-xs font-bold text-emerald-600 dark:text-emerald-300">{berkas.file_name}</span>}
                            {rowError(index, 'file_upload') && <span className="text-xs font-bold text-red-600 dark:text-red-300">{rowError(index, 'file_upload')}</span>}
                        </label>
                        <Input
                            label="Keterangan"
                            value={berkas.keterangan}
                            onChange={(event) => updateBerkasRow(index, { keterangan: event.target.value })}
                        />
                    </div>
                ))}
            </div>
            {form.errors.berkas && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.berkas}</span>}
        </div>
    );

    const summaryContent = (
        <div className="grid gap-3 text-sm">
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Kode SPR</p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">{row.kode_spr}</p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Customer</p>
                <p className="mt-1 font-extrabold text-ink dark:text-white">{selectedCustomer?.label ?? row.customer}</p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">{selectedCustomer?.no_identitas ?? row.no_identitas ?? '-'}</p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Unit</p>
                <p className="mt-1 font-extrabold text-ink dark:text-white">{selectedUnit?.label ?? row.unit}</p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">{row.perumahan ?? '-'}</p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Status</p>
                <p className="mt-1 font-extrabold text-ink dark:text-white">{row.status_label}</p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">{row.record_status_label}</p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 text-xs font-semibold text-ink-soft dark:border-white/10 dark:bg-white/5 dark:text-white/60">
                Edit SPR mengikuti tampilan create, tetapi berkas customer tetap dikelola dari form pembuatan SPR.
            </div>
        </div>
    );

    return (
        <Modal key={row.id} open={open} onClose={close} title={`Edit SPR ${row.kode_spr}`} size="full">
            <Form
                collapsible={false}
                className="border-0 bg-transparent p-0 shadow-none dark:bg-transparent"
                contentClassName="grid-cols-1"
                onSubmit={submit}
                actions={(
                    <div className="sticky bottom-0 z-20 -mx-5 mt-6 flex flex-wrap justify-end gap-3 border-t border-silver-deep/60 bg-white/95 px-5 py-4 backdrop-blur dark:border-white/10 dark:bg-graphite/95">
                        <Button variant="outline" type="button" onClick={close}>
                            <XCircle size={17} /> Batal
                        </Button>
                        <Button disabled={form.processing} type="submit">
                            <FilePlus2 size={17} /> {form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    </div>
                )}
            >
                {isMobile ? (
                    <Accordion
                        defaultOpen={0}
                        items={[
                            { title: 'Data SPR', content: editFieldsContent },
                            { title: 'Berkas Customer', content: berkasFieldsContent },
                            { title: 'Ringkasan SPR', content: summaryContent },
                        ]}
                    />
                ) : (
                    <div className="grid gap-5 xl:grid-cols-[1fr_0.9fr]">
                        <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                            <h3 className="text-lg font-extrabold text-ink dark:text-white">Data SPR</h3>
                            <div className="mt-4">{editFieldsContent}</div>
                            <div className="mt-6">
                                <h3 className="text-lg font-extrabold text-ink dark:text-white">Berkas Customer</h3>
                                <div className="mt-4">{berkasFieldsContent}</div>
                            </div>
                        </section>
                        <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                            <h3 className="text-lg font-extrabold text-ink dark:text-white">Ringkasan SPR</h3>
                            <div className="mt-4">{summaryContent}</div>
                        </section>
                    </div>
                )}
            </Form>
        </Modal>
    );
}

export default function Index({ title, description, baseUrl, rows, filters = {}, customers = [], units = [], bankKreditOptions = [], dokumenOptions = [], options = {}, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [detailRow, setDetailRow] = useState(null);

    const submitSearch = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const approve = (row) => {
        router.post(`${baseUrl}/${row.id}/approve`, {}, { preserveScroll: true });
    };

    const reject = (row) => {
        const catatan = window.prompt(`Catatan penolakan SPR ${row.kode_spr}`, '');

        if (catatan === null) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/reject`, { catatan }, { preserveScroll: true });
    };

    const lockRow = (row) => {
        if (!window.confirm(`Lock SPR ${row.kode_spr}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };

    const unlockRow = (row) => {
        if (!window.confirm(`Buka lock SPR ${row.kode_spr}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };

    const editRowHandler = (row) => {
        router.visit(`${baseUrl}/${row.id}/edit`, {
            preserveScroll: true,
        });
    };

    const canApprove = (row) => {
        return (row.status === 'menunggu_manager' && permissions.canApproveManager) || (row.status === 'menunggu_owner' && permissions.canApproveOwner);
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
                        <Button type="button" onClick={() => router.visit(`${baseUrl}/create`, { preserveScroll: true })}>
                            <FilePlus2 size={18} /> Buat SPR
                        </Button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-end md:justify-between" onSubmit={submitSearch}>
                        <Input className="w-full md:max-w-md" icon={<Search size={17} />} label="Cari SPR" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['Kode', 'Customer', 'Unit', 'Metode', 'Bank KPR', 'Harga', 'Pengajuan KPR', 'Dibuat', 'Diupdate', 'Lock', 'Status', 'Aksi'].map((column) => (
                                        <th className={`px-4 py-3 font-extrabold ${column === 'Aksi' ? 'text-right' : ''}`} key={column}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id}>
                                        <td className="px-4 py-3 font-bold">{row.kode_spr}</td>
                                        <td className="px-4 py-3 font-semibold">{row.customer}</td>
                                        <td className="px-4 py-3 font-semibold">{row.unit}</td>
                                        <td className="px-4 py-3 font-semibold">{row.metode_pembayaran}</td>
                                        <td className="px-4 py-3 font-semibold">{row.metode_key === 'kpr_bank' ? row.bank_kredit : '-'}</td>
                                        <td className="px-4 py-3 font-semibold">{money(row.harga_jual)}</td>
                                        <td className="px-4 py-3 font-semibold">{money(row.nilai_pengajuan_kpr)}</td>
                                        <td className="px-4 py-3 font-semibold">{row.created_at ?? '-'}</td>
                                        <td className="px-4 py-3 font-semibold">{row.updated_at ?? '-'}</td>
                                        <td className="px-4 py-3 font-semibold">{row.record_status_label}</td>
                                        <td className="px-4 py-3 font-semibold">
                                            <span className="rounded-full bg-silver-soft px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.08em] text-ink-soft dark:bg-white/10 dark:text-white/60">{row.status_label}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button size="sm" type="button" variant="outline" onClick={() => setDetailRow(row)}>
                                                    <Eye size={15} /> Detail
                                                </Button>
                                        {row.record_status === 'locked' ? (
                                            row.can_unlock && <Button size="sm" type="button" variant="outline" onClick={() => unlockRow(row)}>
                                                <Unlock size={15} /> Unlock
                                            </Button>
                                        ) : (
                                            <>
                                                {row.can_lock && <Button size="sm" type="button" variant="outline" onClick={() => lockRow(row)}>
                                                    <Lock size={15} /> Lock
                                                </Button>}
                                                {row.status === 'menunggu_manager' || row.status === 'menunggu_owner' ? (
                                                    <Button size="sm" type="button" variant="outline" onClick={() => editRowHandler(row)}>
                                                        <Edit3 size={15} /> Edit
                                                    </Button>
                                                ) : (
                                                    <Button size="sm" type="button" variant="outline" disabled>
                                                        <Edit3 size={15} /> Edit
                                                    </Button>
                                                )}
                                            </>
                                        )}
                                                {canApprove(row) && (
                                                    <>
                                                        <Button size="sm" type="button" onClick={() => approve(row)}>
                                                            <CheckCircle2 size={15} /> ACC
                                                        </Button>
                                                        <Button
                                                            className="border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                                            size="sm"
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() => reject(row)}
                                                        >
                                                            Reject
                                                        </Button>
                                                    </>
                                                )}
                                                {!canApprove(row) && <span className="inline-flex items-center gap-2 text-xs font-bold text-ink-soft"><ShieldCheck size={14} /> {row.status_label}</span>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50" colSpan={12}>Belum ada SPR.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

            <DetailModal
                open={Boolean(detailRow)}
                onClose={() => setDetailRow(null)}
                row={detailRow}
                title={detailRow ? `Detail SPR ${detailRow.kode_spr}` : 'Detail SPR'}
                columns={[
                    { key: 'kode_spr', label: 'Kode SPR' },
                    { key: 'customer', label: 'Customer' },
                    { key: 'unit', label: 'Unit' },
                    { key: 'perumahan', label: 'Perumahan' },
                    { key: 'metode_pembayaran', label: 'Metode Pembayaran' },
                    { key: 'bank_kredit', label: 'Bank KPR' },
                    { key: 'harga_jual', label: 'Harga Jual', render: (row) => money(row.harga_jual) },
                    { key: 'nilai_pengajuan_kpr', label: 'Pengajuan KPR', render: (row) => money(row.nilai_pengajuan_kpr) },
                    { key: 'booking_fee', label: 'Booking Fee', render: (row) => money(row.booking_fee) },
                    { key: 'uang_muka', label: 'Uang Muka', render: (row) => money(row.uang_muka) },
                    { key: 'status_label', label: 'Status' },
                    { key: 'created_by', label: 'Marketing' },
                    { key: 'created_at', label: 'Dibuat' },
                    { key: 'updated_at', label: 'Diupdate' },
                    { key: 'record_status_label', label: 'Lock' },
                    { key: 'catatan', label: 'Catatan', full: true },
                ]}
            />
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'SPR'}>{page}</AdminLayout>;
