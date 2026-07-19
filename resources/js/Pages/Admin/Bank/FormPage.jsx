import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Save } from "lucide-react";
import { Button, CurrencyInput, Dropdown, Input, Textarea } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const today = new Date().toISOString().slice(0, 10);
const definitions = {
    branch: [
        ["bank_kredit_id", "Bank", "select", "banks"], ["branch_code", "Kode Cabang"], ["branch_name", "Nama Cabang"],
        ["city", "Kota"], ["address", "Alamat", "textarea"], ["pic_name", "Nama PIC"], ["pic_position", "Jabatan PIC"],
        ["phone", "Nomor Telepon"], ["email", "Email", "email"], ["status", "Status", "select", "status"],
    ],
    partnership: [
        ["bank_kredit_id", "Bank", "select", "banks"], ["bank_branch_id", "Cabang Opsional", "branch"],
        ["perumahan_id", "Perumahan", "select", "housings"], ["agreement_number", "Nomor Perjanjian"],
        ["agreement_name", "Nama Perjanjian"], ["effective_from", "Tanggal Berlaku", "date"],
        ["effective_until", "Tanggal Berakhir", "date"], ["status", "Status", "select", "status"],
        ["notes", "Catatan", "textarea"],
    ],
    product: [
        ["bank_kredit_id", "Bank", "select", "banks"], ["bank_branch_id", "Cabang Opsional", "branch"],
        ["product_code", "Kode Produk"], ["product_name", "Nama Produk"], ["product_type", "Tipe Produk"],
        ["subsidy_type", "Jenis Subsidi", "select", "subsidy"], ["scheme_type", "Skema", "select", "scheme"],
        ["minimum_ceiling", "Minimum Plafon", "currency"], ["maximum_ceiling", "Maksimum Plafon", "currency"],
        ["minimum_down_payment", "Minimum DP", "currency"], ["maximum_tenor_months", "Maksimum Tenor (bulan)", "number"],
        ["indicative_interest_margin", "Bunga/Margin Indikatif (%)", "number"],
        ["provision_fee", "Biaya Provisi", "currency"], ["administration_fee", "Biaya Administrasi", "currency"],
        ["appraisal_fee", "Biaya Appraisal", "currency"], ["insurance_fee", "Biaya Asuransi", "currency"],
        ["notary_fee", "Biaya Notaris", "currency"], ["disbursement_method", "Metode Pencairan", "select", "disbursement"],
        ["estimated_sla_days", "Estimasi SLA (hari)", "number"], ["effective_from", "Tanggal Berlaku", "date"],
        ["effective_until", "Tanggal Berakhir", "date"], ["status", "Status", "select", "status"],
        ["notes", "Catatan", "textarea"],
    ],
};
const choices = {
    status: [{ value: "aktif", label: "Aktif" }, { value: "nonaktif", label: "Nonaktif" }],
    subsidy: [{ value: "subsidi", label: "Subsidi" }, { value: "non_subsidi", label: "Non-Subsidi" }],
    scheme: [{ value: "konvensional", label: "Konvensional" }, { value: "syariah", label: "Syariah" }],
    disbursement: [
        { value: "sekaligus", label: "Sekaligus" }, { value: "bertahap", label: "Bertahap" },
        { value: "berdasarkan_progress", label: "Berdasarkan Kemajuan" },
        { value: "sesuai_perjanjian", label: "Sesuai Perjanjian Kerja Sama" },
    ],
};
const defaults = {
    status: "aktif", product_type: "KPR", subsidy_type: "non_subsidi", scheme_type: "konvensional",
    maximum_tenor_months: 240, disbursement_method: "sekaligus", effective_from: today,
};

export default function FormPage({ title, kind, baseUrl, actionUrl, method, row, options = {} }) {
    const initial = Object.fromEntries(definitions[kind].map(([name]) => [name, row?.[name] ?? defaults[name] ?? ""]));
    const form = useForm(initial);
    const branches = (options.branches ?? []).filter((item) => item.bank_id === String(form.data.bank_kredit_id));
    const submit = (event) => {
        event.preventDefault();
        form[method](actionUrl);
    };
    const renderField = ([name, label, type = "text", source]) => {
        if (type === "select" || type === "branch") {
            const list = type === "branch"
                ? [{ value: "", label: "Semua Cabang" }, ...branches]
                : (options[source] ?? choices[source] ?? []);
            return <label className="grid gap-2 text-sm font-bold" key={name}>{label}
                <Dropdown value={String(form.data[name] ?? "")} options={list} error={form.errors[name]}
                    onChange={(value) => name === "bank_kredit_id"
                        ? form.setData({ ...form.data, bank_kredit_id: value, bank_branch_id: "" })
                        : form.setData(name, value)} />
            </label>;
        }
        if (type === "textarea") return <Textarea key={name} label={label} value={form.data[name] ?? ""} error={form.errors[name]} onChange={(e) => form.setData(name, e.target.value)} />;
        if (type === "currency") return <CurrencyInput key={name} label={label} value={form.data[name] ?? ""} error={form.errors[name]} onChange={(value) => form.setData(name, value)} />;
        return <Input key={name} label={label} type={type} step={type === "number" ? "any" : undefined} min={type === "number" ? 0 : undefined}
            value={form.data[name] ?? ""} error={form.errors[name]} onChange={(e) => form.setData(name, e.target.value)} />;
    };
    return <><Head title={title} /><div className="grid gap-6">
        <header className="flex flex-col gap-4 rounded-xl border bg-white/85 p-5 md:flex-row md:items-center md:justify-between">
            <div><p className="text-xs font-black uppercase text-ink-soft">Master Kredit Bank</p><h1 className="text-2xl font-black">{title}</h1>
                <p className="text-sm text-ink-soft">Lengkapi data pada halaman khusus ini, lalu simpan untuk kembali ke daftar.</p></div>
            <Button as={Link} href={baseUrl} variant="outline"><ArrowLeft size={16} /> Kembali</Button>
        </header>
        <form onSubmit={submit} className="grid gap-5 rounded-xl border bg-white/85 p-5">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{definitions[kind].map(renderField)}</div>
            <div className="flex justify-end gap-2 border-t pt-5"><Button as={Link} href={baseUrl} variant="outline">Batal</Button>
                <Button type="submit" disabled={form.processing}><Save size={16} /> Simpan</Button></div>
        </form>
    </div></>;
}
FormPage.layout = (page) => <AdminLayout title={page?.props?.title}>{page}</AdminLayout>;
