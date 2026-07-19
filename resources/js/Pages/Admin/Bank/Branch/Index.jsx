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
    branch_code: "",
    branch_name: "",
    address: "",
    city: "",
    pic_name: "",
    pic_position: "",
    phone: "",
    email: "",
    status: "aktif",
};
export default function Index(props) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState(null);
    const f = useForm(empty);
    const show = (r = null) => {
        setSelected(r);
        f.setData(
            r
                ? { ...empty, ...r, bank_kredit_id: String(r.bank_kredit_id) }
                : empty,
        );
        f.clearErrors();
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
            description="Daftar kantor cabang dan PIC tiap bank."
            onCreate={() => show()}
            onEdit={show}
            columns={[
                { key: "bank_name", label: "Bank" },
                { key: "branch_code", label: "Kode Cabang" },
                {
                    key: "branch_name",
                    label: "Nama Cabang",
                    render: (r) => <b>{r.branch_name}</b>,
                },
                { key: "city", label: "Kota" },
                { key: "pic_name", label: "PIC" },
                { key: "phone", label: "Telepon" },
                { key: "status", label: "Status" },
            ]}
        >
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title={selected ? "Ubah Cabang Bank" : "Tambah Cabang Bank"}
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
                <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
                    <div className="grid gap-2">
                        <b>Bank</b>
                        <Dropdown
                            value={f.data.bank_kredit_id}
                            options={props.banks}
                            onChange={(v) => f.setData("bank_kredit_id", v)}
                        />
                    </div>
                    <Input
                        label="Kode Cabang"
                        value={f.data.branch_code}
                        error={f.errors.branch_code}
                        onChange={(e) =>
                            f.setData("branch_code", e.target.value)
                        }
                    />
                    <Input
                        label="Nama Cabang"
                        value={f.data.branch_name}
                        error={f.errors.branch_name}
                        onChange={(e) =>
                            f.setData("branch_name", e.target.value)
                        }
                    />
                    <Input
                        label="Kota"
                        value={f.data.city}
                        onChange={(e) => f.setData("city", e.target.value)}
                    />
                    <Textarea
                        label="Alamat"
                        value={f.data.address}
                        onChange={(e) => f.setData("address", e.target.value)}
                    />
                    <div />
                    <Input
                        label="Nama PIC"
                        value={f.data.pic_name}
                        onChange={(e) => f.setData("pic_name", e.target.value)}
                    />
                    <Input
                        label="Jabatan PIC"
                        value={f.data.pic_position}
                        onChange={(e) =>
                            f.setData("pic_position", e.target.value)
                        }
                    />
                    <Input
                        label="Nomor Telepon"
                        value={f.data.phone}
                        onChange={(e) => f.setData("phone", e.target.value)}
                    />
                    <Input
                        label="Email"
                        type="email"
                        value={f.data.email}
                        error={f.errors.email}
                        onChange={(e) => f.setData("email", e.target.value)}
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
                </form>
            </Modal>
        </BankPageShell>
    );
}
Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Cabang Bank"}>
        {page}
    </AdminLayout>
);
