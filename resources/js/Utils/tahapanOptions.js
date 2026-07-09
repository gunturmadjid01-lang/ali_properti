export function scopedTahapanOptions(options = [], perumahanId = '', detailRumahId = '') {
    const projectId = String(perumahanId ?? '');
    const unitId = String(detailRumahId ?? '');

    return options.filter((option) => {
        if (unitId) {
            return String(option.detail_rumah_id ?? '') === unitId;
        }

        return !option.detail_rumah_id
            && (!projectId || String(option.perumahan_id ?? '') === projectId);
    });
}
