export default function AuditCell({
    createdBy,
    updatedBy,
    createdLabel = 'Dibuat',
    updatedLabel = 'Diubah',
}) {
    return (
        <div className="grid gap-1 text-xs leading-5">
            <span><span className="font-bold">{createdLabel}:</span> {createdBy ?? '-'}</span>
            <span><span className="font-bold">{updatedLabel}:</span> {updatedBy ?? '-'}</span>
        </div>
    );
}
