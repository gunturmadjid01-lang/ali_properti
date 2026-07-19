import { useForm } from "@inertiajs/react";
import { useState } from "react";
import {
    Button,
    Dropdown,
    Input,
    Modal,
    Textarea,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
import BankPageShell from "../components/BankPageShell";
const empty = {
    bank_kredit_id: "",
    bank_branch_id: "",
    product_code: "",
    product_name: "",
    product_type: "KPR",
    subsidy_type: "non_subsidi",
    scheme_type: "konvensional",
    minimum_ceiling: 0,
    maximum_ceiling: 0,
    minimum_down_payment: 0,
    maximum_tenor_months: 240,
    indicative_interest_margin: 0,
    provision_fee: 0,
    administration_fee: 0,
    appraisal_fee: 0,
    insurance_fee: 0,
    notary_fee: 0,
    disbursement_method: "sekaligus",
    estimated_sla_days: "",
    effective_from: new Date().toISOString().slice(0, 10),
    effective_until: "",
    status: "aktif",
    notes: "",
};
const money = (v) => Number(v ?? 0).toLocaleString("id-ID");
export default function Index(props) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState(null);
    const f = useForm(empty);
    const show = (r = null) => {
        setSelected(r);
        f.setData(
            r
                ? {
                      ...empty,
                      ...r,
                      bank_kredit_id: String(r.bank_kredit_id),
                      bank_branch_id: String(r.bank_branch_id ?? ""),
                      effective_from: String(r.effective_from ?? "").slice(
                          0,
                          10,
                      ),
                      effective_until: String(r.effective_until ?? "").slice(
                          0,
                          10,
                      ),
                  }
                : empty,
        );
        f.clearErrors();
        setOpen(true);
    };
    const submit = (e) => {
        e.preventDefault();
        const opts = { onSuccess: () => setOpen(false) };
        selected
            ? f.put(`${props.baseUrl}/${selected.id}`, opts)
            : f.post(props.baseUrl, opts);
    };
    const branches = props.branches.filter(
        (x) => x.bank_id === String(f.data.bank_kredit_id),
    );
    return (
        <BankPageShell
            {...props}
            description="Ketentuan produk kredit berperiode. Setiap perubahan otomatis menghasilkan versi baru."
            onCreate={() => show()}
            onEdit={show}
            columns={[
                { key: "product_code", label: "Kode" },
                {
                    key: "product_name",
                    label: "Produk",
                    render: (r) => <b>{r.product_name}</b>,
                },
                { key: "bank_name", label: "Bank" },
                { key: "scheme_type", label: "Skema" },
                {
                    key: "maximum_ceiling",
                    label: "Plafon Maks",
                    render: (r) => `Rp ${money(r.maximum_ceiling)}`,
                },
                { key: "maximum_tenor_months", label: "Tenor" },
                {
                    key: "current_version",
                    label: "Versi",
                    render: (r) => `v${r.current_version}`,
                },
                { key: "status", label: "Status" },
            ]}
        >
            <Modal
                size="lg"
                open={open}
                onClose={() => setOpen(false)}
                title={
                    selected
                        ? "Ubah Produk Kredit Bank"
                        : "Tambah Produk Kredit Bank"
                }
                footer={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button onClick={submit} disabled={f.processing}>
                            Simpan
                        </Button>
                    </>
                }
            >
                <form className="grid gap-4 md:grid-cols-3" onSubmit={submit}>
                    <div className="grid gap-2">
                        <b>Bank</b>
                        <Dropdown
                            value={f.data.bank_kredit_id}
                            options={props.banks}
                            onChange={(v) =>
                                f.setData({
                                    ...f.data,
                                    bank_kredit_id: v,
                                    bank_branch_id: "",
                                })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <b>Cabang Opsional</b>
                        <Dropdown
                            value={f.data.bank_branch_id}
                            options={[
                                { value: "", label: "Semua Cabang" },
                                ...branches,
                            ]}
                            onChange={(v) => f.setData("bank_branch_id", v)}
                        />
                    </div>
                    <Input
                        label="Kode Produk"
                        value={f.data.product_code}
                        error={f.errors.product_code}
                        onChange={(e) =>
                            f.setData("product_code", e.target.value)
                        }
                    />
                    <Input
                        label="Nama Produk"
                        value={f.data.product_name}
                        error={f.errors.product_name}
                        onChange={(e) =>
                            f.setData("product_name", e.target.value)
                        }
                    />
                    <Input
                        label="Tipe Produk"
                        value={f.data.product_type}
                        onChange={(e) =>
                            f.setData("product_type", e.target.value)
                        }
                    />
                    <div className="grid gap-2">
                        <b>Subsidi</b>
                        <Dropdown
                            value={f.data.subsidy_type}
                            options={[
                                { value: "subsidi", label: "Subsidi" },
                                { value: "non_subsidi", label: "Non-Subsidi" },
                            ]}
                            onChange={(v) => f.setData("subsidy_type", v)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <b>Skema</b>
                        <Dropdown
                            value={f.data.scheme_type}
                            options={[
                                {
                                    value: "konvensional",
                                    label: "Konvensional",
                                },
                                { value: "syariah", label: "Syariah" },
                            ]}
                            onChange={(v) => f.setData("scheme_type", v)}
                        />
                    </div>
                    <Input
                        label="Minimum Plafon"
                        type="number"
                        min="0"
                        value={f.data.minimum_ceiling}
                        onChange={(e) =>
                            f.setData("minimum_ceiling", e.target.value)
                        }
                    />
                    <Input
                        label="Maksimum Plafon"
                        type="number"
                        min="0"
                        value={f.data.maximum_ceiling}
                        error={f.errors.maximum_ceiling}
                        onChange={(e) =>
                            f.setData("maximum_ceiling", e.target.value)
                        }
                    />
                    <Input
                        label="Minimum DP"
                        type="number"
                        min="0"
                        value={f.data.minimum_down_payment}
                        onChange={(e) =>
                            f.setData("minimum_down_payment", e.target.value)
                        }
                    />
                    <Input
                        label="Maksimum Tenor (bulan)"
                        type="number"
                        min="1"
                        value={f.data.maximum_tenor_months}
                        onChange={(e) =>
                            f.setData("maximum_tenor_months", e.target.value)
                        }
                    />
                    <Input
                        label="Bunga/Margin Indikatif (%)"
                        type="number"
                        min="0"
                        step="0.0001"
                        value={f.data.indicative_interest_margin}
                        onChange={(e) =>
                            f.setData(
                                "indicative_interest_margin",
                                e.target.value,
                            )
                        }
                    />
                    <Input
                        label="Biaya Provisi"
                        type="number"
                        min="0"
                        value={f.data.provision_fee}
                        onChange={(e) =>
                            f.setData("provision_fee", e.target.value)
                        }
                    />
                    <Input
                        label="Biaya Administrasi"
                        type="number"
                        min="0"
                        value={f.data.administration_fee}
                        onChange={(e) =>
                            f.setData("administration_fee", e.target.value)
                        }
                    />
                    <Input
                        label="Biaya Appraisal"
                        type="number"
                        min="0"
                        value={f.data.appraisal_fee}
                        onChange={(e) =>
                            f.setData("appraisal_fee", e.target.value)
                        }
                    />
                    <Input
                        label="Biaya Asuransi"
                        type="number"
                        min="0"
                        value={f.data.insurance_fee}
                        onChange={(e) =>
                            f.setData("insurance_fee", e.target.value)
                        }
                    />
                    <Input
                        label="Biaya Notaris"
                        type="number"
                        min="0"
                        value={f.data.notary_fee}
                        onChange={(e) =>
                            f.setData("notary_fee", e.target.value)
                        }
                    />
                    <div className="grid gap-2">
                        <b>Metode Pencairan</b>
                        <Dropdown
                            value={f.data.disbursement_method}
                            options={[
                                { value: "sekaligus", label: "Sekaligus" },
                                { value: "bertahap", label: "Bertahap" },
                                {
                                    value: "berdasarkan_progress",
                                    label: "Berdasarkan Kemajuan",
                                },
                                {
                                    value: "sesuai_perjanjian",
                                    label: "Sesuai Perjanjian Kerja Sama",
                                },
                            ]}
                            onChange={(v) =>
                                f.setData("disbursement_method", v)
                            }
                        />
                    </div>
                    <Input
                        label="Estimasi SLA (hari)"
                        type="number"
                        min="1"
                        value={f.data.estimated_sla_days}
                        onChange={(e) =>
                            f.setData("estimated_sla_days", e.target.value)
                        }
                    />
                    <Input
                        label="Tanggal Berlaku"
                        type="date"
                        value={f.data.effective_from}
                        error={f.errors.effective_from}
                        onChange={(e) =>
                            f.setData("effective_from", e.target.value)
                        }
                    />
                    <Input
                        label="Tanggal Berakhir"
                        type="date"
                        value={f.data.effective_until}
                        error={f.errors.effective_until}
                        onChange={(e) =>
                            f.setData("effective_until", e.target.value)
                        }
                    />
                    <div className="grid gap-2">
                        <b>Status</b>
                        <Dropdown
                            value={f.data.status}
                            options={[
                                { value: "aktif", label: "Aktif" },
                                { value: "nonaktif", label: "Nonaktif" },
                            ]}
                            onChange={(v) => f.setData("status", v)}
                        />
                    </div>
                    <div className="md:col-span-3">
                        <Textarea
                            label="Catatan"
                            value={f.data.notes}
                            onChange={(e) => f.setData("notes", e.target.value)}
                        />
                    </div>
                </form>
            </Modal>
        </BankPageShell>
    );
}
Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Produk Kredit Bank"}>
        {page}
    </AdminLayout>
);
