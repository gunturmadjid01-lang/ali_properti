import { Head, router, useForm } from '@inertiajs/react';
import {
    Activity, Banknote, BarChart3, BookOpen, Eye, Landmark, Library, ListTree,
    Plus, Scale, TrendingDown, TrendingUp, WalletCards,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button, CurrencyInput, Dropdown, Input, Modal, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value ?? 0));

function Card({ children, className = '' }) {
    return <section className={`rounded-lg border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8 ${className}`}>{children}</section>;
}

function FilterBar({ baseUrl, filters, options, showAccount = false }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [perumahanId, setPerumahanId] = useState(filters.perumahan_id ?? '');
    const [accountId, setAccountId] = useState(filters.account_id ?? '');

    const submit = (event) => {
        event.preventDefault();
        router.get(baseUrl, {
            date_from: dateFrom,
            date_to: dateTo,
            perumahan_id: perumahanId,
            account_id: accountId,
        }, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <Card className="p-4">
            <form className={`grid gap-3 ${showAccount ? 'xl:grid-cols-[210px_210px_240px_minmax(260px,1fr)_auto]' : 'lg:grid-cols-[210px_210px_minmax(260px,1fr)_auto]'} items-end`} onSubmit={submit}>
                <Input label="Dari Tanggal" type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
                <Input label="Sampai Tanggal" type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
                <Dropdown label="Perumahan" value={perumahanId} options={options.perumahans ?? []} onChange={setPerumahanId} />
                {showAccount && <Dropdown label="Akun" value={accountId} options={options.accounts ?? []} onChange={setAccountId} />}
                <Button type="submit">Terapkan</Button>
            </form>
        </Card>
    );
}

function StatGrid({ rows }) {
    return <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">{rows.map(([label, value, tone]) => (
        <Card className="p-4" key={label}>
            <p className="text-xs font-extrabold uppercase text-ink-soft">{label}</p>
            <strong className={`mt-2 block break-words text-xl ${tone ?? ''}`}>{value}</strong>
        </Card>
    ))}</div>;
}

function Table({ columns, rows = [], detailTitle = 'Detail' }) {
    const [detail, setDetail] = useState(null);
    return (
        <>
            <Card className="overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-silver-soft/80 text-left text-xs uppercase text-ink-soft dark:bg-white/5">
                            <tr>
                                {columns.map((column) => <th className="px-4 py-3" key={column.key}>{column.label}</th>)}
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {rows.map((row, index) => <tr key={row.id ?? index}>
                                {columns.map((column) => <td className="px-4 py-3" key={column.key}>{column.render ? column.render(row) : row[column.key]}</td>)}
                                <td className="px-4 py-3 text-right"><Button size="sm" type="button" variant="outline" onClick={() => setDetail(row)}><Eye size={14} /> Detail</Button></td>
                            </tr>)}
                            {rows.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={columns.length + 1}>Belum ada data.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </Card>
            <Modal open={Boolean(detail)} onClose={() => setDetail(null)} title={detailTitle} size="lg">
                {detail && <div className="grid gap-3 sm:grid-cols-2">{columns.map((column) => (
                    <div className="rounded-lg bg-silver-soft/70 p-3 dark:bg-white/5" key={column.key}>
                        <p className="text-xs font-bold uppercase text-ink-soft">{column.label}</p>
                        <div className="mt-1 font-semibold">{column.render ? column.render(detail) : String(detail[column.key] ?? '-')}</div>
                    </div>
                ))}</div>}
            </Modal>
        </>
    );
}

function Dashboard({ data }) {
    const stats = data.stats ?? {};
    return <div className="grid gap-4">
        <StatGrid rows={[
            ['Saldo Kas/Bank', money(stats.cash_balance), 'text-emerald-600'],
            ['Pemasukan', money(stats.cash_in)],
            ['Pengeluaran', money(stats.cash_out), 'text-red-600'],
            ['Piutang', money(stats.receivable)],
            ['Hutang', money(stats.payable)],
            ['Laba Bersih', money(stats.profit), Number(stats.profit) < 0 ? 'text-red-600' : 'text-emerald-600'],
        ]} />
        <Card className="p-5">
            <h2 className="text-lg font-extrabold">Pergerakan Kas Bulanan</h2>
            <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                {(data.monthly ?? []).map((row) => <div className="rounded-lg border border-silver-deep/60 p-4 dark:border-white/10" key={row.month}>
                    <strong>{row.month}</strong>
                    <p className="mt-2 text-sm text-emerald-600">Masuk {money(row.in)}</p>
                    <p className="text-sm text-red-600">Keluar {money(row.out)}</p>
                </div>)}
            </div>
        </Card>
        <Table detailTitle="Detail Jurnal" rows={data.recent_journals ?? []} columns={journalColumns} />
    </div>;
}

function ManualTransaction({ data, options, canCreate }) {
    const initialProperty = options.perumahans?.find((row) => row.value)?.value ?? '';
    const form = useForm({
        perumahan_id: initialProperty,
        master_bank_id: '',
        tipe_post_id: '',
        tanggal: new Date().toISOString().slice(0, 10),
        nominal: '',
        nomor_referensi: '',
        keterangan: '',
    });
    const selectedPost = options.postTypes?.find((row) => String(row.value) === String(form.data.tipe_post_id));
    const bankOptions = (options.banks ?? []).filter((row) => String(row.perumahan_id) === String(form.data.perumahan_id));
    const submit = (event) => {
        event.preventDefault();
        form.post('/admin/keuangan/transaksi-kas-bank', {
            preserveScroll: true,
            onSuccess: () => form.reset('master_bank_id', 'tipe_post_id', 'nominal', 'nomor_referensi', 'keterangan'),
        });
    };

    return <div className="grid gap-5 xl:grid-cols-[420px_minmax(0,1fr)]">
        <Card className="p-5">
            {!canCreate && <p className="text-sm font-semibold text-ink-soft">Anda hanya dapat melihat data transaksi ini.</p>}
            {canCreate && (
            <form className="grid gap-4" onSubmit={submit}>
                <Dropdown
                    label="Perumahan"
                    value={form.data.perumahan_id}
                    options={options.perumahans ?? []}
                    onChange={(value) => form.setData({ ...form.data, perumahan_id: value, master_bank_id: '' })}
                />
                <Dropdown label="Rekening Kas / Bank" value={form.data.master_bank_id} options={bankOptions} onChange={(value) => form.setData('master_bank_id', value)} />
                <Dropdown label="Tipe Post" value={form.data.tipe_post_id} options={options.postTypes ?? []} onChange={(value) => form.setData('tipe_post_id', value)} />
                {selectedPost && <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/60 p-3 text-sm dark:border-white/10 dark:bg-white/5">
                    <p><b>Jenis:</b> {selectedPost.jenis}</p>
                    <p className="mt-1"><b>Debit:</b> {selectedPost.debit}</p>
                    <p><b>Kredit:</b> {selectedPost.credit}</p>
                </div>}
                <Input label="Tanggal" type="date" value={form.data.tanggal} onChange={(event) => form.setData('tanggal', event.target.value)} />
                <CurrencyInput label="Nominal" value={form.data.nominal} onChange={(value) => form.setData('nominal', value)} />
                <Input label="Nomor Referensi" placeholder="Opsional: nomor bukti, memo, atau kontrak" value={form.data.nomor_referensi} onChange={(event) => form.setData('nomor_referensi', event.target.value)} />
                <Textarea label="Keterangan" value={form.data.keterangan} onChange={(event) => form.setData('keterangan', event.target.value)} />
                {Object.keys(form.errors).length > 0 && <div className="rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-700">{Object.values(form.errors)[0]}</div>}
                <Button disabled={form.processing}>Posting Transaksi</Button>
            </form>
            )}
        </Card>
        <Table detailTitle="Detail Transaksi Kas/Bank" rows={data.rows ?? []} columns={[
            { key: 'date', label: 'Tanggal' },
            { key: 'reference', label: 'Referensi' },
            { key: 'perumahan', label: 'Perumahan' },
            { key: 'bank', label: 'Kas / Bank' },
            { key: 'post', label: 'Tipe Post' },
            { key: 'type', label: 'Jenis' },
            { key: 'amount', label: 'Nominal', render: (row) => money(row.amount) },
            { key: 'description', label: 'Keterangan' },
            { key: 'input_by', label: 'Input Oleh' },
        ]} />
    </div>;
}

const journalColumns = [
    { key: 'number', label: 'Nomor Jurnal' },
    { key: 'date', label: 'Tanggal' },
    { key: 'perumahan', label: 'Perumahan' },
    { key: 'type', label: 'Tipe' },
    { key: 'description', label: 'Keterangan' },
    { key: 'debit', label: 'Debit', render: (row) => money(row.debit) },
    { key: 'credit', label: 'Kredit', render: (row) => money(row.credit) },
];

function AccountList({ data, canWrite }) {
    const [editing, setEditing] = useState(null);
    const [open, setOpen] = useState(false);
    const initial = { kode_akun: '', nama_akun: '', kategori: 'aset', posisi_normal: 'debit', status: 'aktif' };
    const form = useForm(initial);
    const show = (row = null) => {
        setEditing(row);
        form.setData(row ? {
            kode_akun: row.kode_akun,
            nama_akun: row.nama_akun,
            kategori: row.kategori,
            posisi_normal: row.posisi_normal,
            status: row.status,
        } : initial);
        form.clearErrors();
        setOpen(true);
    };
    const submit = (event) => {
        event.preventDefault();
        const action = editing
            ? form.put(`/admin/keuangan/daftar-akun/${editing.id}`, { preserveScroll: true, onSuccess: () => setOpen(false) })
            : form.post('/admin/keuangan/daftar-akun', { preserveScroll: true, onSuccess: () => setOpen(false) });
        return action;
    };

    return <>
        {canWrite && <div className="flex justify-end"><Button type="button" onClick={() => show()}><Plus size={16} /> Tambah Akun</Button></div>}
        <Table detailTitle="Detail Akun" rows={data.rows ?? []} columns={[
            { key: 'kode_akun', label: 'Kode' },
            { key: 'nama_akun', label: 'Nama Akun' },
            { key: 'kategori', label: 'Kategori' },
            { key: 'posisi_normal', label: 'Saldo Normal' },
            { key: 'status', label: 'Status' },
            { key: 'is_system', label: 'Akun Sistem', render: (row) => row.is_system ? 'Ya' : (canWrite ? <Button size="sm" variant="outline" type="button" onClick={() => show(row)}>Edit</Button> : '-') },
        ]} />
        {canWrite && <Modal open={open} onClose={() => setOpen(false)} title={editing ? 'Edit Akun' : 'Tambah Akun'} size="md">
            <form className="grid gap-4" onSubmit={submit}>
                <Input label="Kode Akun" value={form.data.kode_akun} error={form.errors.kode_akun} onChange={(event) => form.setData('kode_akun', event.target.value)} />
                <Input label="Nama Akun" value={form.data.nama_akun} error={form.errors.nama_akun} onChange={(event) => form.setData('nama_akun', event.target.value)} />
                <Dropdown label="Kategori" value={form.data.kategori} options={data.categories ?? []} onChange={(value) => form.setData('kategori', value)} />
                <Dropdown label="Saldo Normal" value={form.data.posisi_normal} options={[{ value: 'debit', label: 'Debit' }, { value: 'kredit', label: 'Kredit' }]} onChange={(value) => form.setData('posisi_normal', value)} />
                <Dropdown label="Status" value={form.data.status} options={[{ value: 'aktif', label: 'Aktif' }, { value: 'nonaktif', label: 'Nonaktif' }]} onChange={(value) => form.setData('status', value)} />
                <div className="flex justify-end"><Button disabled={form.processing}>Simpan</Button></div>
            </form>
        </Modal>}
    </>;
}

function JournalList({ data, options, canCreate }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        tanggal: new Date().toISOString().slice(0, 10),
        perumahan_id: options.perumahans?.find((row) => row.value)?.value ?? '',
        keterangan: '',
        lines: [
            { chart_of_account_id: '', debit: '', kredit: '', keterangan: '' },
            { chart_of_account_id: '', debit: '', kredit: '', keterangan: '' },
        ],
    });
    const totals = useMemo(() => ({
        debit: form.data.lines.reduce((sum, row) => sum + Number(row.debit || 0), 0),
        credit: form.data.lines.reduce((sum, row) => sum + Number(row.kredit || 0), 0),
    }), [form.data.lines]);
    const updateLine = (index, key, value) => form.setData('lines', form.data.lines.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row));
    const addLine = () => form.setData('lines', [...form.data.lines, { chart_of_account_id: '', debit: '', kredit: '', keterangan: '' }]);
    const submit = (event) => {
        event.preventDefault();
        form.post('/admin/keuangan/jurnal-umum', { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return <>
        {canCreate && <div className="flex justify-end"><Button type="button" onClick={() => setOpen(true)}><Plus size={16} /> Jurnal Manual</Button></div>}
        <Table detailTitle="Detail Jurnal" rows={data.rows ?? []} columns={journalColumns} />
        {canCreate && <Modal open={open} onClose={() => setOpen(false)} title="Jurnal Umum Manual" size="xl">
            <form className="grid gap-4" onSubmit={submit}>
                <div className="grid gap-4 md:grid-cols-2">
                    <Input label="Tanggal" type="date" value={form.data.tanggal} onChange={(event) => form.setData('tanggal', event.target.value)} />
                    <Dropdown label="Perumahan" value={form.data.perumahan_id} options={options.perumahans ?? []} onChange={(value) => form.setData('perumahan_id', value)} />
                </div>
                <Textarea label="Keterangan" value={form.data.keterangan} onChange={(event) => form.setData('keterangan', event.target.value)} />
                <div className="grid gap-3">
                    {form.data.lines.map((line, index) => <div className="grid gap-3 rounded-lg border border-silver-deep/60 p-3 md:grid-cols-[minmax(260px,1fr)_180px_180px]" key={index}>
                        <Dropdown label={`Akun ${index + 1}`} value={line.chart_of_account_id} options={options.accounts ?? []} onChange={(value) => updateLine(index, 'chart_of_account_id', value)} />
                        <CurrencyInput label="Debit" value={line.debit} onChange={(value) => updateLine(index, 'debit', value)} />
                        <CurrencyInput label="Kredit" value={line.kredit} onChange={(value) => updateLine(index, 'kredit', value)} />
                    </div>)}
                    <Button type="button" variant="outline" onClick={addLine}><Plus size={15} /> Tambah Baris</Button>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <Card className="p-4"><p className="text-xs font-bold text-ink-soft">Total Debit</p><strong>{money(totals.debit)}</strong></Card>
                    <Card className="p-4"><p className="text-xs font-bold text-ink-soft">Total Kredit</p><strong>{money(totals.credit)}</strong></Card>
                </div>
                <div className="flex justify-end"><Button disabled={form.processing || totals.debit <= 0 || totals.debit !== totals.credit}>Posting Jurnal</Button></div>
            </form>
        </Modal>}
    </>;
}

function Ledger({ data }) {
    return <div className="grid gap-4">
        <StatGrid rows={[
            ['Saldo Awal', money(data.opening_balance)],
            ['Saldo Akhir', money(data.ending_balance), Number(data.ending_balance) < 0 ? 'text-red-600' : 'text-emerald-600'],
        ]} />
        <Card className="p-5"><p className="text-xs font-bold uppercase text-ink-soft">Akun</p><h2 className="mt-1 text-xl font-extrabold">{data.account ? `${data.account.kode_akun} - ${data.account.nama_akun}` : '-'}</h2></Card>
        <Table rows={data.rows ?? []} columns={[
            { key: 'date', label: 'Tanggal' },
            { key: 'journal', label: 'Jurnal' },
            { key: 'perumahan', label: 'Perumahan' },
            { key: 'description', label: 'Keterangan' },
            { key: 'debit', label: 'Debit', render: (row) => money(row.debit) },
            { key: 'credit', label: 'Kredit', render: (row) => money(row.credit) },
            { key: 'balance', label: 'Saldo', render: (row) => money(row.balance) },
        ]} />
    </div>;
}

function TrialBalance({ data }) {
    return <div className="grid gap-4">
        <StatGrid rows={[
            ['Total Debit', money(data.total_debit)],
            ['Total Kredit', money(data.total_credit)],
            ['Status', data.balanced ? 'Balance' : 'Tidak Balance', data.balanced ? 'text-emerald-600' : 'text-red-600'],
        ]} />
        <Table rows={data.rows ?? []} columns={[
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Akun' },
            { key: 'opening', label: 'Saldo Awal', render: (row) => money(row.opening) },
            { key: 'debit', label: 'Mutasi Debit', render: (row) => money(row.debit) },
            { key: 'credit', label: 'Mutasi Kredit', render: (row) => money(row.credit) },
            { key: 'ending_debit', label: 'Saldo Debit', render: (row) => money(row.ending_debit) },
            { key: 'ending_credit', label: 'Saldo Kredit', render: (row) => money(row.ending_credit) },
        ]} />
    </div>;
}

function ProfitLoss({ data }) {
    return <div className="grid gap-4">
        <StatGrid rows={[
            ['Pendapatan', money(data.revenue), 'text-emerald-600'],
            ['HPP', money(data.cost_of_sales)],
            ['Laba Kotor', money(data.gross_profit)],
            ['Beban Operasional', money(data.operating_expense)],
            ['Laba Bersih', money(data.net_profit), Number(data.net_profit) < 0 ? 'text-red-600' : 'text-emerald-600'],
        ]} />
        <Table rows={data.rows ?? []} columns={[
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Akun' },
            { key: 'category', label: 'Kategori' },
            { key: 'amount', label: 'Nilai', render: (row) => money(row.amount) },
        ]} />
    </div>;
}

function BalanceSheet({ data }) {
    return <div className="grid gap-4">
        <StatGrid rows={[
            ['Total Aset', money(data.assets)],
            ['Total Liabilitas', money(data.liabilities)],
            ['Total Ekuitas', money(data.equity)],
            ['Liabilitas + Ekuitas', money(data.liabilities_equity)],
            ['Status', data.balanced ? 'Balance' : 'Belum Balance', data.balanced ? 'text-emerald-600' : 'text-red-600'],
        ]} />
        <Table rows={data.rows ?? []} columns={[
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Akun' },
            { key: 'category', label: 'Kategori' },
            { key: 'amount', label: 'Saldo', render: (row) => money(row.amount) },
        ]} />
    </div>;
}

function CashFlow({ data }) {
    return <div className="grid gap-4">
        <StatGrid rows={[
            ['Saldo Awal', money(data.opening_balance)],
            ['Kas Masuk', money(data.cash_in), 'text-emerald-600'],
            ['Kas Keluar', money(data.cash_out), 'text-red-600'],
            ['Arus Kas Bersih', money(data.net_cash_flow)],
            ['Saldo Akhir', money(data.ending_balance)],
        ]} />
        <Table rows={data.rows ?? []} columns={[
            { key: 'date', label: 'Tanggal' },
            { key: 'type', label: 'Jenis' },
            { key: 'post', label: 'Pos' },
            { key: 'bank', label: 'Kas / Bank' },
            { key: 'description', label: 'Keterangan' },
            { key: 'amount', label: 'Nominal', render: (row) => money(row.amount) },
        ]} />
    </div>;
}

function Receivable({ data }) {
    return <div className="grid gap-4">
        <StatGrid rows={[
            ['Total Tagihan', money(data.summary?.bill)],
            ['Sudah Dibayar', money(data.summary?.paid)],
            ['Sisa Piutang', money(data.summary?.remaining)],
            ['Lewat Jatuh Tempo', money(data.summary?.overdue), 'text-red-600'],
        ]} />
        <Table rows={data.rows ?? []} columns={[
            { key: 'reference', label: 'SPR' },
            { key: 'customer', label: 'Customer' },
            { key: 'perumahan', label: 'Perumahan' },
            { key: 'type', label: 'Tagihan' },
            { key: 'due_date', label: 'Jatuh Tempo' },
            { key: 'bill', label: 'Tagihan', render: (row) => money(row.bill) },
            { key: 'paid', label: 'Dibayar', render: (row) => money(row.paid) },
            { key: 'remaining', label: 'Sisa', render: (row) => money(row.remaining) },
            { key: 'status', label: 'Status' },
        ]} />
    </div>;
}

function Payable({ data }) {
    return <div className="grid gap-4">
        <StatGrid rows={[
            ['Total Tagihan', money(data.summary?.bill)],
            ['Sudah Dibayar', money(data.summary?.paid)],
            ['Sisa Hutang', money(data.summary?.remaining), 'text-red-600'],
        ]} />
        <Table rows={data.rows ?? []} columns={[
            { key: 'source', label: 'Jenis' },
            { key: 'reference', label: 'Referensi' },
            { key: 'vendor', label: 'Vendor' },
            { key: 'perumahan', label: 'Perumahan' },
            { key: 'due_date', label: 'Jatuh Tempo' },
            { key: 'bill', label: 'Tagihan', render: (row) => money(row.bill) },
            { key: 'paid', label: 'Dibayar', render: (row) => money(row.paid) },
            { key: 'remaining', label: 'Sisa', render: (row) => money(row.remaining) },
            { key: 'status', label: 'Status' },
        ]} />
    </div>;
}

const icons = {
    dashboard: WalletCards,
    'transaksi-kas-bank': Banknote,
    'daftar-akun': ListTree,
    'jurnal-umum': BookOpen,
    'buku-besar': Library,
    'neraca-saldo': Scale,
    'laba-rugi': BarChart3,
    neraca: Landmark,
    'arus-kas': Activity,
    piutang: TrendingUp,
    hutang: TrendingDown,
};

export default function Index({ title, section, baseUrl, filters, options, data, permissions = {} }) {
    const Icon = icons[section] ?? WalletCards;
    const canCreate = permissions.canCreate ?? false;
    const canUpdate = permissions.canUpdate ?? false;
    const content = {
        dashboard: <Dashboard data={data} />,
        'transaksi-kas-bank': <ManualTransaction data={data} options={options} canCreate={canCreate} />,
        'daftar-akun': <AccountList data={data} canWrite={canCreate || canUpdate} />,
        'jurnal-umum': <JournalList data={data} options={options} canCreate={canCreate} />,
        'buku-besar': <Ledger data={data} />,
        'neraca-saldo': <TrialBalance data={data} />,
        'laba-rugi': <ProfitLoss data={data} />,
        neraca: <BalanceSheet data={data} />,
        'arus-kas': <CashFlow data={data} />,
        piutang: <Receivable data={data} />,
        hutang: <Payable data={data} />,
    }[section];
    const filterable = !['daftar-akun'].includes(section);

    return <>
        <Head title={title} />
        <div className="grid gap-5">
            <Card className="p-6">
                <div className="flex items-center gap-3">
                    <span className="grid h-11 w-11 place-items-center rounded-lg bg-ink text-white dark:bg-white dark:text-ink"><Icon size={20} /></span>
                    <div><p className="text-xs font-extrabold uppercase text-ink-soft">ERP Keuangan</p><h1 className="text-2xl font-extrabold">{title}</h1></div>
                </div>
            </Card>
            {filterable && <FilterBar baseUrl={baseUrl} filters={filters} options={options} showAccount={section === 'buku-besar'} />}
            {content}
        </div>
    </>;
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Keuangan'}>{page}</AdminLayout>;
