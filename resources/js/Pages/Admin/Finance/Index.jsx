import { Head, router, useForm } from "@inertiajs/react";
import {
    Activity,
    Banknote,
    BarChart3,
    BookOpen,
    CheckCircle2,
    Eye,
    Landmark,
    Library,
    ListTree,
    Plus,
    ReceiptText,
    Scale,
    TrendingDown,
    TrendingUp,
    WalletCards,
} from "lucide-react";
import { useMemo, useState } from "react";
import {
    Button,
    CurrencyInput,
    Dropdown,
    FieldLabel,
    Input,
    Modal,
    Textarea,
    TableActions,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import {
    FinanceChart,
    FinanceTrendChart,
} from "../../../Components/Finance/FinanceChart";

const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

function Card({ children, className = "" }) {
    return (
        <section
            className={`rounded-lg border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8 ${className}`}
        >
            {children}
        </section>
    );
}

function FilterBar({ baseUrl, filters, options, showAccount = false }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? "");
    const [dateTo, setDateTo] = useState(filters.date_to ?? "");
    const [perumahanId, setPerumahanId] = useState(filters.perumahan_id ?? "");
    const [accountId, setAccountId] = useState(filters.account_id ?? "");

    const submit = (event) => {
        event.preventDefault();
        router.get(
            baseUrl,
            {
                date_from: dateFrom,
                date_to: dateTo,
                perumahan_id: perumahanId,
                account_id: accountId,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };
    const quickPeriod = (period) => {
        const today = new Date();
        const iso = (date) =>
            `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
        let start = new Date(today);
        if (period === "month")
            start = new Date(today.getFullYear(), today.getMonth(), 1);
        if (period === "year") start = new Date(today.getFullYear(), 0, 1);
        const end =
            period === "month"
                ? new Date(today.getFullYear(), today.getMonth() + 1, 0)
                : period === "year"
                  ? new Date(today.getFullYear(), 11, 31)
                  : today;
        const nextFrom = iso(start);
        const nextTo = iso(end);
        setDateFrom(nextFrom);
        setDateTo(nextTo);
        router.get(
            baseUrl,
            {
                date_from: nextFrom,
                date_to: nextTo,
                perumahan_id: perumahanId,
                account_id: accountId,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <Card className="p-4">
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <span className="mr-1 text-xs font-black uppercase text-ink-soft">
                    Periode cepat
                </span>
                <Button
                    type="button"
                    variant="secondary"
                    onClick={() => quickPeriod("day")}
                >
                    Hari ini
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    onClick={() => quickPeriod("month")}
                >
                    Bulan ini
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    onClick={() => quickPeriod("year")}
                >
                    Tahun ini
                </Button>
                <span className="text-xs text-ink-soft">
                    atau tentukan rentang tanggal sendiri di bawah.
                </span>
            </div>
            <form
                className={`grid gap-3 ${showAccount ? "xl:grid-cols-[210px_210px_240px_minmax(260px,1fr)_auto]" : "lg:grid-cols-[210px_210px_minmax(260px,1fr)_auto]"} items-end`}
                onSubmit={submit}
            >
                <Input
                    label="Dari Tanggal"
                    type="date"
                    value={dateFrom}
                    onChange={(event) => setDateFrom(event.target.value)}
                />
                <Input
                    label="Sampai Tanggal"
                    type="date"
                    value={dateTo}
                    onChange={(event) => setDateTo(event.target.value)}
                />
                <Dropdown
                    label="Perumahan"
                    value={perumahanId}
                    options={options.perumahans ?? []}
                    onChange={setPerumahanId}
                />
                {showAccount && (
                    <Dropdown
                        label="Akun"
                        value={accountId}
                        options={options.accounts ?? []}
                        onChange={setAccountId}
                    />
                )}
                <Button type="submit">Terapkan</Button>
            </form>
        </Card>
    );
}

function StatGrid({ rows }) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            {rows.map(([label, value, tone]) => (
                <Card className="p-4" key={label}>
                    <p className="text-xs font-extrabold uppercase text-ink-soft">
                        {label}
                    </p>
                    <strong
                        className={`mt-2 block break-words text-xl ${tone ?? ""}`}
                    >
                        {value}
                    </strong>
                </Card>
            ))}
        </div>
    );
}

function Table({ columns, rows = [], detailTitle = "Detail" }) {
    const [detail, setDetail] = useState(null);
    return (
        <>
            <Card className="overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-silver-soft/80 text-left text-xs uppercase text-ink-soft dark:bg-white/5">
                            <tr>
                                {columns.map((column) => (
                                    <th className="px-4 py-3" key={column.key}>
                                        {column.label}
                                    </th>
                                ))}
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {rows.map((row, index) => (
                                <tr key={row.id ?? index}>
                                    {columns.map((column) => (
                                        <td
                                            className="px-4 py-3"
                                            key={column.key}
                                        >
                                            {column.render
                                                ? column.render(row)
                                                : row[column.key]}
                                        </td>
                                    ))}
                                    <td className="px-4 py-3 text-right">
                                        <TableActions>
                                            <Button
                                                size="sm"
                                                type="button"
                                                variant="outline"
                                                onClick={() => setDetail(row)}
                                            >
                                                <Eye size={14} /> Detail
                                            </Button>
                                        </TableActions>
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        className="px-5 py-10 text-center font-bold text-ink-soft"
                                        colSpan={columns.length + 1}
                                    >
                                        Belum ada data.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </Card>
            <Modal
                open={Boolean(detail)}
                onClose={() => setDetail(null)}
                title={detailTitle}
                size="lg"
            >
                {detail && (
                    <div className="grid gap-3 sm:grid-cols-2">
                        {columns.map((column) => (
                            <div
                                className="rounded-lg bg-silver-soft/70 p-3 dark:bg-white/5"
                                key={column.key}
                            >
                                <p className="text-xs font-bold uppercase text-ink-soft">
                                    {column.label}
                                </p>
                                <div className="mt-1 font-semibold">
                                    {column.render
                                        ? column.render(detail)
                                        : String(detail[column.key] ?? "-")}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Modal>
        </>
    );
}

function Dashboard({ data }) {
    const stats = data.stats ?? {};
    return (
        <div className="grid gap-4">
            <StatGrid
                rows={[
                    [
                        "Saldo Kas/Bank",
                        money(stats.cash_balance),
                        "text-emerald-600",
                    ],
                    ["Pemasukan", money(stats.cash_in)],
                    ["Pengeluaran", money(stats.cash_out), "text-red-600"],
                    ["Piutang", money(stats.receivable)],
                    ["Hutang", money(stats.payable)],
                    [
                        "Laba Bersih",
                        money(stats.profit),
                        Number(stats.profit) < 0
                            ? "text-red-600"
                            : "text-emerald-600",
                    ],
                ]}
            />
            <FinanceTrendChart
                title="Tren Saldo Kas & Bank"
                subtitle="Garis biru menunjukkan saldo kumulatif; pemasukan dan pengeluaran tetap terlihat sebagai pembanding."
                items={data.monthly ?? []}
                series={[
                    {
                        key: "balance",
                        label: "Saldo kumulatif",
                        color: "#2563eb",
                        area: true,
                    },
                    { key: "in", label: "Pemasukan", color: "#10b981" },
                    { key: "out", label: "Pengeluaran", color: "#ef4444" },
                ]}
            />
            <Table
                detailTitle="Detail Jurnal"
                rows={data.recent_journals ?? []}
                columns={journalColumns}
            />
        </div>
    );
}

function SelectField({
    label,
    required = false,
    value,
    options,
    onChange,
    error,
    placeholder,
}) {
    return (
        <div className="grid gap-2">
            <FieldLabel required={required}>{label}</FieldLabel>
            <Dropdown
                value={value}
                options={options}
                label={placeholder ?? `Pilih ${label.toLowerCase()}`}
                onChange={onChange}
            />
            {error && (
                <span className="text-xs font-bold text-red-600 dark:text-red-300">
                    {error}
                </span>
            )}
        </div>
    );
}

function CashTransaction({ data, options, canCreate, type }) {
    const income = type === "pemasukan";
    const initialBranch =
        options.branches?.find((row) => row.value)?.value ?? "";
    const form = useForm({
        cabang_id: initialBranch,
        master_bank_id: "",
        tipe_post_id: "",
        tanggal: new Date().toISOString().slice(0, 10),
        nominal: "",
        nomor_referensi: "",
        keterangan: "",
    });
    const selectedPost = options.postTypes?.find(
        (row) => String(row.value) === String(form.data.tipe_post_id),
    );
    const bankOptions = (options.banks ?? []).filter(
        (row) => String(row.cabang_id) === String(form.data.cabang_id),
    );
    const submit = (event) => {
        event.preventDefault();
        form.post(`/admin/keuangan/${type}`, {
            preserveScroll: true,
            onSuccess: () =>
                form.reset(
                    "master_bank_id",
                    "tipe_post_id",
                    "nominal",
                    "nomor_referensi",
                    "keterangan",
                ),
        });
    };

    return (
        <div className="grid gap-5">
            <Card className="overflow-hidden">
                <header
                    className={`relative overflow-hidden px-5 py-5 text-white ${income ? "bg-gradient-to-r from-emerald-700 via-emerald-600 to-teal-600" : "bg-gradient-to-r from-red-700 via-rose-600 to-orange-600"}`}
                >
                    <div className="absolute -right-10 -top-16 h-40 w-40 rounded-full bg-white/10" />
                    <div className="relative flex items-center gap-4">
                        <span className="grid h-11 w-11 place-items-center rounded-xl bg-white/15">
                            {income ? (
                                <TrendingUp size={22} />
                            ) : (
                                <TrendingDown size={22} />
                            )}
                        </span>
                        <div>
                            <p className="text-xs font-black uppercase tracking-[0.16em] text-white/70">
                                Form Transaksi
                            </p>
                            <h2 className="mt-1 text-xl font-black">
                                Catat {income ? "Pemasukan" : "Pengeluaran"} Kas
                                & Bank
                            </h2>
                            <p className="mt-1 text-sm text-white/75">
                                {income
                                    ? "Hanya untuk pemasukan non-customer yang belum memiliki modul transaksi khusus."
                                    : "Transaksi akan langsung membentuk jurnal berdasarkan tipe transaksi yang dipilih."}
                            </p>
                        </div>
                    </div>
                </header>

                {!canCreate && (
                    <p className="m-5 rounded-xl bg-silver-soft p-4 text-sm font-semibold text-ink-soft dark:bg-white/5">
                        Anda hanya dapat melihat data transaksi ini.
                    </p>
                )}
                {canCreate && (
                    <form onSubmit={submit}>
                        <div className="grid gap-5 p-5 md:p-6">
                            <section className="rounded-xl border border-silver-deep/60 p-4 dark:border-white/10">
                                <div className="mb-4 flex items-center gap-3 border-b border-silver-deep/50 pb-3 dark:border-white/10">
                                    <span className="grid h-7 w-7 place-items-center rounded-lg bg-ink text-xs font-black text-white dark:bg-white dark:text-ink">
                                        1
                                    </span>
                                    <div>
                                        <h3 className="text-sm font-black">
                                            Sumber dan Klasifikasi
                                        </h3>
                                        <p className="text-xs text-ink-soft">
                                            Tentukan perusahaan, rekening, dan
                                            jenis transaksi.
                                        </p>
                                    </div>
                                </div>
                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    <SelectField
                                        label="Perusahaan / Cabang"
                                        required
                                        value={form.data.cabang_id}
                                        options={options.branches ?? []}
                                        error={form.errors.cabang_id}
                                        onChange={(value) =>
                                            form.setData({
                                                ...form.data,
                                                cabang_id: value,
                                                master_bank_id: "",
                                            })
                                        }
                                    />
                                    <SelectField
                                        label="Rekening Kas / Bank"
                                        required
                                        value={form.data.master_bank_id}
                                        options={bankOptions}
                                        error={form.errors.master_bank_id}
                                        onChange={(value) =>
                                            form.setData(
                                                "master_bank_id",
                                                value,
                                            )
                                        }
                                    />
                                    <SelectField
                                        label={
                                            income
                                                ? "Jenis Pemasukan"
                                                : "Jenis Pengeluaran"
                                        }
                                        required
                                        value={form.data.tipe_post_id}
                                        options={options.postTypes ?? []}
                                        error={form.errors.tipe_post_id}
                                        onChange={(value) =>
                                            form.setData("tipe_post_id", value)
                                        }
                                    />
                                </div>
                                {selectedPost && (
                                    <div
                                        className={`mt-4 grid gap-3 rounded-xl border p-4 text-xs font-bold md:grid-cols-2 ${income ? "border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200" : "border-red-200 bg-red-50 text-red-900 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200"}`}
                                    >
                                        <div>
                                            <span className="block text-[10px] uppercase opacity-60">
                                                Akun Debit
                                            </span>
                                            <span className="mt-1 block">
                                                {selectedPost.debit}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="block text-[10px] uppercase opacity-60">
                                                Akun Kredit
                                            </span>
                                            <span className="mt-1 block">
                                                {selectedPost.credit}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </section>

                            <section className="rounded-xl border border-silver-deep/60 p-4 dark:border-white/10">
                                <div className="mb-4 flex items-center gap-3 border-b border-silver-deep/50 pb-3 dark:border-white/10">
                                    <span className="grid h-7 w-7 place-items-center rounded-lg bg-ink text-xs font-black text-white dark:bg-white dark:text-ink">
                                        2
                                    </span>
                                    <div>
                                        <h3 className="text-sm font-black">
                                            Nilai dan Bukti Transaksi
                                        </h3>
                                        <p className="text-xs text-ink-soft">
                                            Lengkapi tanggal, nominal,
                                            referensi, dan penjelasan.
                                        </p>
                                    </div>
                                </div>
                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    <Input
                                        label="Tanggal Transaksi"
                                        required
                                        type="date"
                                        value={form.data.tanggal}
                                        error={form.errors.tanggal}
                                        onChange={(event) =>
                                            form.setData(
                                                "tanggal",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <CurrencyInput
                                        label={`Nominal ${income ? "Pemasukan" : "Pengeluaran"}`}
                                        required
                                        value={form.data.nominal}
                                        error={form.errors.nominal}
                                        onChange={(value) =>
                                            form.setData("nominal", value)
                                        }
                                    />
                                    <Input
                                        label="Nomor Referensi"
                                        placeholder="Nomor bukti, memo, atau kontrak"
                                        value={form.data.nomor_referensi}
                                        error={form.errors.nomor_referensi}
                                        onChange={(event) =>
                                            form.setData(
                                                "nomor_referensi",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Textarea
                                        className="md:col-span-2 xl:col-span-3"
                                        label="Keterangan Transaksi"
                                        required
                                        value={form.data.keterangan}
                                        error={form.errors.keterangan}
                                        onChange={(event) =>
                                            form.setData(
                                                "keterangan",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </section>
                        </div>

                        <footer className="flex flex-col gap-3 border-t border-silver-deep/60 bg-silver-soft/45 px-5 py-4 dark:border-white/10 dark:bg-white/[0.025] sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2 text-xs font-bold text-ink-soft">
                                <CheckCircle2
                                    size={16}
                                    className={
                                        income
                                            ? "text-emerald-600"
                                            : "text-red-600"
                                    }
                                />{" "}
                                Pastikan rekening dan nominal sudah sesuai bukti
                                transaksi.
                            </div>
                            <Button
                                className={
                                    income
                                        ? "bg-emerald-700 text-white hover:bg-emerald-800"
                                        : "bg-red-700 text-white hover:bg-red-800"
                                }
                                disabled={form.processing}
                            >
                                <ReceiptText size={17} />{" "}
                                {form.processing
                                    ? "Memposting..."
                                    : `Posting ${income ? "Pemasukan" : "Pengeluaran"}`}
                            </Button>
                        </footer>
                    </form>
                )}
            </Card>

            <Table
                detailTitle="Detail Transaksi Kas/Bank"
                rows={data.rows ?? []}
                columns={[
                    { key: "date", label: "Tanggal" },
                    { key: "reference", label: "Referensi" },
                    { key: "company", label: "Perusahaan / Cabang" },
                    { key: "bank", label: "Kas / Bank" },
                    { key: "post", label: "Tipe Post" },
                    {
                        key: "amount",
                        label: "Nominal",
                        render: (row) => money(row.amount),
                    },
                    { key: "description", label: "Keterangan" },
                    { key: "input_by", label: "Input Oleh" },
                ]}
            />
        </div>
    );
}

const journalColumns = [
    { key: "number", label: "Nomor Jurnal" },
    { key: "date", label: "Tanggal" },
    { key: "perumahan", label: "Perumahan" },
    { key: "type", label: "Tipe" },
    { key: "description", label: "Keterangan" },
    { key: "debit", label: "Debit", render: (row) => money(row.debit) },
    { key: "credit", label: "Kredit", render: (row) => money(row.credit) },
];

function AccountList({ data, canWrite }) {
    const [editing, setEditing] = useState(null);
    const [open, setOpen] = useState(false);
    const initial = {
        kode_akun: "",
        nama_akun: "",
        kategori: "aset",
        posisi_normal: "debit",
        status: "aktif",
    };
    const form = useForm(initial);
    const show = (row = null) => {
        setEditing(row);
        form.setData(
            row
                ? {
                      kode_akun: row.kode_akun,
                      nama_akun: row.nama_akun,
                      kategori: row.kategori,
                      posisi_normal: row.posisi_normal,
                      status: row.status,
                  }
                : initial,
        );
        form.clearErrors();
        setOpen(true);
    };
    const submit = (event) => {
        event.preventDefault();
        const action = editing
            ? form.put(`/admin/keuangan/daftar-akun/${editing.id}`, {
                  preserveScroll: true,
                  onSuccess: () => setOpen(false),
              })
            : form.post("/admin/keuangan/daftar-akun", {
                  preserveScroll: true,
                  onSuccess: () => setOpen(false),
              });
        return action;
    };

    return (
        <>
            {canWrite && (
                <div className="flex justify-end">
                    <Button type="button" onClick={() => show()}>
                        <Plus size={16} /> Tambah Akun
                    </Button>
                </div>
            )}
            <Table
                detailTitle="Detail Akun"
                rows={data.rows ?? []}
                columns={[
                    { key: "kode_akun", label: "Kode" },
                    { key: "nama_akun", label: "Nama Akun" },
                    { key: "kategori", label: "Kategori" },
                    { key: "posisi_normal", label: "Saldo Normal" },
                    { key: "status", label: "Status" },
                    {
                        key: "is_system",
                        label: "Akun Sistem",
                        render: (row) =>
                            row.is_system ? (
                                "Ya"
                            ) : canWrite ? (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    type="button"
                                    onClick={() => show(row)}
                                >
                                    Edit
                                </Button>
                            ) : (
                                "-"
                            ),
                    },
                ]}
            />
            {canWrite && (
                <Modal
                    open={open}
                    onClose={() => setOpen(false)}
                    title={editing ? "Ubah Akun" : "Tambah Akun"}
                    size="md"
                >
                    <form className="grid gap-4" onSubmit={submit}>
                        <Input
                            label="Kode Akun"
                            value={form.data.kode_akun}
                            error={form.errors.kode_akun}
                            onChange={(event) =>
                                form.setData("kode_akun", event.target.value)
                            }
                        />
                        <Input
                            label="Nama Akun"
                            value={form.data.nama_akun}
                            error={form.errors.nama_akun}
                            onChange={(event) =>
                                form.setData("nama_akun", event.target.value)
                            }
                        />
                        <Dropdown
                            label="Kategori"
                            value={form.data.kategori}
                            options={data.categories ?? []}
                            onChange={(value) =>
                                form.setData("kategori", value)
                            }
                        />
                        <Dropdown
                            label="Saldo Normal"
                            value={form.data.posisi_normal}
                            options={[
                                { value: "debit", label: "Debit" },
                                { value: "kredit", label: "Kredit" },
                            ]}
                            onChange={(value) =>
                                form.setData("posisi_normal", value)
                            }
                        />
                        <Dropdown
                            label="Status"
                            value={form.data.status}
                            options={[
                                { value: "aktif", label: "Aktif" },
                                { value: "nonaktif", label: "Nonaktif" },
                            ]}
                            onChange={(value) => form.setData("status", value)}
                        />
                        <div className="flex justify-end">
                            <Button disabled={form.processing}>Simpan</Button>
                        </div>
                    </form>
                </Modal>
            )}
        </>
    );
}

function JournalList({ data, options, canCreate }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        tanggal: new Date().toISOString().slice(0, 10),
        perumahan_id: options.perumahans?.find((row) => row.value)?.value ?? "",
        keterangan: "",
        lines: [
            { chart_of_account_id: "", debit: "", kredit: "", keterangan: "" },
            { chart_of_account_id: "", debit: "", kredit: "", keterangan: "" },
        ],
    });
    const totals = useMemo(
        () => ({
            debit: form.data.lines.reduce(
                (sum, row) => sum + Number(row.debit || 0),
                0,
            ),
            credit: form.data.lines.reduce(
                (sum, row) => sum + Number(row.kredit || 0),
                0,
            ),
        }),
        [form.data.lines],
    );
    const updateLine = (index, key, value) =>
        form.setData(
            "lines",
            form.data.lines.map((row, rowIndex) =>
                rowIndex === index ? { ...row, [key]: value } : row,
            ),
        );
    const addLine = () =>
        form.setData("lines", [
            ...form.data.lines,
            { chart_of_account_id: "", debit: "", kredit: "", keterangan: "" },
        ]);
    const submit = (event) => {
        event.preventDefault();
        form.post("/admin/keuangan/jurnal-umum", {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <>
            {canCreate && (
                <div className="flex justify-end">
                    <Button type="button" onClick={() => setOpen(true)}>
                        <Plus size={16} /> Jurnal Manual
                    </Button>
                </div>
            )}
            <FinanceTrendChart
                title="Total Debit dan Kredit per Hari"
                subtitle="Data jurnal diringkas per tanggal (maksimal 31 titik), sehingga grafik tidak memanjang saat jurnal bertambah."
                items={data.trend ?? []}
                series={[
                    {
                        key: "debit",
                        label: "Debit",
                        color: "#10b981",
                        area: true,
                    },
                    { key: "credit", label: "Kredit", color: "#f59e0b" },
                ]}
            />
            <Table
                detailTitle="Detail Jurnal"
                rows={data.rows ?? []}
                columns={journalColumns}
            />
            {canCreate && (
                <Modal
                    open={open}
                    onClose={() => setOpen(false)}
                    title="Jurnal Umum Manual"
                    size="xl"
                >
                    <form className="grid gap-4" onSubmit={submit}>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Input
                                label="Tanggal"
                                type="date"
                                value={form.data.tanggal}
                                onChange={(event) =>
                                    form.setData("tanggal", event.target.value)
                                }
                            />
                            <Dropdown
                                label="Perumahan"
                                value={form.data.perumahan_id}
                                options={options.perumahans ?? []}
                                onChange={(value) =>
                                    form.setData("perumahan_id", value)
                                }
                            />
                        </div>
                        <Textarea
                            label="Keterangan"
                            value={form.data.keterangan}
                            onChange={(event) =>
                                form.setData("keterangan", event.target.value)
                            }
                        />
                        <div className="grid gap-3">
                            {form.data.lines.map((line, index) => (
                                <div
                                    className="grid gap-3 rounded-lg border border-silver-deep/60 p-3 md:grid-cols-[minmax(260px,1fr)_180px_180px]"
                                    key={index}
                                >
                                    <Dropdown
                                        label={`Akun ${index + 1}`}
                                        value={line.chart_of_account_id}
                                        options={options.accounts ?? []}
                                        onChange={(value) =>
                                            updateLine(
                                                index,
                                                "chart_of_account_id",
                                                value,
                                            )
                                        }
                                    />
                                    <CurrencyInput
                                        label="Debit"
                                        value={line.debit}
                                        onChange={(value) =>
                                            updateLine(index, "debit", value)
                                        }
                                    />
                                    <CurrencyInput
                                        label="Kredit"
                                        value={line.kredit}
                                        onChange={(value) =>
                                            updateLine(index, "kredit", value)
                                        }
                                    />
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={addLine}
                            >
                                <Plus size={15} /> Tambah Baris
                            </Button>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Card className="p-4">
                                <p className="text-xs font-bold text-ink-soft">
                                    Total Debit
                                </p>
                                <strong>{money(totals.debit)}</strong>
                            </Card>
                            <Card className="p-4">
                                <p className="text-xs font-bold text-ink-soft">
                                    Total Kredit
                                </p>
                                <strong>{money(totals.credit)}</strong>
                            </Card>
                        </div>
                        <div className="flex justify-end">
                            <Button
                                disabled={
                                    form.processing ||
                                    totals.debit <= 0 ||
                                    totals.debit !== totals.credit
                                }
                            >
                                Posting Jurnal
                            </Button>
                        </div>
                    </form>
                </Modal>
            )}
        </>
    );
}

function Ledger({ data }) {
    return (
        <div className="grid gap-4">
            <StatGrid
                rows={[
                    ["Saldo Awal", money(data.opening_balance)],
                    [
                        "Saldo Akhir",
                        money(data.ending_balance),
                        Number(data.ending_balance) < 0
                            ? "text-red-600"
                            : "text-emerald-600",
                    ],
                ]}
            />
            <Card className="p-5">
                <p className="text-xs font-bold uppercase text-ink-soft">
                    Akun
                </p>
                <h2 className="mt-1 text-xl font-extrabold">
                    {data.account
                        ? `${data.account.kode_akun} - ${data.account.nama_akun}`
                        : "-"}
                </h2>
            </Card>
            <FinanceTrendChart
                title="Pergerakan Saldo"
                subtitle="Saldo berjalan dari baris buku besar pada periode yang dipilih."
                items={(data.rows ?? []).map((row) => ({
                    label: row.date,
                    balance: row.balance,
                }))}
                series={[
                    {
                        key: "balance",
                        label: "Saldo berjalan",
                        color: "#2563eb",
                        area: true,
                    },
                ]}
            />
            <Table
                rows={data.rows ?? []}
                columns={[
                    { key: "date", label: "Tanggal" },
                    { key: "journal", label: "Jurnal" },
                    { key: "perumahan", label: "Perumahan" },
                    { key: "description", label: "Keterangan" },
                    {
                        key: "debit",
                        label: "Debit",
                        render: (row) => money(row.debit),
                    },
                    {
                        key: "credit",
                        label: "Kredit",
                        render: (row) => money(row.credit),
                    },
                    {
                        key: "balance",
                        label: "Saldo",
                        render: (row) => money(row.balance),
                    },
                ]}
            />
        </div>
    );
}

function TrialBalance({ data }) {
    return (
        <div className="grid gap-4">
            <StatGrid
                rows={[
                    ["Total Debit", money(data.total_debit)],
                    ["Total Kredit", money(data.total_credit)],
                    [
                        "Status",
                        data.balanced ? "Balance" : "Tidak Balance",
                        data.balanced ? "text-emerald-600" : "text-red-600",
                    ],
                ]}
            />
            <FinanceChart
                title="Mutasi Debit dan Kredit per Akun"
                subtitle="Perbandingan maksimal 12 akun dengan mutasi terbesar; rincian semua akun ada pada tabel."
                primaryLabel="Debit"
                secondaryLabel="Kredit"
                items={(data.rows ?? []).map((row) => ({
                    label: `${row.code} - ${row.name}`,
                    value: row.debit,
                    secondary: row.credit,
                }))}
            />
            <Table
                rows={data.rows ?? []}
                columns={[
                    { key: "code", label: "Kode" },
                    { key: "name", label: "Akun" },
                    {
                        key: "opening",
                        label: "Saldo Awal",
                        render: (row) => money(row.opening),
                    },
                    {
                        key: "debit",
                        label: "Mutasi Debit",
                        render: (row) => money(row.debit),
                    },
                    {
                        key: "credit",
                        label: "Mutasi Kredit",
                        render: (row) => money(row.credit),
                    },
                    {
                        key: "ending_debit",
                        label: "Saldo Debit",
                        render: (row) => money(row.ending_debit),
                    },
                    {
                        key: "ending_credit",
                        label: "Saldo Kredit",
                        render: (row) => money(row.ending_credit),
                    },
                ]}
            />
        </div>
    );
}

function ProfitLoss({ data }) {
    return (
        <div className="grid gap-4">
            <StatGrid
                rows={[
                    ["Pendapatan", money(data.revenue), "text-emerald-600"],
                    ["HPP", money(data.cost_of_sales)],
                    ["Laba Kotor", money(data.gross_profit)],
                    ["Beban Operasional", money(data.operating_expense)],
                    [
                        "Laba Bersih",
                        money(data.net_profit),
                        Number(data.net_profit) < 0
                            ? "text-red-600"
                            : "text-emerald-600",
                    ],
                ]}
            />
            <FinanceChart
                title="Komposisi Laba Rugi"
                subtitle="Batang membandingkan komponen laporan pada periode aktif."
                items={[
                    { label: "Pendapatan", value: data.revenue },
                    {
                        label: "HPP",
                        value: data.cost_of_sales,
                        tone: "bg-amber-500",
                    },
                    {
                        label: "Beban Operasional",
                        value: data.operating_expense,
                        tone: "bg-red-500",
                    },
                    {
                        label: "Laba Bersih",
                        value: Math.abs(Number(data.net_profit)),
                        tone:
                            Number(data.net_profit) < 0
                                ? "bg-red-500"
                                : "bg-blue-500",
                    },
                ]}
            />
            <FinanceTrendChart
                title="Tren Laba Bersih"
                subtitle="Pendapatan dan beban dibandingkan per bulan; garis biru menunjukkan laba/rugi bersih."
                items={data.trend ?? []}
                series={[
                    {
                        key: "profit",
                        label: "Laba/rugi bersih",
                        color: "#2563eb",
                        area: true,
                    },
                    { key: "revenue", label: "Pendapatan", color: "#10b981" },
                    { key: "expense", label: "Beban", color: "#ef4444" },
                ]}
            />
            <Table
                rows={data.rows ?? []}
                columns={[
                    { key: "code", label: "Kode" },
                    { key: "name", label: "Akun" },
                    { key: "category", label: "Kategori" },
                    {
                        key: "amount",
                        label: "Nilai",
                        render: (row) => money(row.amount),
                    },
                ]}
            />
        </div>
    );
}

function BalanceSheet({ data }) {
    return (
        <div className="grid gap-4">
            <StatGrid
                rows={[
                    ["Total Aset", money(data.assets)],
                    ["Total Liabilitas", money(data.liabilities)],
                    ["Total Ekuitas", money(data.equity)],
                    ["Liabilitas + Ekuitas", money(data.liabilities_equity)],
                    [
                        "Status",
                        data.balanced ? "Balance" : "Belum Balance",
                        data.balanced ? "text-emerald-600" : "text-red-600",
                    ],
                ]}
            />
            <FinanceChart
                title="Struktur Neraca"
                subtitle="Perbandingan aset, liabilitas, dan ekuitas pada tanggal laporan."
                items={[
                    { label: "Aset", value: data.assets },
                    {
                        label: "Liabilitas",
                        value: data.liabilities,
                        tone: "bg-amber-500",
                    },
                    {
                        label: "Ekuitas",
                        value: data.equity,
                        tone: "bg-blue-500",
                    },
                ]}
            />
            <Table
                rows={data.rows ?? []}
                columns={[
                    { key: "code", label: "Kode" },
                    { key: "name", label: "Akun" },
                    { key: "category", label: "Kategori" },
                    {
                        key: "amount",
                        label: "Saldo",
                        render: (row) => money(row.amount),
                    },
                ]}
            />
        </div>
    );
}

function CashFlow({ data }) {
    return (
        <div className="grid gap-4">
            <StatGrid
                rows={[
                    ["Saldo Awal", money(data.opening_balance)],
                    ["Kas Masuk", money(data.cash_in), "text-emerald-600"],
                    ["Kas Keluar", money(data.cash_out), "text-red-600"],
                    ["Arus Kas Bersih", money(data.net_cash_flow)],
                    ["Saldo Akhir", money(data.ending_balance)],
                ]}
            />
            <FinanceTrendChart
                title="Arus Kas dan Saldo Kumulatif"
                subtitle="Saldo kumulatif mengikuti kas masuk dan kas keluar pada setiap tanggal transaksi."
                items={data.trend ?? []}
                series={[
                    {
                        key: "balance",
                        label: "Saldo kumulatif",
                        color: "#2563eb",
                        area: true,
                    },
                    { key: "in", label: "Kas masuk", color: "#10b981" },
                    { key: "out", label: "Kas keluar", color: "#ef4444" },
                ]}
            />
            <Table
                rows={data.rows ?? []}
                columns={[
                    { key: "date", label: "Tanggal" },
                    { key: "type", label: "Jenis" },
                    { key: "post", label: "Pos" },
                    { key: "bank", label: "Kas / Bank" },
                    { key: "description", label: "Keterangan" },
                    {
                        key: "amount",
                        label: "Nominal",
                        render: (row) => money(row.amount),
                    },
                ]}
            />
        </div>
    );
}

function Receivable({ data }) {
    return (
        <div className="grid gap-4">
            <StatGrid
                rows={[
                    ["Total Tagihan", money(data.summary?.bill)],
                    ["Sudah Dibayar", money(data.summary?.paid)],
                    ["Sisa Piutang", money(data.summary?.remaining)],
                    [
                        "Lewat Jatuh Tempo",
                        money(data.summary?.overdue),
                        "text-red-600",
                    ],
                ]}
            />
            <FinanceChart
                title="Aging Piutang Pelanggan"
                subtitle="Sisa piutang dikelompokkan menurut umur keterlambatan."
                primaryLabel="Sisa piutang"
                items={data.aging ?? []}
            />
            <Table
                rows={data.rows ?? []}
                columns={[
                    { key: "reference", label: "SPR" },
                    { key: "customer", label: "Pelanggan" },
                    { key: "perumahan", label: "Perumahan" },
                    { key: "type", label: "Tagihan" },
                    { key: "due_date", label: "Jatuh Tempo" },
                    {
                        key: "bill",
                        label: "Tagihan",
                        render: (row) => money(row.bill),
                    },
                    {
                        key: "paid",
                        label: "Dibayar",
                        render: (row) => money(row.paid),
                    },
                    {
                        key: "remaining",
                        label: "Sisa",
                        render: (row) => money(row.remaining),
                    },
                    { key: "status", label: "Status" },
                ]}
            />
        </div>
    );
}

function Payable({ data }) {
    return (
        <div className="grid gap-4">
            <StatGrid
                rows={[
                    ["Total Tagihan", money(data.summary?.bill)],
                    ["Sudah Dibayar", money(data.summary?.paid)],
                    [
                        "Sisa Hutang",
                        money(data.summary?.remaining),
                        "text-red-600",
                    ],
                ]}
            />
            <FinanceChart
                title="Aging Hutang Supplier & Kontraktor"
                subtitle="Sisa hutang dikelompokkan menurut umur keterlambatan."
                primaryLabel="Sisa hutang"
                items={data.aging ?? []}
            />
            <Table
                rows={data.rows ?? []}
                columns={[
                    { key: "source", label: "Jenis" },
                    { key: "reference", label: "Referensi" },
                    { key: "vendor", label: "Vendor" },
                    { key: "perumahan", label: "Perumahan" },
                    { key: "due_date", label: "Jatuh Tempo" },
                    {
                        key: "bill",
                        label: "Tagihan",
                        render: (row) => money(row.bill),
                    },
                    {
                        key: "paid",
                        label: "Dibayar",
                        render: (row) => money(row.paid),
                    },
                    {
                        key: "remaining",
                        label: "Sisa",
                        render: (row) => money(row.remaining),
                    },
                    { key: "status", label: "Status" },
                ]}
            />
        </div>
    );
}

const icons = {
    dashboard: WalletCards,
    pemasukan: TrendingUp,
    pengeluaran: TrendingDown,
    "daftar-akun": ListTree,
    "jurnal-umum": BookOpen,
    "buku-besar": Library,
    "neraca-saldo": Scale,
    "laba-rugi": BarChart3,
    neraca: Landmark,
    "arus-kas": Activity,
    piutang: TrendingUp,
    hutang: TrendingDown,
};

export default function Index({
    title,
    section,
    baseUrl,
    filters,
    options,
    data,
    permissions = {},
}) {
    const Icon = icons[section] ?? WalletCards;
    const canCreate = permissions.canCreate ?? false;
    const canUpdate = permissions.canUpdate ?? false;
    const content = {
        dashboard: <Dashboard data={data} />,
        pemasukan: (
            <CashTransaction
                data={data}
                options={options}
                canCreate={canCreate}
                type="pemasukan"
            />
        ),
        pengeluaran: (
            <CashTransaction
                data={data}
                options={options}
                canCreate={canCreate}
                type="pengeluaran"
            />
        ),
        "daftar-akun": (
            <AccountList data={data} canWrite={canCreate || canUpdate} />
        ),
        "jurnal-umum": (
            <JournalList data={data} options={options} canCreate={canCreate} />
        ),
        "buku-besar": <Ledger data={data} />,
        "neraca-saldo": <TrialBalance data={data} />,
        "laba-rugi": <ProfitLoss data={data} />,
        neraca: <BalanceSheet data={data} />,
        "arus-kas": <CashFlow data={data} />,
        piutang: <Receivable data={data} />,
        hutang: <Payable data={data} />,
    }[section];
    const filterable = !["daftar-akun", "pemasukan", "pengeluaran"].includes(
        section,
    );

    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <Card className="p-6">
                    <div className="flex items-center gap-3">
                        <span className="grid h-11 w-11 place-items-center rounded-lg bg-ink text-white dark:bg-white dark:text-ink">
                            <Icon size={20} />
                        </span>
                        <div>
                            <p className="text-xs font-extrabold uppercase text-ink-soft">
                                ERP Keuangan
                            </p>
                            <h1 className="text-2xl font-extrabold">{title}</h1>
                        </div>
                    </div>
                </Card>
                {filterable && (
                    <FilterBar
                        baseUrl={baseUrl}
                        filters={filters}
                        options={options}
                        showAccount={section === "buku-besar"}
                    />
                )}
                {content}
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Keuangan"}>{page}</AdminLayout>
);
