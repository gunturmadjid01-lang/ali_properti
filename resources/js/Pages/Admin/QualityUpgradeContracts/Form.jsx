import { Head, Link, useForm } from "@inertiajs/react";
import { Plus, Save, Trash2 } from "lucide-react";
import { useMemo } from "react";
import { Button, CurrencyInput, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const blankItem = () => ({ quality_upgrade_catalog_id: "", item_code: "", name: "", specification: "", location: "", volume: "1", unit: "paket", unit_price: "", discount: "0", estimated_material_cost: "0", estimated_labor_cost: "0", estimated_other_cost: "0" });
const blankInstallment = (date = "") => ({ description: "Termin 1", due_date: date, amount: "" });
const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;

export default function Form({ title, row = {}, method, actionUrl, indexUrl, options }) {
    const snapshotInstallments = row.payment_snapshot?.installments ?? [];
    const form = useForm({
        contract_date: row.contract_date?.slice?.(0, 10) ?? new Date().toISOString().slice(0, 10),
        costumer_id: String(row.costumer_id ?? ""),
        detail_rumah_id: String(row.detail_rumah_id ?? ""),
        spr_id: String(row.spr_id ?? ""),
        company_id: String(row.company_id ?? ""),
        master_bank_id: String(row.master_bank_id ?? ""),
        payment_method: row.payment_method ?? "cash",
        discount: String(row.discount ?? 0),
        tax_amount: String(row.tax_amount ?? 0),
        down_payment: String(row.down_payment ?? 0),
        planned_start_date: row.planned_start_date?.slice?.(0, 10) ?? "",
        planned_finish_date: row.planned_finish_date?.slice?.(0, 10) ?? "",
        warranty_days: String(row.warranty_days ?? 30),
        scope_notes: row.scope_notes ?? "",
        terms: row.terms ?? "",
        items: row.items?.length ? row.items.map((item) => ({ ...item, quality_upgrade_catalog_id: String(item.quality_upgrade_catalog_id ?? ""), volume: String(item.volume), unit_price: String(item.unit_price), discount: String(item.discount ?? 0), estimated_material_cost: String(item.estimated_material_cost ?? 0), estimated_labor_cost: String(item.estimated_labor_cost ?? 0), estimated_other_cost: String(item.estimated_other_cost ?? 0) })) : [blankItem()],
        installments: snapshotInstallments.length ? snapshotInstallments.map((item) => ({ ...item, amount: String(item.amount) })) : [blankInstallment(row.contract_date?.slice?.(0, 10) ?? new Date().toISOString().slice(0, 10))],
    });
    const subtotal = useMemo(() => form.data.items.reduce((sum, item) => sum + Math.max(0, Number(item.volume || 0) * Number(item.unit_price || 0) - Number(item.discount || 0)), 0), [form.data.items]);
    const total = Math.max(0, subtotal - Number(form.data.discount || 0) + Number(form.data.tax_amount || 0));
    const installmentTotal = form.data.installments.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const banks = (options.banks ?? []).filter((bank) => !form.data.company_id || bank.company_id === String(form.data.company_id));
    const updateItem = (index, key, value) => form.setData("items", form.data.items.map((item, i) => i === index ? { ...item, [key]: value } : item));
    const updateInstallment = (index, key, value) => form.setData("installments", form.data.installments.map((item, i) => i === index ? { ...item, [key]: value } : item));
    const distribute = (methodValue, count = form.data.installments.length || 1) => {
        const size = methodValue === "cash" ? 1 : Math.max(1, count);
        const cents = Math.round(total * 100);
        const base = Math.floor(cents / size);
        form.setData({ ...form.data, payment_method: methodValue, installments: Array.from({ length: size }, (_, index) => ({ description: methodValue === "cash" ? "Pelunasan Cash" : `Cicilan ${index + 1}`, due_date: new Date(new Date(form.data.contract_date).setMonth(new Date(form.data.contract_date).getMonth() + index)).toISOString().slice(0, 10), amount: String((base + (index < cents % size ? 1 : 0)) / 100) })) });
    };
    const setDownPayment = (value) => {
        const count = Math.max(1, form.data.installments.length);
        const requested = Math.min(total, Math.max(0, Number(value || 0)));
        const dp = count === 1 && requested > 0 ? total : requested;
        const remainder = Math.max(0, total - dp);
        const otherCount = Math.max(1, count - 1);
        const schedules = form.data.installments.map((item, index) => index === 0
            ? { ...item, description: dp > 0 ? "Uang Muka / DP" : item.description, amount: String(dp > 0 ? dp : (count === 1 ? total : 0)) }
            : { ...item, amount: String(remainder / otherCount) });
        form.setData({ ...form.data, down_payment: String(dp), installments: schedules });
    };

    const submit = (event) => {
        event.preventDefault();
        method === "put" ? form.put(actionUrl) : form.post(actionUrl);
    };

    return <><Head title={title} /><form onSubmit={submit} className="grid gap-5">
        <header className="rounded-2xl border bg-white/90 p-6 dark:bg-white/5"><h1 className="text-2xl font-black">{title}</h1><p className="mt-2 text-sm text-ink-soft">Kontrak mandiri berbasis customer dan unit. SPR hanya referensi opsional; perusahaan penerima menentukan rekening dan pembukuan.</p></header>
        <section className="grid gap-4 rounded-2xl border bg-white/90 p-5 md:grid-cols-2 dark:bg-white/5">
            <Field label="Tanggal Kontrak"><Input type="date" value={form.data.contract_date} onChange={(e) => form.setData("contract_date", e.target.value)} /></Field>
            <Field label="Referensi SPR (opsional)"><Select value={form.data.spr_id} options={options.sprs} empty="Tanpa SPR / kontrak mandiri" onChange={(value) => { const spr = options.sprs.find((item) => item.value === value); form.setData({ ...form.data, spr_id: value, costumer_id: spr?.customer_id ?? form.data.costumer_id, detail_rumah_id: spr?.unit_id ?? form.data.detail_rumah_id, company_id: spr?.company_id ?? form.data.company_id, master_bank_id: "" }); }} /></Field>
            <Field label="Customer"><Select value={form.data.costumer_id} options={options.customers} empty="Pilih customer" onChange={(value) => form.setData("costumer_id", value)} /></Field>
            <Field label="Unit Bangunan"><Select value={form.data.detail_rumah_id} options={(options.units ?? []).filter((unit) => !form.data.costumer_id || !unit.customer_id || unit.customer_id === form.data.costumer_id)} empty="Pilih unit" onChange={(value) => { const unit = options.units.find((item) => item.value === value); form.setData({ ...form.data, detail_rumah_id: value, company_id: form.data.company_id || unit?.company_id || "", master_bank_id: "" }); }} /></Field>
            <Field label="Perusahaan Pelaksana & Penerima"><Select value={form.data.company_id} options={options.companies} empty="Pilih perusahaan" onChange={(value) => form.setData({ ...form.data, company_id: value, master_bank_id: "" })} /></Field>
            <Field label="Rekening Tujuan"><Select value={form.data.master_bank_id} options={banks} empty="Pilih rekening perusahaan" onChange={(value) => form.setData("master_bank_id", value)} /></Field>
            <Field label="Rencana Mulai"><Input type="date" value={form.data.planned_start_date} onChange={(e) => form.setData("planned_start_date", e.target.value)} /></Field>
            <Field label="Rencana Selesai"><Input type="date" value={form.data.planned_finish_date} onChange={(e) => form.setData("planned_finish_date", e.target.value)} /></Field>
            <Field label="Garansi (hari)"><Input type="number" min="0" value={form.data.warranty_days} onChange={(e) => form.setData("warranty_days", e.target.value)} /></Field>
        </section>
        <section className="rounded-2xl border bg-white/90 p-5 dark:bg-white/5"><div className="flex items-center justify-between"><div><h2 className="text-lg font-black">Rincian Penambahan Mutu</h2><p className="text-xs text-ink-soft">Kanopi, dapur, pagar, carport, atau banyak item sekaligus.</p></div><Button type="button" size="sm" onClick={() => form.setData("items", [...form.data.items, blankItem()])}><Plus size={15}/> Item</Button></div>
            <div className="mt-4 grid gap-4">{form.data.items.map((item, index) => <div className="grid gap-3 rounded-xl border p-4 lg:grid-cols-6" key={index}>
                <Field label="Paket standar" className="lg:col-span-2"><Select value={item.quality_upgrade_catalog_id} options={options.catalogs} empty="Input manual" onChange={(value) => { const catalog = options.catalogs.find((option) => option.value === value); form.setData("items", form.data.items.map((current, i) => i === index ? { ...current, quality_upgrade_catalog_id: value, name: catalog?.name ?? current.name, specification: catalog?.specification ?? current.specification, unit: catalog?.unit ?? current.unit, unit_price: catalog?.price ?? current.unit_price, estimated_material_cost: catalog?.material_cost ?? current.estimated_material_cost, estimated_labor_cost: catalog?.labor_cost ?? current.estimated_labor_cost, estimated_other_cost: catalog?.other_cost ?? current.estimated_other_cost } : current)); }} /></Field>
                <Field label="Nama Pekerjaan" className="lg:col-span-2"><Input value={item.name} onChange={(e) => updateItem(index, "name", e.target.value)} /></Field>
                <Field label="Lokasi"><Input value={item.location} onChange={(e) => updateItem(index, "location", e.target.value)} /></Field>
                <Field label="Volume"><Input type="number" step="0.0001" value={item.volume} onChange={(e) => updateItem(index, "volume", e.target.value)} /></Field>
                <Field label="Satuan"><Input value={item.unit} onChange={(e) => updateItem(index, "unit", e.target.value)} /></Field>
                <button type="button" className="self-end rounded-lg border p-3 text-rose-600" disabled={form.data.items.length === 1} onClick={() => form.setData("items", form.data.items.filter((_, i) => i !== index))}><Trash2 size={16}/></button>
                <Field label="Spesifikasi" className="lg:col-span-3"><Input value={item.specification} onChange={(e) => updateItem(index, "specification", e.target.value)} /></Field>
                <Field label="Harga Satuan" className="lg:col-span-2"><CurrencyInput value={item.unit_price} onChange={(value) => updateItem(index, "unit_price", value)} /></Field>
                <div className="self-end rounded-lg bg-slate-100 p-3 text-right font-black dark:bg-white/10">{money(Number(item.volume || 0) * Number(item.unit_price || 0) - Number(item.discount || 0))}</div>
                <Field label="Estimasi Material" className="lg:col-span-2"><CurrencyInput value={item.estimated_material_cost} onChange={(value) => updateItem(index, "estimated_material_cost", value)} /></Field>
                <Field label="Estimasi Upah" className="lg:col-span-2"><CurrencyInput value={item.estimated_labor_cost} onChange={(value) => updateItem(index, "estimated_labor_cost", value)} /></Field>
                <Field label="Estimasi Lainnya" className="lg:col-span-2"><CurrencyInput value={item.estimated_other_cost} onChange={(value) => updateItem(index, "estimated_other_cost", value)} /></Field>
            </div>)}</div>
        </section>
        <section className="grid gap-4 rounded-2xl border bg-white/90 p-5 md:grid-cols-3 dark:bg-white/5">
            <Field label="Diskon Kontrak"><CurrencyInput value={form.data.discount} onChange={(value) => form.setData("discount", value)} /></Field>
            <Field label="Pajak"><CurrencyInput value={form.data.tax_amount} onChange={(value) => form.setData("tax_amount", value)} /></Field>
            <div className="rounded-xl bg-emerald-50 p-4 text-right dark:bg-emerald-400/10"><p className="text-xs font-bold">NILAI KONTRAK</p><p className="text-xl font-black">{money(total)}</p></div>
            <Field label="Metode Pembayaran"><Select value={form.data.payment_method} options={[{ value: "cash", label: "Cash / Lunas" }, { value: "installment", label: "Cicilan / Termin" }]} onChange={(value) => distribute(value, value === "cash" ? 1 : 2)} /></Field>
            <Field label="DP (jadwal pertama)"><CurrencyInput value={form.data.down_payment} onChange={setDownPayment} /></Field>
            {form.data.payment_method === "installment" && <Field label="Jumlah Cicilan"><Input type="number" min="2" value={form.data.installments.length} onChange={(e) => distribute("installment", Number(e.target.value || 2))} /></Field>}
            <div className={`rounded-xl p-4 ${Math.abs(total - installmentTotal) < 0.01 ? "bg-emerald-50" : "bg-rose-50"}`}><p className="text-xs font-bold">TOTAL JADWAL</p><p className="font-black">{money(installmentTotal)}</p><button type="button" className="mt-1 text-xs underline" onClick={() => distribute(form.data.payment_method, form.data.installments.length)}>Sesuaikan dengan nilai kontrak</button></div>
            <div className="md:col-span-3 grid gap-3">{form.data.installments.map((item, index) => <div className="grid gap-2 md:grid-cols-[1fr_180px_240px]" key={index}><Input value={item.description} onChange={(e) => updateInstallment(index, "description", e.target.value)} /><Input type="date" value={item.due_date} onChange={(e) => updateInstallment(index, "due_date", e.target.value)} /><CurrencyInput value={item.amount} onChange={(value) => updateInstallment(index, "amount", value)} /></div>)}</div>
            <Field label="Ruang Lingkup / Catatan" className="md:col-span-3"><textarea className="w-full rounded-lg border p-3" rows="3" value={form.data.scope_notes} onChange={(e) => form.setData("scope_notes", e.target.value)} /></Field>
            <Field label="Ketentuan Kontrak" className="md:col-span-3"><textarea className="w-full rounded-lg border p-3" rows="4" value={form.data.terms} onChange={(e) => form.setData("terms", e.target.value)} /></Field>
        </section>
        {Object.keys(form.errors).length > 0 && <div className="rounded-xl bg-rose-50 p-4 text-sm text-rose-700">{Object.entries(form.errors).map(([key, value]) => <p key={key}>{value}</p>)}</div>}
        <div className="flex justify-end gap-2"><Button as={Link} href={indexUrl} variant="outline">Batal</Button><Button type="submit" disabled={form.processing || Math.abs(total - installmentTotal) > 0.01}><Save size={16}/> Simpan Draft</Button></div>
    </form></>;
}

function Field({ label, children, className = "" }) { return <label className={`grid gap-1 text-sm font-bold ${className}`}><span>{label}</span>{children}</label>; }
function Select({ value, options = [], empty, onChange }) { return <select className="w-full rounded-lg border p-3" value={value} onChange={(e) => onChange(e.target.value)}><option value="">{empty}</option>{options.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select>; }
Form.layout = (page) => <AdminLayout title={page.props.title}>{page}</AdminLayout>;
