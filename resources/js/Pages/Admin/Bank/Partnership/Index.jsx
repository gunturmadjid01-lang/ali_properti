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
    perumahan_id: "",
    agreement_number: "",
    agreement_name: "",
    effective_from: new Date().toISOString().slice(0, 10),
    effective_until: "",
    status: "aktif",
    notes: "",
};
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
                      perumahan_id: String(r.perumahan_id),
                      effective_from: String(r.effective_from).slice(0, 10),
                      effective_until: String(r.effective_until ?? "").slice(
                          0,
                          10,
                      ),
                  }
                : empty,
        );
        setOpen(true);
    };
    const submit = (e) => {
        e.preventDefault();
        selected
            ? f.put(`${props.baseUrl}/${selected.id}`, {
                  onSuccess: () => setOpen(false),
              })
            : f.post(props.baseUrl, { onSuccess: () => setOpen(false) });
    };
    return (
        <BankPageShell
            {...props}
            description="Perjanjian bank dengan tiap perumahan dan periode berlakunya."
            onCreate={() => show()}
            onEdit={show}
            columns={[
                { key: "agreement_number", label: "Nomor" },
                {
                    key: "agreement_name",
                    label: "Kerja Sama",
                    render: (r) => <b>{r.agreement_name}</b>,
                },
                { key: "bank_name", label: "Bank" },
                { key: "housing_name", label: "Perumahan" },
                { key: "effective_from", label: "Berlaku" },
                {
                    key: "current_version",
                    label: "Versi",
                    render: (r) => `v${r.current_version}`,
                },
                { key: "status", label: "Status" },
            ]}
        >
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title={selected ? "Ubah Kerja Sama" : "Tambah Kerja Sama"}
                footer={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button onClick={submit}>Simpan</Button>
                    </>
                }
            >
                <form className="grid gap-4 md:grid-cols-2">
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
                                ...props.branches.filter(
                                    (x) =>
                                        x.bank_id ===
                                        String(f.data.bank_kredit_id),
                                ),
                            ]}
                            onChange={(v) => f.setData("bank_branch_id", v)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <b>Perumahan</b>
                        <Dropdown
                            value={f.data.perumahan_id}
                            options={props.housings}
                            onChange={(v) => f.setData("perumahan_id", v)}
                        />
                    </div>
                    <Input
                        label="Nomor Perjanjian"
                        value={f.data.agreement_number}
                        error={f.errors.agreement_number}
                        onChange={(e) =>
                            f.setData("agreement_number", e.target.value)
                        }
                    />
                    <Input
                        label="Nama Perjanjian"
                        value={f.data.agreement_name}
                        onChange={(e) =>
                            f.setData("agreement_name", e.target.value)
                        }
                    />
                    <div />
                    <Input
                        label="Tanggal Berlaku"
                        type="date"
                        value={f.data.effective_from}
                        onChange={(e) =>
                            f.setData("effective_from", e.target.value)
                        }
                    />
                    <Input
                        label="Tanggal Berakhir"
                        type="date"
                        value={f.data.effective_until}
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
                    <Textarea
                        label="Catatan"
                        value={f.data.notes}
                        onChange={(e) => f.setData("notes", e.target.value)}
                    />
                </form>
            </Modal>
        </BankPageShell>
    );
}
Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Kerja Sama Bank dan Perumahan"}>
        {page}
    </AdminLayout>
);
