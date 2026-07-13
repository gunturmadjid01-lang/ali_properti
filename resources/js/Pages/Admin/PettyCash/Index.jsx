import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowDownCircle,
    ArrowUpCircle,
    Banknote,
    CheckCircle2,
    FileCheck2,
    FileText,
    Plus,
    ReceiptText,
    WalletCards,
    XCircle,
} from "lucide-react";
import { useMemo, useState } from "react";
import {
    Button,
    CurrencyInput,
    Dropdown,
    FieldLabel,
    Input,
    ModalForm,
    Textarea,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const rupiah = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value || 0));

const categoryOptions = [
    { value: "material", label: "Material pembangunan" },
    { value: "upah_tukang", label: "Upah tukang" },
    { value: "perbaikan_unit", label: "Perbaikan unit" },
    { value: "pekerjaan_proyek", label: "Pekerjaan proyek" },
    { value: "atk", label: "ATK kantor" },
    { value: "transport", label: "Transport / bensin" },
    { value: "konsumsi", label: "Konsumsi" },
    { value: "utilitas", label: "Utilitas / pulsa" },
    { value: "pemeliharaan_kantor", label: "Pemeliharaan kantor" },
    { value: "lainnya", label: "Operasional lainnya" },
];

const statusClasses = {
    draft: "bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-white/70",
    pending:
        "bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200",
    approved:
        "bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200",
    rejected: "bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-200",
};

const statusLabel = {
    draft: "Draft",
    pending: "Menunggu Approval",
    approved: "Disetujui",
    rejected: "Ditolak",
};

const costLabels = {
    operational: "Beban Operasional",
    project_hpp: "HPP Perumahan",
    unit_hpp: "HPP Unit",
};

const FileField = ({ label, required, error, onChange, hint }) => (
    <div className="grid gap-2">
        <FieldLabel required={required}>{label}</FieldLabel>
        <input
            className="block w-full rounded-lg border border-silver-deep/70 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white dark:border-white/10 dark:bg-white/5"
            type="file"
            accept=".jpg,.jpeg,.png,.webp,.pdf"
            onChange={(event) => onChange(event.target.files?.[0] ?? null)}
        />
        {hint && <p className="text-xs text-ink-soft">{hint}</p>}
        {error && <p className="text-xs font-bold text-red-600">{error}</p>}
    </div>
);

export default function Index({
    section,
    filters,
    accounts,
    fundings,
    expenses,
    ledgers,
    options,
    permissions,
    hppCategories,
    reportSummary,
}) {
    const [accountOpen, setAccountOpen] = useState(false);
    const [fundingOpen, setFundingOpen] = useState(false);
    const [expenseOpen, setExpenseOpen] = useState(false);
    const [review, setReview] = useState(null);
    const [rejecting, setRejecting] = useState(null);

    const accountOptions = accounts.map((row) => ({
        value: String(row.id),
        label: `${row.code} - ${row.name} (${rupiah(row.balance)})`,
    }));
    const accountForm = useForm({
        name: "Kas Kecil Utama",
        branch_id: "",
        target_amount: "",
        minimum_balance: "",
        request_date: new Date().toISOString().slice(0, 10),
        request_notes: "",
        request_proof: null,
    });
    const fundingForm = useForm({
        petty_cash_account_id: accountOptions[0]?.value ?? "",
        request_date: new Date().toISOString().slice(0, 10),
        amount: "",
        request_notes: "",
        status: "pending",
    });
    const expenseForm = useForm({
        petty_cash_account_id: accountOptions[0]?.value ?? "",
        expense_date: new Date().toISOString().slice(0, 10),
        category: "",
        perumahan_id: "",
        detail_rumah_id: "",
        kelompok_hpp_id: "",
        tahapan_pembangunan_id: "",
        amount: "",
        description: "",
        proof: null,
    });
    const approvalForm = useForm({ approval_proof: null, approval_notes: "" });
    const rejectForm = useForm({ rejection_notes: "" });

    const isHpp = hppCategories.includes(expenseForm.data.category);
    const detectedCostType = !isHpp
        ? "operational"
        : expenseForm.data.detail_rumah_id
          ? "unit_hpp"
          : expenseForm.data.perumahan_id
            ? "project_hpp"
            : null;
    const unitOptions = useMemo(
        () =>
            options.units.filter(
                (unit) =>
                    !expenseForm.data.perumahan_id ||
                    unit.perumahan_id === expenseForm.data.perumahan_id,
            ),
        [options.units, expenseForm.data.perumahan_id],
    );

    const submitAccount = (event) => {
        event.preventDefault();
        accountForm.post("/admin/kas-kecil/rekening", {
            forceFormData: true,
            onSuccess: () => {
                setAccountOpen(false);
                accountForm.reset();
            },
        });
    };
    const submitFunding = (event, status = "pending") => {
        event?.preventDefault();
        fundingForm.transform((data) => ({ ...data, status }));
        fundingForm.post("/admin/kas-kecil/pengisian", {
            onSuccess: () => {
                setFundingOpen(false);
                fundingForm.reset();
            },
            onFinish: () => fundingForm.transform((data) => data),
        });
    };
    const submitExpense = (event) => {
        event.preventDefault();
        expenseForm.post("/admin/kas-kecil/pengeluaran", {
            forceFormData: true,
            onSuccess: () => {
                setExpenseOpen(false);
                expenseForm.reset();
            },
        });
    };
    const approve = (event) => {
        event.preventDefault();
        approvalForm.post(`/admin/kas-kecil/pengisian/${review.id}/setujui`, {
            forceFormData: true,
            onSuccess: () => {
                setReview(null);
                approvalForm.reset();
            },
        });
    };
    const reject = (event) => {
        event.preventDefault();
        rejectForm.post(`/admin/kas-kecil/pengisian/${rejecting.id}/tolak`, {
            onSuccess: () => {
                setRejecting(null);
                rejectForm.reset();
            },
        });
    };

    const nav = [
        ["saldo", "Saldo", WalletCards],
        ["pengisian", "Pengisian Dana", ArrowUpCircle],
        ["pengeluaran", "Pengeluaran", ArrowDownCircle],
        ["laporan", "Laporan", FileText],
    ];
    const totalBalance = accounts.reduce((sum, row) => sum + row.balance, 0);
    return (
        <>
            <Head title="Kas Kecil" />
            <div className="grid gap-5 pb-8">
                <section className="overflow-hidden rounded-2xl bg-gradient-to-br from-ink via-graphite to-[#26343d] p-6 text-white shadow-lg">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p className="text-xs font-black uppercase tracking-[0.18em] text-gold">
                                Keuangan & Akuntansi
                            </p>
                            <h1 className="mt-2 font-display text-3xl font-black">
                                Kas Kecil
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm text-white/65">
                                Pembentukan dana, pengisian kembali,
                                pengeluaran, saldo, dan jejak audit dalam satu
                                alur terkontrol.
                            </p>
                        </div>
                        <div className="rounded-xl border border-white/10 bg-white/10 px-5 py-3">
                            <p className="text-xs font-bold text-white/55">
                                Total saldo tersedia
                            </p>
                            <p className="mt-1 text-2xl font-black text-gold">
                                {rupiah(totalBalance)}
                            </p>
                        </div>
                    </div>
                </section>

                <nav className="flex gap-2 overflow-x-auto rounded-xl border border-silver-deep/60 bg-white p-2 shadow-sm dark:border-white/10 dark:bg-graphite">
                    {nav.map(([key, label, Icon]) => (
                        <Link
                            key={key}
                            href={`/admin/kas-kecil/${key}`}
                            className={`flex shrink-0 items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-extrabold transition ${section === key ? "bg-ink text-white shadow-sm dark:bg-white dark:text-ink" : "text-ink-soft hover:bg-silver-soft dark:hover:bg-white/5"}`}
                        >
                            <Icon size={17} /> {label}
                        </Link>
                    ))}
                </nav>

                {section === "saldo" && (
                    <div className="grid gap-4">
                        <div className="flex flex-wrap justify-between gap-3">
                            <div>
                                <h2 className="text-xl font-black">
                                    Saldo Kas Kecil
                                </h2>
                                <p className="text-sm text-ink-soft">
                                    Saldo hanya bertambah setelah pengisian
                                    disetujui.
                                </p>
                            </div>
                            {permissions.can_create_account && (
                                <Button onClick={() => setAccountOpen(true)}>
                                    <Plus size={17} /> Bentuk Kas Kecil
                                </Button>
                            )}
                        </div>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {accounts.map((row) => (
                                <article
                                    key={row.id}
                                    className="rounded-2xl border border-silver-deep/60 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/[0.04]"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-xs font-black uppercase tracking-wider text-ink-soft">
                                                {row.code}
                                            </p>
                                            <h3 className="mt-1 text-lg font-black">
                                                {row.name}
                                            </h3>
                                            <p className="text-xs text-ink-soft">
                                                {row.branch || "Semua cabang"}
                                            </p>
                                        </div>
                                        <span
                                            className={`rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${row.is_low ? "bg-red-100 text-red-700" : "bg-emerald-100 text-emerald-700"}`}
                                        >
                                            {row.is_low
                                                ? "Saldo rendah"
                                                : "Aman"}
                                        </span>
                                    </div>
                                    <p className="mt-5 text-2xl font-black">
                                        {rupiah(row.balance)}
                                    </p>
                                    <div className="mt-4 h-2 overflow-hidden rounded-full bg-silver-soft">
                                        <div
                                            className="h-full rounded-full bg-gold"
                                            style={{
                                                width: `${Math.min(100, row.target_amount ? (row.balance / row.target_amount) * 100 : 0)}%`,
                                            }}
                                        />
                                    </div>
                                    <div className="mt-2 flex justify-between text-xs font-bold text-ink-soft">
                                        <span>
                                            Minimum{" "}
                                            {rupiah(row.minimum_balance)}
                                        </span>
                                        <span>
                                            Target {rupiah(row.target_amount)}
                                        </span>
                                    </div>
                                </article>
                            ))}
                            {!accounts.length && (
                                <div className="rounded-2xl border border-dashed border-silver-deep p-10 text-center text-sm font-bold text-ink-soft md:col-span-2 xl:col-span-3">
                                    Belum ada kas kecil. Owner atau manajer
                                    dapat membentuk dana awal.
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {section === "pengisian" && (
                    <section className="overflow-hidden rounded-2xl border border-silver-deep/60 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
                        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-silver-deep/60 p-5 dark:border-white/10">
                            <div>
                                <h2 className="text-xl font-black">
                                    Pengisian Dana
                                </h2>
                                <p className="text-sm text-ink-soft">
                                    Draft → Menunggu Approval → Saldo bertambah
                                    setelah disetujui.
                                </p>
                            </div>
                            {permissions.can_create && accounts.length > 0 && (
                                <Button onClick={() => setFundingOpen(true)}>
                                    <Plus size={17} /> Permohonan Isi Ulang
                                </Button>
                            )}
                        </header>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-silver-soft/70 text-left text-xs uppercase text-ink-soft dark:bg-white/5">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Nomor / Tanggal
                                        </th>
                                        <th className="px-4 py-3">Kas Kecil</th>
                                        <th className="px-4 py-3">Nominal</th>
                                        <th className="px-4 py-3">Pemohon</th>
                                        <th className="px-4 py-3">
                                            Status & Bukti
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                    {fundings.map((row) => (
                                        <tr key={row.id}>
                                            <td className="px-4 py-4">
                                                <p className="font-black">
                                                    {row.number}
                                                </p>
                                                <p className="text-xs text-ink-soft">
                                                    {row.request_date} ·{" "}
                                                    {row.type === "initial"
                                                        ? "Pembentukan"
                                                        : "Isi ulang"}
                                                </p>
                                            </td>
                                            <td className="px-4 py-4 font-bold">
                                                {row.account}
                                            </td>
                                            <td className="px-4 py-4 font-black">
                                                {rupiah(row.amount)}
                                            </td>
                                            <td className="px-4 py-4">
                                                {row.requester || "-"}
                                            </td>
                                            <td className="px-4 py-4">
                                                <span
                                                    className={`rounded-full px-2.5 py-1 text-xs font-black ${statusClasses[row.status]}`}
                                                >
                                                    {statusLabel[row.status]}
                                                </span>
                                                <div className="mt-2 flex gap-2 text-xs font-bold">
                                                    {row.request_proof_url && (
                                                        <a
                                                            className="text-blue-600"
                                                            href={
                                                                row.request_proof_url
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            Bukti pengajuan
                                                        </a>
                                                    )}
                                                    {row.approval_proof_url && (
                                                        <a
                                                            className="text-emerald-600"
                                                            href={
                                                                row.approval_proof_url
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            Bukti transfer
                                                        </a>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="flex justify-end gap-2">
                                                    {row.status === "draft" &&
                                                        permissions.can_create && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    router.post(
                                                                        `/admin/kas-kecil/pengisian/${row.id}/kirim`,
                                                                    )
                                                                }
                                                            >
                                                                Kirim
                                                            </Button>
                                                        )}
                                                    {row.status === "pending" &&
                                                        permissions.can_approve && (
                                                            <>
                                                                <Button
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setReview(
                                                                            row,
                                                                        )
                                                                    }
                                                                >
                                                                    <CheckCircle2
                                                                        size={
                                                                            15
                                                                        }
                                                                    />{" "}
                                                                    Setujui
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    className="bg-red-600 text-white hover:bg-red-700"
                                                                    onClick={() =>
                                                                        setRejecting(
                                                                            row,
                                                                        )
                                                                    }
                                                                >
                                                                    <XCircle
                                                                        size={
                                                                            15
                                                                        }
                                                                    />
                                                                </Button>
                                                            </>
                                                        )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {!fundings.length && (
                                        <tr>
                                            <td
                                                colSpan="6"
                                                className="px-5 py-12 text-center font-bold text-ink-soft"
                                            >
                                                Belum ada permohonan pengisian
                                                dana.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                {section === "pengeluaran" && (
                    <section className="overflow-hidden rounded-2xl border border-silver-deep/60 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
                        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-silver-deep/60 p-5 dark:border-white/10">
                            <div>
                                <h2 className="text-xl font-black">
                                    Pengeluaran Kas Kecil
                                </h2>
                                <p className="text-sm text-ink-soft">
                                    Tujuan HPP ditentukan otomatis dan saldo
                                    langsung berkurang.
                                </p>
                            </div>
                            {permissions.can_create && accounts.length > 0 && (
                                <Button onClick={() => setExpenseOpen(true)}>
                                    <Plus size={17} /> Catat Pengeluaran
                                </Button>
                            )}
                        </header>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-silver-soft/70 text-left text-xs uppercase text-ink-soft dark:bg-white/5">
                                    <tr>
                                        <th className="px-4 py-3">Transaksi</th>
                                        <th className="px-4 py-3">Kategori</th>
                                        <th className="px-4 py-3">
                                            Alokasi Otomatis
                                        </th>
                                        <th className="px-4 py-3">
                                            Keterangan
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Nominal
                                        </th>
                                        <th className="px-4 py-3">Bukti</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                    {expenses.map((row) => (
                                        <tr key={row.id}>
                                            <td className="px-4 py-4">
                                                <p className="font-black">
                                                    {row.number}
                                                </p>
                                                <p className="text-xs text-ink-soft">
                                                    {row.expense_date} ·{" "}
                                                    {row.account}
                                                </p>
                                            </td>
                                            <td className="px-4 py-4 font-bold">
                                                {categoryOptions.find(
                                                    (item) =>
                                                        item.value ===
                                                        row.category,
                                                )?.label || row.category}
                                            </td>
                                            <td className="px-4 py-4">
                                                <span
                                                    className={`rounded-full px-2.5 py-1 text-xs font-black ${row.cost_type === "operational" ? "bg-slate-100 text-slate-700" : "bg-amber-100 text-amber-800"}`}
                                                >
                                                    {costLabels[row.cost_type]}
                                                </span>
                                                <p className="mt-1 text-xs text-ink-soft">
                                                    {[row.perumahan, row.unit]
                                                        .filter(Boolean)
                                                        .join(" · ") ||
                                                        "Perusahaan"}
                                                </p>
                                            </td>
                                            <td className="max-w-xs px-4 py-4">
                                                <p className="line-clamp-2">
                                                    {row.description}
                                                </p>
                                                <p className="text-xs text-ink-soft">
                                                    Oleh {row.creator || "-"}
                                                </p>
                                            </td>
                                            <td className="px-4 py-4 text-right font-black text-red-600">
                                                -{rupiah(row.amount)}
                                            </td>
                                            <td className="px-4 py-4">
                                                <a
                                                    href={row.proof_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1 font-bold text-blue-600"
                                                >
                                                    <FileCheck2 size={15} />{" "}
                                                    Buka
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                    {!expenses.length && (
                                        <tr>
                                            <td
                                                colSpan="6"
                                                className="px-5 py-12 text-center font-bold text-ink-soft"
                                            >
                                                Belum ada pengeluaran kas kecil.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                {section === "laporan" && (
                    <div className="grid gap-4">
                        <form
                            className="grid gap-3 rounded-xl border border-silver-deep/60 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04] md:grid-cols-4"
                            method="get"
                        >
                            <Dropdown
                                name="account_id"
                                value={filters.account_id || ""}
                                options={[
                                    { value: "", label: "Semua kas kecil" },
                                    ...accountOptions,
                                ]}
                                label="Semua kas kecil"
                                onChange={(value) =>
                                    router.get(
                                        "/admin/kas-kecil/laporan",
                                        { ...filters, account_id: value },
                                        { preserveState: true },
                                    )
                                }
                            />
                            <Input
                                name="from"
                                type="date"
                                label="Dari tanggal"
                                defaultValue={filters.from || ""}
                            />
                            <Input
                                name="to"
                                type="date"
                                label="Sampai tanggal"
                                defaultValue={filters.to || ""}
                            />
                            <Button type="submit" className="self-end">
                                Terapkan Filter
                            </Button>
                        </form>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <Summary
                                label="Saldo Saat Ini"
                                value={reportSummary.balance}
                                icon={WalletCards}
                                tone="ink"
                            />
                            <Summary
                                label="Dana Masuk"
                                value={reportSummary.cash_in}
                                icon={ArrowUpCircle}
                                tone="emerald"
                            />
                            <Summary
                                label="Pengeluaran"
                                value={reportSummary.cash_out}
                                icon={ArrowDownCircle}
                                tone="red"
                            />
                            <Summary
                                label="Beban Operasional"
                                value={reportSummary.operational}
                                icon={ReceiptText}
                                tone="red"
                            />
                            <Summary
                                label="HPP Perumahan"
                                value={reportSummary.project_hpp}
                                icon={Banknote}
                                tone="ink"
                            />
                            <Summary
                                label="HPP Unit"
                                value={reportSummary.unit_hpp}
                                icon={FileCheck2}
                                tone="ink"
                            />
                        </div>
                        <section className="overflow-hidden rounded-2xl border border-silver-deep/60 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
                            <header className="border-b border-silver-deep/60 p-5 dark:border-white/10">
                                <h2 className="text-xl font-black">
                                    Buku Mutasi Kas Kecil
                                </h2>
                                <p className="text-sm text-ink-soft">
                                    Jejak saldo setelah setiap transaksi.
                                </p>
                            </header>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-silver-soft/70 text-left text-xs uppercase text-ink-soft dark:bg-white/5">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Tanggal
                                            </th>
                                            <th className="px-4 py-3">
                                                Kas Kecil
                                            </th>
                                            <th className="px-4 py-3">
                                                Keterangan
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Masuk
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Keluar
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Saldo Setelah
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                        {ledgers.map((row) => (
                                            <tr key={row.id}>
                                                <td className="px-4 py-4 font-bold">
                                                    {row.transaction_date}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {row.account}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {row.description}
                                                </td>
                                                <td className="px-4 py-4 text-right font-black text-emerald-600">
                                                    {row.direction === "in"
                                                        ? rupiah(row.amount)
                                                        : "-"}
                                                </td>
                                                <td className="px-4 py-4 text-right font-black text-red-600">
                                                    {row.direction === "out"
                                                        ? rupiah(row.amount)
                                                        : "-"}
                                                </td>
                                                <td className="px-4 py-4 text-right font-black">
                                                    {rupiah(row.balance_after)}
                                                </td>
                                            </tr>
                                        ))}
                                        {!ledgers.length && (
                                            <tr>
                                                <td
                                                    colSpan="6"
                                                    className="px-5 py-12 text-center font-bold text-ink-soft"
                                                >
                                                    Belum ada mutasi pada
                                                    periode ini.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                )}
            </div>

            <ModalForm
                open={accountOpen}
                onClose={() => setAccountOpen(false)}
                onSubmit={submitAccount}
                title="Pembentukan Kas Kecil"
                description="Dana awal masuk ke saldo setelah disetujui dan dilengkapi bukti transfer."
                size="xl"
                contentClassName="md:grid-cols-2"
                actions={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAccountOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={accountForm.processing}>
                            <Banknote size={17} /> Ajukan Pembentukan
                        </Button>
                    </>
                }
            >
                <Input
                    label="Nama Kas Kecil"
                    required
                    value={accountForm.data.name}
                    error={accountForm.errors.name}
                    onChange={(e) =>
                        accountForm.setData("name", e.target.value)
                    }
                />
                <div className="grid gap-2">
                    <FieldLabel>Cabang</FieldLabel>
                    <Dropdown
                        value={accountForm.data.branch_id}
                        options={[
                            { value: "", label: "Semua cabang" },
                            ...options.branches,
                        ]}
                        label="Pilih cabang"
                        onChange={(v) => accountForm.setData("branch_id", v)}
                    />
                </div>
                <CurrencyInput
                    label="Dana Awal / Target"
                    required
                    value={accountForm.data.target_amount}
                    error={accountForm.errors.target_amount}
                    onChange={(v) => accountForm.setData("target_amount", v)}
                />
                <CurrencyInput
                    label="Batas Saldo Minimum"
                    required
                    value={accountForm.data.minimum_balance}
                    error={accountForm.errors.minimum_balance}
                    onChange={(v) => accountForm.setData("minimum_balance", v)}
                />
                <Input
                    label="Tanggal Pengajuan"
                    required
                    type="date"
                    value={accountForm.data.request_date}
                    error={accountForm.errors.request_date}
                    onChange={(e) =>
                        accountForm.setData("request_date", e.target.value)
                    }
                />
                <FileField
                    label="Bukti Penarikan / Transfer Awal"
                    required
                    error={accountForm.errors.request_proof}
                    onChange={(file) =>
                        accountForm.setData("request_proof", file)
                    }
                    hint="JPG, PNG, WEBP, atau PDF. Maksimal 5 MB."
                />
                <div className="md:col-span-2">
                    <Textarea
                        label="Catatan"
                        value={accountForm.data.request_notes}
                        error={accountForm.errors.request_notes}
                        onChange={(e) =>
                            accountForm.setData("request_notes", e.target.value)
                        }
                    />
                </div>
            </ModalForm>

            <ModalForm
                open={fundingOpen}
                onClose={() => setFundingOpen(false)}
                onSubmit={submitFunding}
                title="Permohonan Pengisian Kembali"
                description="Simpan sebagai draft atau langsung kirim untuk approval."
                contentClassName="md:grid-cols-2"
                size="xl"
                actions={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={fundingForm.processing}
                            onClick={() => submitFunding(null, "draft")}
                        >
                            Simpan Draft
                        </Button>
                        <Button type="submit" disabled={fundingForm.processing}>
                            <ArrowUpCircle size={17} /> Kirim Approval
                        </Button>
                    </>
                }
            >
                <div className="grid gap-2 md:col-span-2">
                    <FieldLabel required>Kas Kecil</FieldLabel>
                    <Dropdown
                        value={fundingForm.data.petty_cash_account_id}
                        options={accountOptions}
                        label="Pilih kas kecil"
                        onChange={(v) =>
                            fundingForm.setData("petty_cash_account_id", v)
                        }
                    />
                    {fundingForm.errors.petty_cash_account_id && (
                        <p className="text-xs font-bold text-red-600">
                            {fundingForm.errors.petty_cash_account_id}
                        </p>
                    )}
                </div>
                <Input
                    label="Tanggal Permohonan"
                    required
                    type="date"
                    value={fundingForm.data.request_date}
                    error={fundingForm.errors.request_date}
                    onChange={(e) =>
                        fundingForm.setData("request_date", e.target.value)
                    }
                />
                <CurrencyInput
                    label="Nominal Isi Ulang"
                    required
                    value={fundingForm.data.amount}
                    error={fundingForm.errors.amount}
                    onChange={(v) => fundingForm.setData("amount", v)}
                />
                <div className="md:col-span-2">
                    <Textarea
                        label="Alasan / Catatan"
                        value={fundingForm.data.request_notes}
                        error={fundingForm.errors.request_notes}
                        onChange={(e) =>
                            fundingForm.setData("request_notes", e.target.value)
                        }
                    />
                </div>
            </ModalForm>

            <ModalForm
                open={expenseOpen}
                onClose={() => setExpenseOpen(false)}
                onSubmit={submitExpense}
                title="Pengeluaran Kas Kecil"
                description="Sistem mendeteksi alokasi biaya berdasarkan kategori dan tujuan yang dipilih."
                contentClassName="md:grid-cols-2"
                size="2xl"
                actions={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setExpenseOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                expenseForm.processing ||
                                (isHpp && !detectedCostType)
                            }
                        >
                            <ReceiptText size={17} /> Simpan Pengeluaran
                        </Button>
                    </>
                }
            >
                <div className="grid gap-2">
                    <FieldLabel required>Kas Kecil</FieldLabel>
                    <Dropdown
                        value={expenseForm.data.petty_cash_account_id}
                        options={accountOptions}
                        label="Pilih kas kecil"
                        onChange={(v) =>
                            expenseForm.setData("petty_cash_account_id", v)
                        }
                    />
                </div>
                <Input
                    label="Tanggal Pengeluaran"
                    required
                    type="date"
                    value={expenseForm.data.expense_date}
                    error={expenseForm.errors.expense_date}
                    onChange={(e) =>
                        expenseForm.setData("expense_date", e.target.value)
                    }
                />
                <div className="grid gap-2">
                    <FieldLabel required>Kategori</FieldLabel>
                    <Dropdown
                        value={expenseForm.data.category}
                        options={categoryOptions}
                        label="Pilih kategori"
                        onChange={(v) => expenseForm.setData("category", v)}
                    />
                    {expenseForm.errors.category && (
                        <p className="text-xs font-bold text-red-600">
                            {expenseForm.errors.category}
                        </p>
                    )}
                </div>
                <CurrencyInput
                    label="Nominal"
                    required
                    value={expenseForm.data.amount}
                    error={expenseForm.errors.amount}
                    onChange={(v) => expenseForm.setData("amount", v)}
                />
                <div className="grid gap-2">
                    <FieldLabel required={isHpp}>Perumahan / Proyek</FieldLabel>
                    <Dropdown
                        value={expenseForm.data.perumahan_id}
                        options={[
                            { value: "", label: "Tidak terkait proyek" },
                            ...options.perumahans,
                        ]}
                        label="Pilih perumahan"
                        onChange={(v) => {
                            expenseForm.setData("perumahan_id", v);
                            expenseForm.setData("detail_rumah_id", "");
                        }}
                    />
                    {expenseForm.errors.perumahan_id && (
                        <p className="text-xs font-bold text-red-600">
                            {expenseForm.errors.perumahan_id}
                        </p>
                    )}
                </div>
                <div className="grid gap-2">
                    <FieldLabel>Unit Rumah</FieldLabel>
                    <Dropdown
                        value={expenseForm.data.detail_rumah_id}
                        options={[
                            { value: "", label: "Tanpa unit tertentu" },
                            ...unitOptions,
                        ]}
                        label="Pilih unit"
                        onChange={(v) =>
                            expenseForm.setData("detail_rumah_id", v)
                        }
                    />
                    {expenseForm.errors.detail_rumah_id && (
                        <p className="text-xs font-bold text-red-600">
                            {expenseForm.errors.detail_rumah_id}
                        </p>
                    )}
                </div>
                {isHpp && (
                    <>
                        <div className="grid gap-2">
                            <FieldLabel>Kelompok HPP</FieldLabel>
                            <Dropdown
                                value={expenseForm.data.kelompok_hpp_id}
                                options={[
                                    { value: "", label: "Tanpa kelompok" },
                                    ...options.hppGroups,
                                ]}
                                label="Pilih kelompok HPP"
                                onChange={(v) =>
                                    expenseForm.setData("kelompok_hpp_id", v)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <FieldLabel>Tahapan Pembangunan</FieldLabel>
                            <Dropdown
                                value={expenseForm.data.tahapan_pembangunan_id}
                                options={[
                                    { value: "", label: "Tanpa tahapan" },
                                    ...options.stages,
                                ]}
                                label="Pilih tahapan"
                                onChange={(v) =>
                                    expenseForm.setData(
                                        "tahapan_pembangunan_id",
                                        v,
                                    )
                                }
                            />
                        </div>
                    </>
                )}
                <div
                    className={`flex items-start gap-3 rounded-xl border p-4 md:col-span-2 ${detectedCostType ? "border-emerald-200 bg-emerald-50 text-emerald-800" : "border-amber-200 bg-amber-50 text-amber-800"}`}
                >
                    {detectedCostType ? (
                        <CheckCircle2 className="shrink-0" size={19} />
                    ) : (
                        <AlertTriangle className="shrink-0" size={19} />
                    )}
                    <div>
                        <p className="text-sm font-black">
                            Deteksi:{" "}
                            {detectedCostType
                                ? costLabels[detectedCostType]
                                : "Pilih proyek atau unit"}
                        </p>
                        <p className="mt-1 text-xs font-bold">
                            {detectedCostType === "unit_hpp"
                                ? "Biaya otomatis masuk realisasi HPP unit terpilih."
                                : detectedCostType === "project_hpp"
                                  ? "Biaya otomatis masuk realisasi HPP perumahan."
                                  : detectedCostType === "operational"
                                    ? "Biaya dicatat sebagai operasional dan tidak menambah HPP."
                                    : "Kategori pembangunan wajib memiliki tujuan biaya."}
                        </p>
                    </div>
                </div>
                <div className="md:col-span-2">
                    <Textarea
                        label="Keterangan Pengeluaran"
                        required
                        value={expenseForm.data.description}
                        error={expenseForm.errors.description}
                        onChange={(e) =>
                            expenseForm.setData("description", e.target.value)
                        }
                    />
                </div>
                <div className="md:col-span-2">
                    <FileField
                        label="Bukti Pengeluaran"
                        required
                        error={expenseForm.errors.proof}
                        onChange={(file) => expenseForm.setData("proof", file)}
                        hint="Nota, kuitansi, atau bukti pembayaran. Maksimal 5 MB."
                    />
                </div>
            </ModalForm>

            <ModalForm
                open={Boolean(review)}
                onClose={() => setReview(null)}
                onSubmit={approve}
                title="Setujui Pengisian Dana"
                description={
                    review ? `${review.number} · ${rupiah(review.amount)}` : ""
                }
                actions={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setReview(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={approvalForm.processing}
                        >
                            <CheckCircle2 size={17} /> Setujui & Tambah Saldo
                        </Button>
                    </>
                }
            >
                <FileField
                    label="Bukti Transfer / Penarikan"
                    required
                    error={approvalForm.errors.approval_proof}
                    onChange={(file) =>
                        approvalForm.setData("approval_proof", file)
                    }
                />
                <Textarea
                    label="Catatan Approval"
                    value={approvalForm.data.approval_notes}
                    error={approvalForm.errors.approval_notes}
                    onChange={(e) =>
                        approvalForm.setData("approval_notes", e.target.value)
                    }
                />
            </ModalForm>

            <ModalForm
                open={Boolean(rejecting)}
                onClose={() => setRejecting(null)}
                onSubmit={reject}
                title="Tolak Permohonan"
                description={rejecting?.number || ""}
                actions={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRejecting(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            className="bg-red-600 text-white hover:bg-red-700"
                            disabled={rejectForm.processing}
                        >
                            <XCircle size={17} /> Tolak
                        </Button>
                    </>
                }
            >
                <Textarea
                    label="Alasan Penolakan"
                    required
                    value={rejectForm.data.rejection_notes}
                    error={rejectForm.errors.rejection_notes}
                    onChange={(e) =>
                        rejectForm.setData("rejection_notes", e.target.value)
                    }
                />
            </ModalForm>
        </>
    );
}

const Summary = ({ label, value, icon: Icon, tone }) => (
    <article className="rounded-2xl border border-silver-deep/60 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
        <div className="flex items-center justify-between">
            <p className="text-sm font-bold text-ink-soft">{label}</p>
            <Icon
                size={20}
                className={
                    tone === "emerald"
                        ? "text-emerald-600"
                        : tone === "red"
                          ? "text-red-600"
                          : "text-ink"
                }
            />
        </div>
        <p className="mt-3 text-2xl font-black">{rupiah(value)}</p>
    </article>
);

Index.layout = (page) => <AdminLayout title="Kas Kecil">{page}</AdminLayout>;
