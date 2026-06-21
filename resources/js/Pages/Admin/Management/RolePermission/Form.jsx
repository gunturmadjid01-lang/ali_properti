import { Save, XCircle } from 'lucide-react';
import { Button, ModalForm } from '../../../../Components/UI';
import FieldRenderer from '../Components/FieldRenderer';

export default function RolePermissionForm({ open, title, fields, options, form, selected, onSubmit, onClose }) {
    return (
        <ModalForm
            open={open}
            onClose={onClose}
            title={selected ? `Edit ${title}` : `Tambah ${title}`}
            description="Atur nama role dan permission yang boleh diakses."
            onSubmit={onSubmit}
            contentClassName="md:grid-cols-2"
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
            {fields.map((field) => (
                <div className={field.full ? 'md:col-span-2' : ''} key={field.name}>
                    <FieldRenderer field={field} value={form.data[field.name]} error={form.errors[field.name]} options={options} onChange={form.setData} />
                </div>
            ))}
        </ModalForm>
    );
}
