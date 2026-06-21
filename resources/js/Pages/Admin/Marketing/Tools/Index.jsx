import { Head, router, useForm } from '@inertiajs/react';
import { BadgePercent, BarChart3, Calculator, Home, Search, Send, Trophy, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button, CurrencyInput, Dropdown, Input, Textarea } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value ?? 0));

function Card({ children, className = '' }) {
    return <section className={`rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8 ${className}`}>{children}</section>;
}

function Badge({ children, tone = 'neutral' }) {
    const cls = tone === 'good'
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
        : tone === 'bad'
            ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
            : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200';
    return <span className={`rounded-full px-3 py-1 text-[11px] font-extrabold ${cls}`}>{children}</span>;
}

function FilterBar({ baseUrl, filters = {}, perumahanOptions = [], statusOptions = [] }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [perumahanId, setPerumahanId] = useState(filters.perumahan_id ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const submit = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search, perumahan_id: perumahanId, status }, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <Card className="p-4">
            <form className="grid gap-3 md:grid-cols-[minmax(220px,1fr)_220px_200px_auto] md:items-end" onSubmit={submit}>
                <Input icon={<Search size={16} />} label="Cari" value={search} onChange={(event) => setSearch(event.target.value)} />
                {perumahanOptions.length > 0 && <Dropdown label="Perumahan" value={perumahanId} options={perumahanOptions} onChange={setPerumahanId} />}
                {statusOptions.length > 0 && <Dropdown label="Status" value={status} options={statusOptions} onChange={setStatus} />}
                <Button type="submit"><Search size={16} /> Cari</Button>
            </form>
        </Card>
    );
}

function DateRangeFilter({ baseUrl, filters = {}, data = {} }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? data.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? data.date_to ?? '');
    const submit = (event) => {
        event.preventDefault();
        router.get(baseUrl, { date_from: dateFrom, date_to: dateTo }, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <Card className="p-4">
            <form className="grid gap-3 md:grid-cols-[220px_220px_auto_1fr] md:items-end" onSubmit={submit}>
                <Input label="Dari Tanggal" type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
                <Input label="Sampai Tanggal" type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
                <Button type="submit"><Search size={16} /> Terapkan</Button>
                <p className="text-sm font-semibold text-ink-soft dark:text-white/55">
                    Data follow up, survey, SPR, dan reminder telat dihitung sesuai rentang tanggal.
                </p>
            </form>
        </Card>
    );
}

function UnitStock({ data, baseUrl, filters }) {
    const stats = [
        ['Tersedia', data.summary?.tersedia ?? 0],
        ['Booking', data.summary?.booking ?? 0],
        ['DP', data.summary?.dp ?? 0],
        ['Proses', data.summary?.proses ?? 0],
        ['Terjual', data.summary?.terjual ?? 0],
        ['Hold', data.summary?.hold ?? 0],
    ];

    return (
        <div className="grid gap-4">
            <FilterBar baseUrl={baseUrl} filters={filters} perumahanOptions={data.perumahanOptions} statusOptions={data.statusOptions} />
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                {stats.map(([label, value]) => <Card className="p-4" key={label}><p className="text-xs font-bold text-ink-soft">{label}</p><strong className="mt-1 block text-2xl">{value}</strong></Card>)}
            </div>
            <UnitTable rows={data.rows ?? []} showPrice />
        </div>
    );
}

function UnitTable({ rows = [], showPrice = false, showPricelist = false }) {
    return (
        <Card className="overflow-hidden">
            <div className="overflow-x-auto">
                <table className="min-w-full text-xs">
                    <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                        <tr>
                            {['Perumahan', 'Unit', 'Tipe', 'Luas', showPrice ? 'Harga' : null, showPricelist ? 'Booking' : null, showPricelist ? 'DP 10%' : null, showPricelist ? 'DP 20%' : null, 'Pembangunan', 'Status'].filter(Boolean).map((col) => <th className="px-4 py-3" key={col}>{col}</th>)}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                        {rows.map((row) => (
                            <tr key={row.id}>
                                <td className="px-4 py-3 font-semibold">{row.perumahan}</td>
                                <td className="px-4 py-3 font-bold">{row.unit}</td>
                                <td className="px-4 py-3">{row.tipe}<br /><span className="text-ink-soft">{row.model}</span></td>
                                <td className="px-4 py-3">LB {row.luas_bangunan}<br />LT {row.luas_tanah}</td>
                                {showPrice && <td className="px-4 py-3 font-bold">{money(row.harga_jual)}</td>}
                                {showPricelist && <td className="px-4 py-3">{money(row.booking_fee_saran)}</td>}
                                {showPricelist && <td className="px-4 py-3">{money(row.dp_10)}</td>}
                                {showPricelist && <td className="px-4 py-3">{money(row.dp_20)}</td>}
                                <td className="px-4 py-3">{row.status_pembangunan}<br /><span className="text-ink-soft">{row.progress}%</span></td>
                                <td className="px-4 py-3"><Badge tone={row.status_penjualan === 'tersedia' ? 'good' : row.status_penjualan === 'terjual' ? 'bad' : 'neutral'}>{row.status_penjualan}</Badge></td>
                            </tr>
                        ))}
                        {rows.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={10}>Belum ada data.</td></tr>}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}

function Pricelist({ data, baseUrl, filters }) {
    return (
        <div className="grid gap-4">
            <FilterBar baseUrl={baseUrl} filters={filters} perumahanOptions={data.perumahanOptions} />
            <UnitTable rows={data.rows ?? []} showPrice showPricelist />
        </div>
    );
}

function Simulation({ data }) {
    const [unitId, setUnitId] = useState(data.units?.[0]?.value ?? '');
    const [method, setMethod] = useState('kpr');
    const [bankId, setBankId] = useState(data.banks?.[0]?.value ?? '');
    const [dp, setDp] = useState('0');
    const selectedBank = data.banks?.find((bank) => String(bank.value) === String(bankId));
    const [tenor, setTenor] = useState(String(selectedBank?.tenor_max_bulan ?? 120));
    const [rate, setRate] = useState(String(selectedBank?.bunga_tahunan ?? 7.5));
    const selectedUnit = data.units?.find((unit) => unit.value === unitId);
    const price = Number(selectedUnit?.harga_jual ?? 0);
    const dpValue = Number(dp || 0);
    const principal = Math.max(0, price - dpValue);
    const monthlyRate = Number(rate || 0) / 100 / 12;
    const months = Math.max(1, Number(tenor || 1));
    const installment = method === 'kpr'
        ? monthlyRate > 0
            ? principal * (monthlyRate * ((1 + monthlyRate) ** months)) / (((1 + monthlyRate) ** months) - 1)
            : principal / months
        : principal / months;
    const minimumDp = selectedBank ? Math.round(price * Number(selectedBank.minimal_dp_persen || 0) / 100) : 0;
    const provision = selectedBank ? Math.round(principal * Number(selectedBank.biaya_provisi_persen || 0) / 100) : 0;
    const upfrontCost = dpValue + provision + Number(selectedBank?.biaya_admin ?? 0);

    return (
        <div className="grid gap-5 xl:grid-cols-[420px_1fr]">
            <Card className="p-5">
                <div className="grid gap-4">
                    <Dropdown label="Unit" value={unitId} options={data.units ?? []} onChange={setUnitId} />
                    <Dropdown label="Metode" value={method} options={[{ value: 'cash', label: 'Cash' }, { value: 'bertahap', label: 'Bertahap' }, { value: 'kpr', label: 'KPR Bank' }, { value: 'kpr_developer', label: 'KPR Developer' }]} onChange={setMethod} />
                    {method === 'kpr' && (
                        <Dropdown
                            label="Bank Kredit"
                            value={bankId}
                            options={data.banks ?? []}
                            onChange={(value) => {
                                const bank = data.banks?.find((item) => String(item.value) === String(value));
                                setBankId(value);
                                setTenor(String(bank?.tenor_max_bulan ?? tenor));
                                setRate(String(bank?.bunga_tahunan ?? rate));
                            }}
                        />
                    )}
                    <CurrencyInput label="DP / Uang Muka" value={dp} onChange={setDp} />
                    <Input label="Tenor Bulan" type="number" min={method === 'kpr' ? selectedBank?.tenor_min_bulan : 1} max={method === 'kpr' ? selectedBank?.tenor_max_bulan : undefined} value={tenor} onChange={(event) => setTenor(event.target.value)} />
                    {(method === 'kpr' || method === 'kpr_developer') && <Input label="Bunga per Tahun %" type="number" step="0.01" value={rate} onChange={(event) => setRate(event.target.value)} />}
                </div>
            </Card>
            <Card className="p-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div><p className="text-xs font-bold uppercase text-ink-soft">Harga Unit</p><strong className="mt-1 block text-2xl">{money(price)}</strong></div>
                    <div><p className="text-xs font-bold uppercase text-ink-soft">DP</p><strong className="mt-1 block text-2xl">{money(dpValue)}</strong></div>
                    <div><p className="text-xs font-bold uppercase text-ink-soft">Pokok Pembiayaan</p><strong className="mt-1 block text-2xl">{money(principal)}</strong></div>
                    <div><p className="text-xs font-bold uppercase text-ink-soft">Estimasi Cicilan</p><strong className="mt-1 block text-2xl text-emerald-600">{method === 'cash' ? money(price) : `${money(installment)} / bulan`}</strong></div>
                    {method === 'kpr' && (
                        <>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Minimal DP Bank</p><strong className="mt-1 block text-2xl">{money(minimumDp)}</strong></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Biaya Awal Estimasi</p><strong className="mt-1 block text-2xl">{money(upfrontCost)}</strong></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Provisi</p><strong className="mt-1 block text-2xl">{money(provision)}</strong></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Admin Bank</p><strong className="mt-1 block text-2xl">{money(selectedBank?.biaya_admin ?? 0)}</strong></div>
                        </>
                    )}
                </div>
            </Card>
        </div>
    );
}

function Communication({ rows = [] }) {
    return <div className="grid gap-4">{rows.map((row) => (
        <Card className="p-5" key={row.id}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div><strong>{row.customer}</strong><p className="text-sm text-ink-soft">{row.telepon} · {row.status}</p></div>
                <Badge>{row.follow_ups.length} follow up</Badge>
            </div>
            <div className="mt-4 grid gap-3 md:grid-cols-2">
                <div className="grid gap-2">{row.follow_ups.map((item, index) => <div className="rounded-lg bg-silver-soft/80 p-3 text-sm dark:bg-white/5" key={index}><b>{item.tanggal} · {item.metode}</b><p>{item.catatan || '-'}</p><small>{item.user}</small></div>)}</div>
                <div className="grid content-start gap-2">{row.reminders.map((item, index) => <div className="rounded-lg border border-silver-deep/60 p-3 text-sm dark:border-white/10" key={index}><b>{item.judul}</b><p>{item.tanggal}</p><Badge>{item.status}</Badge></div>)}</div>
            </div>
        </Card>
    ))}{rows.length === 0 && <Card className="p-10 text-center font-bold text-ink-soft">Belum ada data.</Card>}</div>;
}

function SimpleTable({ rows = [], columns = [] }) {
    return (
        <Card className="overflow-hidden">
            <div className="overflow-x-auto">
                <table className="min-w-full text-xs">
                    <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                        <tr>{columns.map((col) => <th className="px-4 py-3" key={col.key}>{col.label}</th>)}</tr>
                    </thead>
                    <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                        {rows.map((row, index) => <tr key={row.id ?? index}>{columns.map((col) => <td className="px-4 py-3 font-semibold" key={col.key}>{col.render ? col.render(row) : row[col.key]}</td>)}</tr>)}
                        {rows.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={columns.length}>Belum ada data.</td></tr>}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}

function Distribution({ data, baseUrl }) {
    const form = useForm({ costumer_id: '', user_id: '' });
    const submit = (event) => {
        event.preventDefault();
        form.post('/admin/marketing/tools/distribusi-lead/assign', { preserveScroll: true, onSuccess: () => form.reset() });
    };
    return (
        <div className="grid gap-4">
            <Card className="p-4">
                <form className="grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end" onSubmit={submit}>
                    <Dropdown label="Lead" value={form.data.costumer_id} options={(data.rows ?? []).map((row) => ({ value: String(row.id), label: `${row.customer} - ${row.telepon ?? '-'}` }))} onChange={(value) => form.setData('costumer_id', value)} />
                    <Dropdown label="Marketing" value={form.data.user_id} options={data.marketingOptions ?? []} onChange={(value) => form.setData('user_id', value)} />
                    <Button disabled={form.processing} type="submit"><Send size={16} /> Assign</Button>
                </form>
            </Card>
            <SimpleTable rows={data.rows} columns={[
                { key: 'kode', label: 'Kode' },
                { key: 'customer', label: 'Customer' },
                { key: 'telepon', label: 'Telepon' },
                { key: 'sumber', label: 'Sumber' },
                { key: 'status', label: 'Status' },
                { key: 'marketing', label: 'Marketing' },
            ]} />
        </div>
    );
}

function DiscountApproval({ rows = [] }) {
    return (
        <div className="grid gap-4">
            <Card className="p-5">
                <div className="grid gap-4 md:grid-cols-4">
                    <CurrencyInput label="Harga Normal" value="" onChange={() => {}} />
                    <CurrencyInput label="Harga Request" value="" onChange={() => {}} />
                    <Input label="Diskon %" readOnly value="-" />
                    <Textarea className="md:col-span-4" label="Catatan Approval" placeholder="Catatan analisa diskon/promo khusus" />
                </div>
            </Card>
            <SimpleTable rows={rows} columns={[
                { key: 'kode_spr', label: 'SPR' },
                { key: 'customer', label: 'Customer' },
                { key: 'marketing', label: 'Marketing' },
                { key: 'harga_jual', label: 'Harga', render: (row) => money(row.harga_jual) },
                { key: 'nilai_akhir', label: 'Nilai Akhir', render: (row) => money(row.nilai_akhir) },
                { key: 'status', label: 'Status' },
            ]} />
        </div>
    );
}

export default function Index({ title, section, baseUrl, filters = {}, data = {} }) {
    const content = {
        'unit-stock': <UnitStock data={data} baseUrl={baseUrl} filters={filters} />,
        pricelist: <Pricelist data={data} baseUrl={baseUrl} filters={filters} />,
        'simulasi-pembayaran': <Simulation data={data} />,
        'riwayat-komunikasi': <Communication rows={data.rows ?? []} />,
        'hot-lead': <SimpleTable rows={data.rows} columns={[
            { key: 'customer', label: 'Customer' },
            { key: 'telepon', label: 'Telepon' },
            { key: 'sumber', label: 'Sumber' },
            { key: 'status', label: 'Status' },
            { key: 'progress', label: 'Progress' },
            { key: 'last_follow_up', label: 'Follow Up Terakhir' },
            { key: 'catatan', label: 'Catatan' },
        ]} />,
        'distribusi-lead': <Distribution data={data} baseUrl={baseUrl} />,
        'monitoring-aktivitas': (
            <div className="grid gap-4">
                <DateRangeFilter baseUrl={baseUrl} filters={filters} data={data} />
                <SimpleTable rows={data.rows} columns={[
                    { key: 'name', label: 'Marketing' },
                    { key: 'lead', label: 'Total Lead' },
                    { key: 'follow_up', label: 'Follow Up' },
                    { key: 'survey', label: 'Survey' },
                    { key: 'spr', label: 'SPR' },
                    { key: 'overdue', label: 'Reminder Telat' },
                ]} />
            </div>
        ),
        'approval-diskon': <DiscountApproval rows={data.rows ?? []} />,
        'aging-lead': <SimpleTable rows={data.rows} columns={[
            { key: 'customer', label: 'Customer' },
            { key: 'telepon', label: 'Telepon' },
            { key: 'marketing', label: 'Marketing' },
            { key: 'status', label: 'Status' },
            { key: 'last_activity', label: 'Aktivitas Terakhir' },
            { key: 'age_days', label: 'Umur Hari' },
        ]} />,
        'leaderboard-sales': <SimpleTable rows={data.rows} columns={[
            { key: 'rank', label: 'Rank' },
            { key: 'name', label: 'Marketing' },
            { key: 'lead', label: 'Lead' },
            { key: 'spr', label: 'SPR' },
            { key: 'nilai', label: 'Nilai Penjualan', render: (row) => money(row.nilai) },
        ]} />,
    }[section];

    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <Card className="p-6">
                    <div className="flex items-center gap-3">
                        <span className="grid h-11 w-11 place-items-center rounded-lg bg-ink text-white dark:bg-white dark:text-ink">
                            {section === 'simulasi-pembayaran' ? <Calculator size={20} /> : section === 'leaderboard-sales' ? <Trophy size={20} /> : section === 'approval-diskon' ? <BadgePercent size={20} /> : section === 'unit-stock' ? <Home size={20} /> : section.includes('lead') ? <Users size={20} /> : <BarChart3 size={20} />}
                        </span>
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Marketing Tools</p>
                            <h1 className="text-2xl font-extrabold">{title}</h1>
                        </div>
                    </div>
                </Card>
                {content}
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Marketing Tools'}>{page}</AdminLayout>;
