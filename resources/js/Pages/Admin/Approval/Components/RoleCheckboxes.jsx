export default function RoleCheckboxes({ roles = [], value = [], onChange }) {
    const values = Array.isArray(value) ? value.map(String) : [];

    const toggle = (roleId) => {
        const normalized = String(roleId);
        const next = values.includes(normalized)
            ? values.filter((item) => item !== normalized)
            : [...values, normalized];

        onChange(next);
    };

    return (
        <div className="grid gap-2 md:grid-cols-2">
            {roles.map((role) => (
                <label className="flex min-h-9 items-center gap-2 rounded-md px-2 text-xs font-bold text-ink-soft hover:bg-silver dark:text-white/70 dark:hover:bg-white/10" key={role.value}>
                    <input
                        checked={values.includes(String(role.value))}
                        className="h-4 w-4 rounded border-silver-deep text-ink-soft"
                        type="checkbox"
                        onChange={() => toggle(role.value)}
                    />
                    <span>{role.label}</span>
                </label>
            ))}
        </div>
    );
}

