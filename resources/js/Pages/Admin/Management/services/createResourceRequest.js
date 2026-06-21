import { router } from '@inertiajs/react';

export function createResourceRequest() {
    return {
        submit({ form, baseUrl, selected, onSuccess, onError }) {
            const options = {
                preserveScroll: true,
                onSuccess,
                onError,
            };

            if (selected?.id) {
                form.put(`${baseUrl}/${selected.id}`, options);
                return;
            }

            form.post(baseUrl, options);
        },

        destroy({ baseUrl, row, label }) {
            if (!window.confirm(`Hapus data ${label ?? row.name ?? row.nama_post ?? row.nama_dokument ?? row.nama_dokumen ?? row.nama_cabang ?? row.nama_perusahaan ?? 'ini'}?`)) {
                return;
            }

            router.delete(`${baseUrl}/${row.id}`, {
                preserveScroll: true,
            });
        },

        lock({ baseUrl, row }) {
            if (!window.confirm('Lock data ini? Setelah locked, data tidak bisa diedit atau dihapus sebelum owner membuka lock.')) {
                return;
            }

            router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
        },

        unlock({ baseUrl, row }) {
            if (!window.confirm('Buka lock data ini? Data akan kembali menjadi draft dan bisa diedit oleh penginput.')) {
                return;
            }

            router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
        },

        search({ baseUrl, search, searchKey = 'search' }) {
            router.get(
                baseUrl,
                { [searchKey]: search },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        },
    };
}
