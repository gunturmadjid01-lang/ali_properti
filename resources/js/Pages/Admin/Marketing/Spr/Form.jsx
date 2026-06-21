import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, FilePlus2, Save, Search, XCircle } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Accordion, Button, CurrencyInput, Dropdown, Input, Textarea } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function SearchPicker({ label, placeholder, rows = [], selected, onSelect, error, className = 'md:col-span-2' }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState(selected?.label ?? '');
    const rootRef = useRef(null);

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) {
            return rows.slice(0, 10);
        }

        return rows.filter((row) => row.search?.includes(needle) || row.label?.toLowerCase().includes(needle)).slice(0, 10);
    }, [query, rows]);

    useEffect(() => {
        setQuery(selected?.label ?? '');
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
                onBlur={() => window.setTimeout(() => setOpen(false), 120)}
            />
            {open && (
                <div className="absolute left-0 right-0 top-full z-40 mt-1 grid gap-2 rounded-lg border border-silver-deep/70 bg-white p-2 shadow-soft dark:border-white/10 dark:bg-graphite">
                    <div className="max-h-72 overflow-y-auto">
                        {filtered.map((row) => (
                            <button
                                key={row.id}
                                type="button"
                                className={`w-full rounded-lg px-3 py-2 text-left text-sm font-bold transition ${
                                    selected?.id === row.id
                                        ? 'bg-ink text-white'
                                        : row.is_available === false
                                            ? 'cursor-not-allowed bg-silver/70 text-ink-soft/70 dark:bg-white/6 dark:text-white/35'
                                            : 'text-ink hover:bg-silver dark:text-white dark:hover:bg-white/10'
                                }`}
                                disabled={row.is_available === false}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => {
                                    setQuery(row.label ?? '');
                                    setOpen(false);
                                    onSelect(row);
                                }}
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

function SectionTitle({ title, description }) {
    return (
        <div className="space-y-1">
            <h3 className="text-lg font-extrabold text-ink dark:text-white">{title}</h3>
            {description && <p className="text-sm leading-6 text-ink-soft dark:text-white/60">{description}</p>}
        </div>
    );
}

export default function SprForm({ title, description, baseUrl, submitUrl, method = 'post', mode = 'create', row = {}, customers = [], units = [], bankKreditOptions = [], dokumenOptions = [], options = {} }) {
    const yesNoOptions = [
        { value: '1', label: 'Ya' },
        { value: '0', label: 'Tidak' },
    ];
    const buildInitialBerkasRows = () => dokumenOptions.map((dokumen) => {
        const existing = row?.berkas?.find((item) => Number(item.dokumen_costumer_id) === Number(dokumen.value));

        return {
            dokumen_costumer_id: dokumen.value,
            file_upload: null,
            keterangan: existing?.keterangan ?? '',
            file_name: existing?.nama_file ?? '',
            dokumen_label: dokumen.label,
            existing_file: existing ? { id: existing.id, nama_file: existing.nama_file, path_file: existing.path_file } : null,
        };
    });

    const [isMobile, setIsMobile] = useState(false);
    const [berkasRows, setBerkasRows] = useState(buildInitialBerkasRows());
    const form = useForm({
        costumer_id: row?.costumer_id ? String(row.costumer_id) : '',
        detail_rumah_id: row?.detail_rumah_id ? String(row.detail_rumah_id) : '',
        tanggal_spr: row?.tanggal_spr ?? new Date().toISOString().slice(0, 10),
        metode_pembayaran: row?.metode_key ?? 'kpr_bank',
        bank_kredit_id: row?.bank_kredit_id ? String(row.bank_kredit_id) : '',
        kpr_tenor_bulan: row?.kpr_tenor_bulan ? String(row.kpr_tenor_bulan) : '',
        kpr_bunga_tahunan: row?.kpr_bunga_tahunan ? String(row.kpr_bunga_tahunan) : '',
        harga_jual: row?.harga_jual ? String(row.harga_jual) : '',
        booking_fee: row?.booking_fee ? String(row.booking_fee) : '',
        booking_fee_includes_dp: row?.booking_fee_includes_dp ? '1' : '0',
        tanggal_pembayaran_booking_fee: row?.tanggal_pembayaran_booking_fee ?? '',
        uang_muka: row?.uang_muka ? String(row.uang_muka) : '',
        uang_muka_jumlah_pembayaran: row?.uang_muka_jumlah_pembayaran ? String(row.uang_muka_jumlah_pembayaran) : '',
        tanggal_jatuh_tempo_dp: row?.tanggal_jatuh_tempo_dp ?? '',
        nilai_pengajuan_kpr: row?.nilai_pengajuan_kpr ? String(row.nilai_pengajuan_kpr) : '',
        penambahan_tanah: row?.penambahan_tanah ?? '',
        harga_penambahan_tanah: row?.harga_penambahan_tanah ? String(row.harga_penambahan_tanah) : '',
        penambahan_lain_lain: row?.penambahan_lain_lain ?? '',
        harga_penambahan_lain_lain: row?.harga_penambahan_lain_lain ? String(row.harga_penambahan_lain_lain) : '',
        total_penambahan_tanah: row?.total_penambahan_tanah ? String(row.total_penambahan_tanah) : '',
        total_penambahan_lain_lain: row?.total_penambahan_lain_lain ? String(row.total_penambahan_lain_lain) : '',
        total_penambahan: row?.total_penambahan ? String(row.total_penambahan) : '',
        nilai_pengajuan_akhir: row?.nilai_pengajuan_akhir ? String(row.nilai_pengajuan_akhir) : '',
        jumlah_termin: row?.jumlah_termin ? String(row.jumlah_termin) : '',
        nominal_termin: row?.nominal_termin ? String(row.nominal_termin) : '',
        tanggal_jatuh_tempo_angsuran: row?.tanggal_jatuh_tempo_angsuran ?? '',
        catatan: row?.catatan ?? '',
        berkas: buildInitialBerkasRows().map(({ file_name, dokumen_label, existing_file, ...item }) => item),
    });

    const selectedCustomer = customers.find((customer) => Number(customer.id) === Number(form.data.costumer_id));
    const selectedUnit = units.find((unit) => Number(unit.id) === Number(form.data.detail_rumah_id));
    const selectedBankKredit = bankKreditOptions.find((bank) => String(bank.value) === String(form.data.bank_kredit_id));
    const currentBerkas = berkasRows;

    const calcTanahQty = Number(form.data.penambahan_tanah || 0);
    const calcTanahPrice = Number(form.data.harga_penambahan_tanah || 0);
    const calcTanah = calcTanahQty * calcTanahPrice;
    const calcLain = Number(form.data.harga_penambahan_lain_lain || 0);
    const calcTotal = calcTanah + calcLain;
    const calcFinal = Number(form.data.nilai_pengajuan_kpr || 0) + calcTotal;
    const isBertahap = form.data.metode_pembayaran === 'bertahap';
    const isKprBank = form.data.metode_pembayaran === 'kpr_bank';
    const calcNominalTermin = isBertahap && Number(form.data.jumlah_termin || 0) > 0 ? Math.round(calcFinal / Number(form.data.jumlah_termin || 1)) : 0;
    const kprRate = Number(form.data.kpr_bunga_tahunan || selectedBankKredit?.bunga_tahunan || 0);
    const kprMonths = Math.max(1, Number(form.data.kpr_tenor_bulan || selectedBankKredit?.tenor_max_bulan || 1));
    const kprPrincipal = Number(form.data.nilai_pengajuan_kpr || 0);
    const kprMonthlyRate = kprRate / 100 / 12;
    const kprInstallment = kprPrincipal <= 0 ? 0 : kprMonthlyRate > 0
        ? kprPrincipal * (kprMonthlyRate * ((1 + kprMonthlyRate) ** kprMonths)) / (((1 + kprMonthlyRate) ** kprMonths) - 1)
        : kprPrincipal / kprMonths;
    const kprMinimalDp = selectedBankKredit ? Math.round(Number(form.data.harga_jual || 0) * Number(selectedBankKredit.minimal_dp_persen || 0) / 100) : 0;
    const kprProvisi = selectedBankKredit ? Math.round(kprPrincipal * Number(selectedBankKredit.biaya_provisi_persen || 0) / 100) : 0;

    const syncBerkas = (rows) => {
        setBerkasRows(rows);
        form.setData('berkas', rows.map(({ file_name, dokumen_label, existing_file, ...item }) => item));
    };

    const updateBerkasRow = (index, patch) => {
        setBerkasRows((rows) => {
            const nextRows = rows.map((rowItem, rowIndex) => (rowIndex === index ? { ...rowItem, ...patch } : rowItem));
            form.setData('berkas', nextRows.map(({ file_name, dokumen_label, existing_file, ...item }) => item));
            return nextRows;
        });
    };

    useEffect(() => {
        const media = window.matchMedia('(max-width: 767px)');
        const update = () => setIsMobile(media.matches);
        update();
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, []);

    useEffect(() => {
        const nextRows = buildInitialBerkasRows();
        syncBerkas(nextRows);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [row?.id, dokumenOptions]);

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

    const selectedUnitInfo = useMemo(() => selectedUnit, [selectedUnit]);

    const submit = (event) => {
        event.preventDefault();
        const payload = {
            ...form.data,
            berkas: form.data.berkas,
        };

        if (method === 'put') {
            router.put(submitUrl, payload, {
                forceFormData: true,
                preserveScroll: true,
            });
            return;
        }

        router.post(submitUrl, payload, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const fieldsContent = (
        <div className="grid gap-4 lg:grid-cols-3">
            <div className="lg:col-span-3 sticky top-0 z-20 -mx-5 -mt-2 border-b border-silver-deep/60 bg-white/95 px-5 py-4 backdrop-blur dark:border-white/10 dark:bg-graphite/95">
                <div className="grid gap-4 lg:grid-cols-2">
                    <SearchPicker
                        className="lg:col-span-1"
                        label="Pilih Customer"
                        placeholder="Cari nama, no identitas, atau telepon"
                        rows={customers}
                        selected={selectedCustomer}
                        error={form.errors.costumer_id}
                        onSelect={(customer) => form.setData('costumer_id', String(customer.id))}
                    />
                    <SearchPicker
                        className="lg:col-span-1"
                        label="Pilih Unit Rumah"
                        placeholder="Cari blok, nomor rumah, atau perumahan"
                        rows={units}
                        selected={selectedUnit}
                        error={form.errors.detail_rumah_id}
                        onSelect={(unit) => {
                            form.setData('detail_rumah_id', String(unit.id));
                            form.setData('harga_jual', unit.harga_jual ? String(unit.harga_jual) : form.data.harga_jual);
                        }}
                    />
                </div>
            </div>

            <Input label="Tanggal SPR" type="date" value={form.data.tanggal_spr} error={form.errors.tanggal_spr} onChange={(event) => form.setData('tanggal_spr', event.target.value)} />
            <div className="grid gap-2">
                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Metode Pembayaran</span>
                <Dropdown
                    value={form.data.metode_pembayaran}
                    options={options.paymentOptions ?? []}
                    onChange={(value) => {
                        form.setData('metode_pembayaran', value);
                        if (value !== 'bertahap') {
                            form.setData('jumlah_termin', '');
                            form.setData('nominal_termin', '');
                            form.setData('tanggal_jatuh_tempo_angsuran', '');
                            form.setData('uang_muka_jumlah_pembayaran', '');
                        }
                        if (value !== 'kpr_bank') {
                            form.setData('bank_kredit_id', '');
                            form.setData('kpr_tenor_bulan', '');
                            form.setData('kpr_bunga_tahunan', '');
                        }
                    }}
                />
                {form.errors.metode_pembayaran && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.metode_pembayaran}</span>}
            </div>
            {isKprBank && (
                <div className="grid gap-4 lg:col-span-3 lg:grid-cols-3">
                    <div className="grid gap-2">
                        <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Bank Kredit</span>
                        <Dropdown
                            value={form.data.bank_kredit_id}
                            options={bankKreditOptions}
                            onChange={(value) => {
                                const bank = bankKreditOptions.find((item) => String(item.value) === String(value));
                                form.setData({
                                    ...form.data,
                                    bank_kredit_id: value,
                                    kpr_tenor_bulan: bank?.tenor_max_bulan ? String(bank.tenor_max_bulan) : form.data.kpr_tenor_bulan,
                                    kpr_bunga_tahunan: bank?.bunga_tahunan ? String(bank.bunga_tahunan) : form.data.kpr_bunga_tahunan,
                                });
                            }}
                        />
                        {form.errors.bank_kredit_id && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.bank_kredit_id}</span>}
                    </div>
                    <Input label="Tenor KPR (Bulan)" type="number" min={selectedBankKredit?.tenor_min_bulan ?? 1} max={selectedBankKredit?.tenor_max_bulan ?? undefined} value={form.data.kpr_tenor_bulan} error={form.errors.kpr_tenor_bulan} onChange={(event) => form.setData('kpr_tenor_bulan', event.target.value)} />
                    <Input label="Bunga KPR / Tahun (%)" type="number" step="0.01" value={form.data.kpr_bunga_tahunan} error={form.errors.kpr_bunga_tahunan} onChange={(event) => form.setData('kpr_bunga_tahunan', event.target.value)} />
                    {selectedBankKredit && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200 lg:col-span-3">
                            Minimal DP bank: {money(kprMinimalDp)} ({selectedBankKredit.minimal_dp_persen}%). Estimasi cicilan: {money(kprInstallment)} / bulan. Provisi: {money(kprProvisi)}. Admin: {money(selectedBankKredit.biaya_admin)}.
                        </div>
                    )}
                </div>
            )}
            <CurrencyInput label="Harga Jual" value={form.data.harga_jual} error={form.errors.harga_jual} onChange={(value) => form.setData('harga_jual', value)} />
            <CurrencyInput label="Booking Fee" value={form.data.booking_fee} error={form.errors.booking_fee} onChange={(value) => form.setData('booking_fee', value)} />
            <div className="grid gap-2">
                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Booking Fee Termasuk DP?</span>
                <Dropdown value={form.data.booking_fee_includes_dp} options={yesNoOptions} onChange={(value) => form.setData('booking_fee_includes_dp', value)} />
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
            {isBertahap && (
                <div className="grid gap-4 md:grid-cols-2 lg:col-span-3">
                    <Input label="Jumlah Termin" type="number" min="1" value={form.data.jumlah_termin} error={form.errors.jumlah_termin} onChange={(event) => form.setData('jumlah_termin', event.target.value)} />
                    <CurrencyInput label="Nominal Termin" value={form.data.nominal_termin} readOnly disabled onChange={() => {}} />
                    <Input label="Tanggal Jatuh Tempo Angsuran" type="date" value={form.data.tanggal_jatuh_tempo_angsuran} error={form.errors.tanggal_jatuh_tempo_angsuran} onChange={(event) => form.setData('tanggal_jatuh_tempo_angsuran', event.target.value)} />
                </div>
            )}

            <Textarea className="lg:col-span-3" label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
        </div>
    );

    const berkasContent = (
        <div className="grid gap-4">
            <p className="text-xs font-bold text-ink-soft dark:text-white/55">Form berkas otomatis dibuat dari master jenis dokumen. Tinggal upload file pada item yang diperlukan.</p>
            <div className="grid gap-4">
                {currentBerkas.map((berkas, index) => (
                    <div key={berkas.dokumen_costumer_id || index} className="grid gap-3 rounded-lg border border-silver-deep/60 bg-white/70 p-3 dark:border-white/10 dark:bg-white/5 xl:grid-cols-[1.2fr_1fr_1fr]">
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Jenis Dokumen</span>
                            <Input value={berkas.dokumen_label ?? berkas.dokumen_costumer_id} readOnly disabled inputClassName="cursor-not-allowed bg-silver-soft/70 text-ink-soft dark:bg-white/6 dark:text-white/70" />
                            {berkas.existing_file && (
                                <a className="text-xs font-bold text-emerald-600 underline decoration-dotted underline-offset-4 dark:text-emerald-300" href={`/storage/${berkas.existing_file.path_file}`} rel="noreferrer" target="_blank">
                                    File lama: {berkas.existing_file.nama_file}
                                </a>
                            )}
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
                            {berkas.file_name && <span className="text-xs font-bold text-emerald-600 dark:text-emerald-300">{berkas.file_name}</span>}
                        </label>
                        <Input label="Keterangan" value={berkas.keterangan} onChange={(event) => updateBerkasRow(index, { keterangan: event.target.value })} />
                    </div>
                ))}
            </div>
        </div>
    );

    const summaryContent = (
        <div className="grid gap-3 text-sm">
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Customer</p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">{selectedCustomer?.label ?? '-'}</p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">{selectedCustomer?.telepon ?? '-'}</p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Unit</p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">{selectedUnitInfo?.label ?? '-'}</p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    {selectedUnitInfo ? `LT ${selectedUnitInfo.luas_tanah ?? '-'} | LB ${selectedUnitInfo.luas_bangunan ?? '-'} | ${selectedUnitInfo.status_penjualan ?? '-'}` : '-'}
                </p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Harga Jual</p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">{money(form.data.harga_jual)}</p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Booking Fee</p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">{money(form.data.booking_fee)}</p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    {form.data.booking_fee_includes_dp === '1' ? 'Termasuk DP' : 'Tidak termasuk DP'}
                </p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    Dibayar: {form.data.tanggal_pembayaran_booking_fee || '-'}
                </p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Uang Muka</p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">{money(form.data.uang_muka)}</p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    Dibayar {form.data.uang_muka_jumlah_pembayaran || 0} kali
                </p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    Jatuh tempo DP: {form.data.tanggal_jatuh_tempo_dp || '-'}
                </p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Total Penambahan</p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">{money(calcTotal)}</p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">Tanah: {calcTanahQty || 0} m2 x {money(calcTanahPrice)} = {money(calcTanah)} | Lain-lain: {money(calcLain)}</p>
            </div>
            {isBertahap && (
                <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                    <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Skema Bertahap</p>
                    <p className="mt-1 text-base font-extrabold text-ink dark:text-white">Bertahap</p>
                    <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                        Nominal termin dihitung otomatis dari hasil akhir dibagi jumlah termin.
                    </p>
                </div>
            )}
            {isKprBank && (
                <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                    <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">KPR Bank</p>
                    <p className="mt-1 text-base font-extrabold text-ink dark:text-white">{selectedBankKredit?.label ?? row?.bank_kredit ?? '-'}</p>
                    <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                        {kprRate || 0}% / tahun, {kprMonths} bulan, cicilan estimasi {money(kprInstallment)} / bulan
                    </p>
                </div>
            )}
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Hasil Akhir</p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">{money(calcFinal)}</p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">Status</p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">{row?.status_label ?? 'Draft'}</p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">{row?.record_status_label ?? 'Draft'}</p>
            </div>
        </div>
    );

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
                        <Button as="a" href={baseUrl} variant="outline">
                            <ArrowLeft size={16} /> Kembali
                        </Button>
                    </div>
                </section>

                <form className="grid gap-5" onSubmit={submit}>
                    {isMobile ? (
                        <Accordion
                            defaultOpen={0}
                            items={[
                                { title: 'Data SPR', content: fieldsContent },
                                { title: 'Berkas Customer', content: berkasContent },
                                { title: 'Ringkasan', content: summaryContent },
                            ]}
                        />
                    ) : (
                        <div className="grid gap-5 xl:grid-cols-[1.65fr_0.65fr]">
                            <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                                <SectionTitle title="Data SPR" description="Lengkapi data transaksi, penambahan, dan termin." />
                                <div className="mt-4">{fieldsContent}</div>
                                <div className="mt-6">
                                    <SectionTitle title="Berkas Customer" description="Setiap jenis dokumen diambil dari master dokument customer." />
                                    <div className="mt-4">{berkasContent}</div>
                                </div>
                            </section>
                            <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                                <SectionTitle title="Ringkasan SPR" description="Lihat ringkasan customer, unit, dan total akhir." />
                                <div className="mt-4">{summaryContent}</div>
                            </section>
                        </div>
                    )}

                    <div className="sticky bottom-0 z-20 flex flex-wrap justify-end gap-3 border-t border-silver-deep/60 bg-white/95 px-5 py-4 backdrop-blur dark:border-white/10 dark:bg-graphite/95">
                        <Button as="a" href={baseUrl} variant="outline" type="button">
                            <XCircle size={17} /> Batal
                        </Button>
                        <Button disabled={form.processing} type="submit">
                            <Save size={17} /> {form.processing ? 'Menyimpan...' : mode === 'edit' ? 'Simpan Perubahan' : 'Simpan SPR'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

SprForm.layout = (page) => <AdminLayout title={page?.props?.title ?? 'SPR'}>{page}</AdminLayout>;
