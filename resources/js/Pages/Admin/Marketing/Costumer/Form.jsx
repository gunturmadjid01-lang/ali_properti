import { Save, XCircle } from 'lucide-react';
import { Button, ModalForm } from '../../../../Components/UI';
import FieldRenderer from '../../Management/Components/FieldRenderer';

const groups = [
    {
        key: 'profile',
        title: 'Profile Customer',
        description: 'Data identitas utama dan kontak customer.',
    },
    {
        key: 'pekerjaan',
        title: 'Pekerjaan Customer',
        description: 'Data pekerjaan, penghasilan, dan perusahaan customer.',
    },
    {
        key: 'pasangan',
        title: 'Pasangan Customer',
        description: 'Data pasangan jika customer sudah menikah.',
    },
];

export default function CostumerForm({ open, title, fields, options, form, selected, onSubmit, onClose }) {
    return (
        <ModalForm
            open={open}
            onClose={onClose}
            title={selected ? `Edit ${title}` : `Tambah ${title}`}
            description="Input data customer sesuai migration terbaru."
            onSubmit={onSubmit}
            contentClassName="gap-5"
            size="xl"
            actions={
                <>
                    <Button variant="outline" type="button" onClick={onClose}>
                        <XCircle size={17} /> Batal
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        <Save size={17} /> {form.processing ? 'Menyimpan...' : 'Simpan'}
                    </Button>
                </>
            }
        >
            {groups.map((group) => {
                const groupFields = fields.filter((field) => field.group === group.key);

                if (groupFields.length === 0) {
                    return null;
                }

                return (
                    <section className="rounded-lg border border-silver-deep/70 bg-silver-soft/55 p-4 dark:border-white/10 dark:bg-white/6" key={group.key}>
                        <div className="mb-4">
                            <h3 className="text-base font-extrabold text-ink dark:text-white">{group.title}</h3>
                            <p className="mt-1 text-xs font-bold leading-5 text-ink-soft dark:text-white/50">{group.description}</p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            {groupFields.map((field) => (
                                <div className={field.full ? 'md:col-span-2' : ''} key={field.name}>
                                    <FieldRenderer field={field} value={form.data[field.name]} error={form.errors[field.name]} options={options} onChange={form.setData} />
                                </div>
                            ))}
                        </div>
                    </section>
                );
            })}
        </ModalForm>
    );
}
