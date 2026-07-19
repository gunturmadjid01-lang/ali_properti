import { Head, useForm } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, CheckCircle2, Save } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button, Dropdown, Modal } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function Settings({ title, baseUrl, settings, actions, roles, flash, approvalCategories = [] }) {
    const form = useForm({ settings });
    const [validationModalOpen, setValidationModalOpen] = useState(false);
    const [responseModal, setResponseModal] = useState(null);
    const [activeCategory, setActiveCategory] = useState(approvalCategories[0]?.key ?? '');
    const validationErrors = Object.entries(form.errors ?? {}).filter(([, message]) => Boolean(message));
    const groupedSettings = useMemo(() => {
        const settingsByCategory = form.data.settings.reduce((groups, setting, index) => {
            const key = setting.category_key ?? 'other';
            groups[key] = [...(groups[key] ?? []), { setting, index }];
            return groups;
        }, {});

        return approvalCategories
            .filter((category) => settingsByCategory[category.key]?.length)
            .map((category) => ({ ...category, items: settingsByCategory[category.key] }));
    }, [approvalCategories, form.data.settings]);
    const activeSettings = groupedSettings.find((group) => group.key === activeCategory) ?? groupedSettings[0];

    const settingErrors = (settingIndex) => validationErrors.filter(([key]) => key.startsWith(`settings.${settingIndex}.`));
    const stepError = (settingIndex, stepIndex) => validationErrors.find(([key]) => (
        key.startsWith(`settings.${settingIndex}.approval_steps.${stepIndex}.role_ids`)
    ))?.[1];
    const validationLabel = (key) => {
        const match = key.match(/^settings\.(\d+)\.approval_steps\.(\d+)/);
        if (!match) return 'Pengaturan Persetujuan';

        const setting = form.data.settings[Number(match[1])];
        return `${setting?.module_label ?? 'Modul'} — Tahap ${Number(match[2]) + 1}`;
    };

    useEffect(() => {
        const message = flash?.error ?? flash?.success;
        if (!message) return;

        setResponseModal({
            type: flash?.error ? 'error' : 'success',
            message,
        });
    }, [flash?.id, flash?.error, flash?.success]);

    const updateSetting = (index, key, value) => {
        form.setData('settings', form.data.settings.map((setting, settingIndex) => (
            settingIndex === index ? { ...setting, [key]: value } : setting
        )));
    };

    const setStageCount = (index, count) => {
        const current = form.data.settings[index];
        const existing = current.approval_steps ?? [];
        const steps = Array.from({ length: count }, (_, stepIndex) => ({
            step: stepIndex + 1,
            role_ids: (existing[stepIndex]?.role_ids ?? []).slice(0, 1),
        }));
        form.setData('settings', form.data.settings.map((setting, settingIndex) => (
            settingIndex === index ? { ...setting, approval_stages: count, requires_approval: count > 0, approval_steps: steps } : setting
        )));
    };

    const setStepRole = (settingIndex, stepIndex, roleId) => {
        const setting = form.data.settings[settingIndex];
        const steps = [...(setting.approval_steps ?? [])];
        steps[stepIndex] = { step: stepIndex + 1, role_ids: roleId ? [roleId] : [] };
        updateSetting(settingIndex, 'approval_steps', steps);
        form.clearErrors(
            `settings.${settingIndex}.approval_steps.${stepIndex}.role_ids`,
            `settings.${settingIndex}.approval_steps.${stepIndex}.role_ids.0`,
        );
    };

    const submit = (event) => {
        event.preventDefault();
        form.put(baseUrl, {
            preserveScroll: true,
            onError: () => setValidationModalOpen(true),
            onSuccess: (page) => {
                form.clearErrors();
                setValidationModalOpen(false);
                const error = page?.props?.flash?.error;
                const success = page?.props?.flash?.success;
                setResponseModal({
                    type: error ? 'error' : 'success',
                    message: error ?? success ?? 'Pengaturan approval berhasil disimpan.',
                });
            },
        });
    };

    return (
        <>
            <Head title={title} />
            <Modal
                open={Boolean(responseModal)}
                onClose={() => setResponseModal(null)}
                title={responseModal?.type === 'error' ? 'Pengaturan Gagal Disimpan' : 'Pengaturan Berhasil Disimpan'}
                size="sm"
                footer={<Button type="button" onClick={() => setResponseModal(null)}>Tutup</Button>}
            >
                <div className={`flex items-start gap-4 rounded-lg p-4 ${responseModal?.type === 'error' ? 'bg-red-50 text-red-800 dark:bg-red-500/10 dark:text-red-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200'}`}>
                    {responseModal?.type === 'error' ? <AlertCircle className="mt-0.5 shrink-0" size={24} /> : <CheckCircle2 className="mt-0.5 shrink-0" size={24} />}
                    <div>
                        <p className="font-extrabold">{responseModal?.type === 'error' ? 'Perubahan tidak diterapkan.' : 'Perubahan sudah diterapkan.'}</p>
                        <p className="mt-1 text-sm leading-6">{responseModal?.message}</p>
                    </div>
                </div>
            </Modal>
            <Modal
                open={validationModalOpen && validationErrors.length > 0}
                onClose={() => setValidationModalOpen(false)}
                title="Pengaturan approval belum dapat disimpan"
                size="md"
                footer={<Button type="button" onClick={() => setValidationModalOpen(false)}>Tutup dan Perbaiki</Button>}
            >
                <div className="grid gap-4">
                    <div className="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                        <AlertTriangle className="mt-0.5 shrink-0" size={20} />
                        <div>
                            <p className="font-extrabold">Ada {validationErrors.length} bagian yang harus diperbaiki.</p>
                            <p className="mt-1 text-sm">Periksa modul dan tahap yang ditandai merah pada tabel.</p>
                        </div>
                    </div>
                    <div className="grid max-h-80 gap-2 overflow-y-auto">
                        {validationErrors.map(([key, message]) => (
                            <div key={key} className="rounded-lg border border-red-200 px-4 py-3 dark:border-red-500/20">
                                <p className="text-sm font-extrabold text-red-800 dark:text-red-200">{validationLabel(key)}</p>
                                <p className="mt-1 text-sm text-red-700 dark:text-red-300">{String(message)}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </Modal>
            <form className="grid gap-6" onSubmit={submit}>
                {validationErrors.length > 0 && (
                    <section className="flex flex-col gap-3 rounded-lg border border-red-300 bg-red-50 p-4 text-red-800 shadow-soft dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="mt-0.5 shrink-0" size={20} />
                            <div><p className="font-extrabold">Pengaturan belum tersimpan.</p><p className="text-sm">Ada {validationErrors.length} tahap yang belum valid.</p></div>
                        </div>
                        <Button type="button" variant="outline" onClick={() => setValidationModalOpen(true)}>Lihat Rincian Error</Button>
                    </section>
                )}
                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">Persetujuan</p>
                            <h2 className="mt-1 text-xl font-extrabold">{title}</h2>
                            <p className="mt-1 max-w-2xl text-sm leading-6 text-ink-soft dark:text-white/60">
                                Pilih auto approve atau 1–3 tahap approval, lalu tentukan role penanggung jawab pada setiap tahap.
                            </p>
                            <p className="mt-2 max-w-2xl text-xs font-semibold leading-5 text-amber-700 dark:text-amber-300">
                                Pengajuan yang belum pernah direview akan mengikuti pengaturan baru. Pengajuan yang tahap review-nya sudah berjalan tetap memakai konfigurasi saat diajukan.
                            </p>
                        </div>
                        <Button type="submit" disabled={form.processing}>
                            <Save size={17} /> {form.processing ? 'Menyimpan...' : 'Simpan Pengaturan'}
                        </Button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                        <h3 className="text-base font-extrabold text-ink dark:text-white">Kelola Alur Persetujuan</h3>
                        <p className="mt-1 text-sm text-ink-soft dark:text-white/55">Pilih kategori, kemudian atur alur setiap modul di dalamnya.</p>
                    </div>

                    <div className="grid gap-5 p-4 lg:grid-cols-[230px_minmax(0,1fr)] lg:p-5">
                    <aside className="min-w-0">
                        <p className="mb-2 px-1 text-[11px] font-extrabold uppercase tracking-[0.14em] text-ink-soft dark:text-white/45">Kategori Modul</p>
                        <div className="flex gap-2 overflow-x-auto pb-2 lg:block lg:overflow-hidden lg:rounded-lg lg:border lg:border-silver-deep/70 lg:pb-0 dark:lg:border-white/10">
                        {groupedSettings.map((group) => {
                            const errorCount = group.items.filter(({ index }) => settingErrors(index).length > 0).length;

                            return (
                                <button
                                    className={`flex min-h-11 shrink-0 items-center justify-between gap-3 rounded-lg border px-3.5 text-left text-sm font-extrabold transition lg:w-full lg:rounded-none lg:border-0 lg:border-b lg:border-silver-deep/60 lg:last:border-b-0 dark:lg:border-white/10 ${activeSettings?.key === group.key ? 'border-ink bg-ink text-white shadow-sm dark:border-white dark:bg-white dark:text-graphite' : 'border-silver-deep/70 bg-white/70 text-ink-soft hover:bg-silver dark:border-white/10 dark:bg-white/5 dark:text-white/70 dark:hover:bg-white/10'}`}
                                    type="button"
                                    key={group.key}
                                    onClick={() => setActiveCategory(group.key)}
                                >
                                    <span>{group.label}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-[11px] ${errorCount > 0 ? 'bg-red-500 text-white' : activeSettings?.key === group.key ? 'bg-white/15 dark:bg-graphite/10' : 'bg-silver-soft dark:bg-white/10'}`}>
                                        {errorCount > 0 ? `${errorCount} error` : group.items.length}
                                    </span>
                                </button>
                            );
                        })}
                        </div>
                    </aside>

                    <div className="min-w-0 overflow-hidden rounded-lg border border-silver-deep/70 bg-white/55 dark:border-white/10 dark:bg-white/[0.02]">
                        <div className="flex items-center justify-between gap-4 border-b border-silver-deep/60 bg-silver-soft/70 px-5 py-4 dark:border-white/10 dark:bg-white/5">
                            <div>
                                <h3 className="text-base font-extrabold text-ink dark:text-white">{activeSettings?.label}</h3>
                                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">Atur {activeSettings?.items.length ?? 0} modul dalam kategori ini</p>
                            </div>
                            <span className="shrink-0 rounded-full bg-white px-3 py-1 text-xs font-extrabold text-ink-soft shadow-sm dark:bg-white/10 dark:text-white/65">{activeSettings?.items.length ?? 0} modul</span>
                        </div>
                        <div className="overflow-x-auto">
                        <table className="min-w-[920px] w-full table-fixed divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <colgroup>
                                <col className="w-[25%]" />
                                <col className="w-[20%]" />
                                <col className="w-[55%]" />
                            </colgroup>
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    <th className="px-4 py-3 font-extrabold">Manajemen Data</th>
                                    <th className="px-4 py-3 font-extrabold">Request</th>
                                    <th className="min-w-[420px] px-4 py-3 font-extrabold">Atur Persetujuan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {(activeSettings?.items ?? []).map(({ setting, index }) => (
                                    <tr className={`align-top transition-colors hover:bg-silver-soft/40 dark:hover:bg-white/[0.03] ${settingErrors(index).length > 0 ? 'bg-red-50/70 dark:bg-red-500/5' : ''}`} key={`${setting.module_key}-${setting.action}`}>
                                        <td className="px-4 py-4"><p className="font-extrabold leading-5 text-ink/80 dark:text-white/75">{setting.module_label}</p><p className="mt-1 font-mono text-[10px] text-ink-soft/70 dark:text-white/35">{setting.module_key}</p></td>
                                        <td className="px-4 py-4 font-semibold leading-5 text-ink-soft dark:text-white/58">{actions[setting.action]}</td>
                                        <td className="px-4 py-4">
                                            <div className="grid gap-3">
                                                <Dropdown searchable={false} value={String(setting.approval_stages ?? 0)} options={[{value:'0',label:'Disetujui otomatis'},{value:'1',label:'Persetujuan 1 tahap'},{value:'2',label:'Persetujuan 2 tahap'},{value:'3',label:'Persetujuan 3 tahap'}]} onChange={(value) => setStageCount(index, Number(value))} />
                                                {(setting.approval_steps ?? []).length === 0 ? (
                                                    <span className="inline-flex w-fit rounded-full bg-emerald-50 px-3 py-1.5 font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Langsung aktif tanpa approval</span>
                                                ) : (
                                                    <div className="grid gap-3">
                                                    {setting.approval_steps.map((step, stepIndex) => (
                                                        <div className={`grid gap-2 rounded-lg border p-3 sm:grid-cols-[70px_minmax(0,1fr)] sm:items-center ${stepError(index, stepIndex) ? 'border-red-400 bg-red-50 dark:border-red-400/50 dark:bg-red-500/10' : 'border-silver-deep/60 bg-white/60 dark:border-white/10 dark:bg-white/[0.03]'}`} key={step.step}>
                                                            <span className="font-extrabold">Tahap {step.step}</span>
                                                            <label className="grid gap-1.5 font-extrabold">
                                                                <Dropdown value={step.role_ids?.[0] ?? ''} options={roles} label="Cari dan pilih role pemberi persetujuan" onChange={(value) => setStepRole(index, stepIndex, value)} />
                                                                {stepError(index, stepIndex) && <span className="text-xs font-bold text-red-600 dark:text-red-300">{stepError(index, stepIndex)}</span>}
                                                            </label>
                                                        </div>
                                                    ))}
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        </div>
                    </div>
                    </div>
                </section>
            </form>
        </>
    );
}

Settings.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Admin'}>{page}</AdminLayout>;

export default Settings;
