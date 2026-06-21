import { Head, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { Button } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';
import RoleCheckboxes from './Components/RoleCheckboxes';

function Settings({ title, baseUrl, settings, actions, roles }) {
    const form = useForm({ settings });

    const updateSetting = (index, key, value) => {
        form.setData('settings', form.data.settings.map((setting, settingIndex) => (
            settingIndex === index ? { ...setting, [key]: value } : setting
        )));
    };

    const submit = (event) => {
        event.preventDefault();
        form.put(baseUrl, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <form className="grid gap-6" onSubmit={submit}>
                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">Approval</p>
                            <h2 className="mt-1 text-xl font-extrabold">{title}</h2>
                            <p className="mt-1 max-w-2xl text-sm leading-6 text-ink-soft dark:text-white/60">
                                Atur modul dan aksi mana yang harus masuk approval, lalu pilih role yang boleh melakukan approval.
                            </p>
                        </div>
                        <Button type="submit" disabled={form.processing}>
                            <Save size={17} /> {form.processing ? 'Menyimpan...' : 'Simpan Setting'}
                        </Button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    <th className="px-4 py-3 font-extrabold">Management Data</th>
                                    <th className="px-4 py-3 font-extrabold">Request</th>
                                    <th className="px-4 py-3 font-extrabold">Perlu Approval</th>
                                    <th className="min-w-[320px] px-4 py-3 font-extrabold">Role Approval</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {form.data.settings.map((setting, index) => (
                                    <tr key={`${setting.module_key}-${setting.action}`}>
                                        <td className="px-4 py-3 font-extrabold text-ink/80 dark:text-white/75">{setting.module_label}</td>
                                        <td className="px-4 py-3 font-semibold text-ink-soft dark:text-white/58">{actions[setting.action]}</td>
                                        <td className="px-4 py-3">
                                            <label className="inline-flex items-center gap-3 rounded-lg bg-silver-soft px-3 py-2 font-bold text-ink-soft dark:bg-white/8 dark:text-white/65">
                                                <input
                                                    checked={Boolean(setting.requires_approval)}
                                                    className="h-4 w-4 rounded border-silver-deep text-ink-soft"
                                                    type="checkbox"
                                                    onChange={(event) => updateSetting(index, 'requires_approval', event.target.checked)}
                                                />
                                                Aktif
                                            </label>
                                        </td>
                                        <td className="px-4 py-3">
                                            <RoleCheckboxes
                                                roles={roles}
                                                value={setting.approver_role_ids}
                                                onChange={(value) => updateSetting(index, 'approver_role_ids', value)}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </form>
        </>
    );
}

Settings.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Admin'}>{page}</AdminLayout>;

export default Settings;

