import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    Camera,
    KeyRound,
    LogOut,
    Mail,
    Phone,
    Save,
    ShieldCheck,
    Trash2,
    UserCircle2,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { Button, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

function Avatar({ name, source, size = "h-24 w-24" }) {
    if (source) {
        return (
            <img
                className={`${size} rounded-3xl border border-white/80 object-cover shadow-soft dark:border-white/10`}
                src={source}
                alt={name}
            />
        );
    }

    return (
        <div
            className={`${size} grid place-items-center rounded-3xl border border-white/80 bg-gradient-to-br from-champagne to-gold text-2xl font-black text-gold-deep shadow-soft dark:border-white/10`}
        >
            {name?.charAt(0)?.toUpperCase() ?? "U"}
        </div>
    );
}

export default function Index({ title, user }) {
    const fileInput = useRef(null);
    const [preview, setPreview] = useState(user.avatar_url ?? null);
    const profileForm = useForm({
        name: user.name ?? "",
        email: user.email ?? "",
        phone: user.phone ?? "",
        employee_number: user.employee_number ?? "",
        job_title: user.job_title ?? "",
        join_date: user.join_date ?? "",
        tax_number: user.tax_number ?? "",
        bpjs_health_number: user.bpjs_health_number ?? "",
        bpjs_employment_number: user.bpjs_employment_number ?? "",
        payroll_bank_name: user.payroll_bank_name ?? "",
        payroll_bank_account: user.payroll_bank_account ?? "",
        payroll_bank_holder: user.payroll_bank_holder ?? "",
        avatar: null,
        remove_avatar: false,
    });
    const passwordForm = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
    });

    useEffect(() => {
        if (!profileForm.data.avatar) {
            setPreview(profileForm.data.remove_avatar ? null : user.avatar_url);
            return undefined;
        }

        const objectUrl = URL.createObjectURL(profileForm.data.avatar);
        setPreview(objectUrl);
        return () => URL.revokeObjectURL(objectUrl);
    }, [
        profileForm.data.avatar,
        profileForm.data.remove_avatar,
        user.avatar_url,
    ]);

    const updateProfile = (event) => {
        event.preventDefault();
        profileForm.transform((data) => ({ ...data, _method: "put" }));
        profileForm.post("/admin/profile", {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                const cleanData = {
                    ...profileForm.data,
                    avatar: null,
                    remove_avatar: false,
                };
                profileForm.setDefaults(cleanData);
                profileForm.setData(cleanData);
                if (fileInput.current) fileInput.current.value = "";
            },
        });
    };

    const updatePassword = (event) => {
        event.preventDefault();
        passwordForm.put("/admin/profile/password", {
            preserveScroll: true,
            onSuccess: () => {
                const emptyPassword = {
                    current_password: "",
                    password: "",
                    password_confirmation: "",
                };
                passwordForm.setDefaults(emptyPassword);
                passwordForm.setData(emptyPassword);
                passwordForm.clearErrors();
            },
        });
    };

    const removeAvatar = () => {
        profileForm.setData((data) => ({
            ...data,
            avatar: null,
            remove_avatar: true,
        }));
        if (fileInput.current) fileInput.current.value = "";
    };

    const logout = () => router.post("/logout", {}, { preserveScroll: true });

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="flex flex-col justify-between gap-5 rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-5">
                        <Avatar name={profileForm.data.name} source={preview} />
                        <div className="min-w-0">
                            <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-ink-soft">
                                Pengaturan Profil Petugas
                            </p>
                            <h1 className="mt-1 truncate text-3xl font-extrabold text-ink dark:text-white">
                                {profileForm.data.name || user.name}
                            </h1>
                            <p className="mt-2 text-sm text-ink-soft dark:text-white/60">
                                {user.roles?.join(", ") || "Petugas"}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            as={Link}
                            href="/admin/dashboard"
                            type="button"
                            variant="outline"
                        >
                            Kembali
                        </Button>
                        <Button type="button" variant="ghost" onClick={logout}>
                            <LogOut size={17} /> Logout
                        </Button>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
                    <form
                        className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8"
                        onSubmit={updateProfile}
                    >
                        <div className="flex items-start gap-3">
                            <UserCircle2
                                className="mt-1 text-gold-deep"
                                size={22}
                            />
                            <div>
                                <h2 className="text-2xl font-extrabold text-ink dark:text-white">
                                    Informasi Profil
                                </h2>
                                <p className="mt-1 text-sm leading-6 text-ink-soft dark:text-white/60">
                                    Informasi ini digunakan sebagai identitas
                                    petugas pada dashboard, laporan, dan
                                    percakapan internal.
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 rounded-3xl border border-silver-deep/60 bg-silver-soft p-5 dark:border-white/10 dark:bg-white/6">
                            <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
                                <Avatar
                                    name={profileForm.data.name}
                                    source={preview}
                                    size="h-20 w-20"
                                />
                                <div className="grid flex-1 gap-3">
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                fileInput.current?.click()
                                            }
                                        >
                                            <Camera size={17} /> Pilih Foto
                                        </Button>
                                        {(preview || user.avatar_url) && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={removeAvatar}
                                            >
                                                <Trash2 size={17} /> Hapus Foto
                                            </Button>
                                        )}
                                    </div>
                                    <input
                                        ref={fileInput}
                                        className="hidden"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={(event) => {
                                            profileForm.setData(
                                                "avatar",
                                                event.target.files?.[0] ?? null,
                                            );
                                            profileForm.setData(
                                                "remove_avatar",
                                                false,
                                            );
                                        }}
                                    />
                                    <p className="text-xs font-semibold text-ink-soft dark:text-white/50">
                                        JPG, PNG, atau WEBP. Maksimal 2 MB.
                                    </p>
                                    {profileForm.errors.avatar && (
                                        <p className="text-xs font-bold text-red-600 dark:text-red-300">
                                            {profileForm.errors.avatar}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 grid gap-4 md:grid-cols-2">
                            <Input
                                label="Nama Lengkap"
                                required
                                value={profileForm.data.name}
                                error={profileForm.errors.name}
                                icon={<UserCircle2 size={17} />}
                                onChange={(event) =>
                                    profileForm.setData(
                                        "name",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                label="Nomor Telepon"
                                required
                                value={profileForm.data.phone}
                                error={profileForm.errors.phone}
                                icon={<Phone size={17} />}
                                onChange={(event) =>
                                    profileForm.setData(
                                        "phone",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                className="md:col-span-2"
                                label="Email Login"
                                type="email"
                                required
                                value={profileForm.data.email}
                                error={profileForm.errors.email}
                                icon={<Mail size={17} />}
                                onChange={(event) =>
                                    profileForm.setData(
                                        "email",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                label="NIP / Kode Pegawai"
                                value={profileForm.data.employee_number}
                                error={profileForm.errors.employee_number}
                                onChange={(event) =>
                                    profileForm.setData(
                                        "employee_number",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                label="Jabatan"
                                value={profileForm.data.job_title}
                                disabled
                                readOnly
                                title="Jabatan hanya dapat diubah oleh pengelola data pegawai."
                                inputClassName="cursor-not-allowed bg-silver/80 text-ink-soft opacity-75 dark:bg-white/5"
                            />
                            <Input
                                label="Tanggal Masuk"
                                type="date"
                                value={profileForm.data.join_date}
                                disabled
                                readOnly
                                title="Tanggal masuk hanya dapat diubah oleh pengelola data pegawai."
                                inputClassName="cursor-not-allowed bg-silver/80 text-ink-soft opacity-75 dark:bg-white/5"
                            />
                            <Input
                                label="NPWP"
                                value={profileForm.data.tax_number}
                                error={profileForm.errors.tax_number}
                                onChange={(event) =>
                                    profileForm.setData(
                                        "tax_number",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                label="BPJS Kesehatan"
                                value={profileForm.data.bpjs_health_number}
                                error={profileForm.errors.bpjs_health_number}
                                onChange={(event) =>
                                    profileForm.setData(
                                        "bpjs_health_number",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                label="BPJS Ketenagakerjaan"
                                value={profileForm.data.bpjs_employment_number}
                                error={
                                    profileForm.errors.bpjs_employment_number
                                }
                                onChange={(event) =>
                                    profileForm.setData(
                                        "bpjs_employment_number",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                label="Bank Penggajian"
                                value={profileForm.data.payroll_bank_name}
                                error={profileForm.errors.payroll_bank_name}
                                onChange={(event) =>
                                    profileForm.setData(
                                        "payroll_bank_name",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                label="Nomor Rekening Gaji"
                                value={profileForm.data.payroll_bank_account}
                                error={profileForm.errors.payroll_bank_account}
                                onChange={(event) =>
                                    profileForm.setData(
                                        "payroll_bank_account",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                className="md:col-span-2"
                                label="Nama Pemilik Rekening"
                                value={profileForm.data.payroll_bank_holder}
                                error={profileForm.errors.payroll_bank_holder}
                                onChange={(event) =>
                                    profileForm.setData(
                                        "payroll_bank_holder",
                                        event.target.value,
                                    )
                                }
                            />
                        </div>

                        <div className="mt-6 flex justify-end">
                            <Button
                                type="submit"
                                disabled={profileForm.processing}
                            >
                                <Save size={17} />
                                {profileForm.processing
                                    ? "Menyimpan..."
                                    : "Simpan Profil"}
                            </Button>
                        </div>
                    </form>

                    <div className="grid content-start gap-6">
                        <form
                            className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8"
                            onSubmit={updatePassword}
                        >
                            <div className="flex items-start gap-3">
                                <KeyRound
                                    className="mt-1 text-gold-deep"
                                    size={22}
                                />
                                <div>
                                    <h2 className="text-xl font-extrabold text-ink dark:text-white">
                                        Ganti Password
                                    </h2>
                                    <p className="mt-1 text-sm leading-6 text-ink-soft dark:text-white/60">
                                        Gunakan minimal 8 karakter yang
                                        mengandung huruf dan angka.
                                    </p>
                                </div>
                            </div>
                            <div className="mt-5 grid gap-4">
                                <Input
                                    label="Password Saat Ini"
                                    required
                                    type="password"
                                    autoComplete="current-password"
                                    value={passwordForm.data.current_password}
                                    error={passwordForm.errors.current_password}
                                    onChange={(event) =>
                                        passwordForm.setData(
                                            "current_password",
                                            event.target.value,
                                        )
                                    }
                                />
                                <Input
                                    label="Password Baru"
                                    required
                                    type="password"
                                    autoComplete="new-password"
                                    value={passwordForm.data.password}
                                    error={passwordForm.errors.password}
                                    onChange={(event) =>
                                        passwordForm.setData(
                                            "password",
                                            event.target.value,
                                        )
                                    }
                                />
                                <Input
                                    label="Konfirmasi Password Baru"
                                    required
                                    type="password"
                                    autoComplete="new-password"
                                    value={
                                        passwordForm.data.password_confirmation
                                    }
                                    error={
                                        passwordForm.errors
                                            .password_confirmation
                                    }
                                    onChange={(event) =>
                                        passwordForm.setData(
                                            "password_confirmation",
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                className="mt-5 w-full"
                                type="submit"
                                disabled={passwordForm.processing}
                            >
                                <KeyRound size={17} />
                                {passwordForm.processing
                                    ? "Memperbarui..."
                                    : "Perbarui Password"}
                            </Button>
                        </form>

                        <section className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                            <div className="flex items-center gap-3">
                                <ShieldCheck
                                    className="text-emerald-600"
                                    size={21}
                                />
                                <h2 className="text-lg font-extrabold">
                                    Akses Petugas
                                </h2>
                            </div>
                            <p className="mt-3 text-sm leading-6 text-ink-soft dark:text-white/60">
                                Role dan penugasan hanya dapat diubah melalui
                                manajemen user oleh admin.
                            </p>
                            <div className="mt-4 flex flex-wrap gap-2">
                                {user.roles?.map((role) => (
                                    <span
                                        className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-extrabold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200"
                                        key={role}
                                    >
                                        {role}
                                    </span>
                                ))}
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Pengaturan Profil"}>
        {page}
    </AdminLayout>
);
