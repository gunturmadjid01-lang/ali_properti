const compactRupiah = (value) =>
    `Rp ${new Intl.NumberFormat("id-ID", {
        notation: "compact",
        maximumFractionDigits: 1,
    }).format(Number(value || 0))}`;

const palette = {
    emerald: "#10b981",
    amber: "#f59e0b",
    red: "#ef4444",
    blue: "#3b82f6",
    violet: "#8b5cf6",
};

export function ChartLegend({ series = [] }) {
    return (
        <div className="flex flex-wrap gap-x-5 gap-y-2 text-xs font-bold text-ink-soft">
            {series.map((item) => (
                <span className="inline-flex items-center gap-2" key={item.key}>
                    <i
                        className="h-2.5 w-2.5 rounded-full"
                        style={{ backgroundColor: item.color }}
                    />
                    {item.label}
                </span>
            ))}
        </div>
    );
}

export function FinanceTrendChart({
    title,
    subtitle,
    items = [],
    series = [],
    emptyText = "Belum ada data pada filter ini.",
}) {
    const rows = items.map((item) => ({
        ...item,
        ...Object.fromEntries(
            series.map((line) => [line.key, Number(item[line.key] || 0)]),
        ),
    }));
    const values = rows.flatMap((row) =>
        series.map((line) => Number(row[line.key] || 0)),
    );
    const max = Math.max(0, ...values);
    const min = Math.min(0, ...values);
    const span = Math.max(1, max - min);
    const width = 920;
    const height = 280;
    const left = 70;
    const right = 24;
    const top = 22;
    const bottom = 48;
    const plotWidth = width - left - right;
    const plotHeight = height - top - bottom;
    const x = (index) =>
        left +
        (rows.length <= 1
            ? plotWidth / 2
            : (index / (rows.length - 1)) * plotWidth);
    const y = (value) => top + ((max - Number(value || 0)) / span) * plotHeight;
    const ticks = Array.from(
        { length: 5 },
        (_, index) => max - (span * index) / 4,
    );

    return (
        <section className="rounded-xl border border-white/80 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-white/7">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-black">{title}</h2>
                    {subtitle && (
                        <p className="mt-1 text-xs text-ink-soft">{subtitle}</p>
                    )}
                </div>
                <ChartLegend series={series} />
            </div>
            {!rows.length || !series.length ? (
                <div className="mt-4 grid min-h-36 place-items-center rounded-lg border border-dashed border-silver-deep/70 text-sm font-bold text-ink-soft dark:border-white/15">
                    {emptyText}
                </div>
            ) : (
                <div className="mt-4 overflow-x-auto">
                    <svg
                        aria-label={title}
                        className="min-w-[680px] w-full"
                        role="img"
                        viewBox={`0 0 ${width} ${height}`}
                    >
                        {ticks.map((tick, index) => (
                            <g key={index}>
                                <line
                                    stroke="currentColor"
                                    className="text-slate-200 dark:text-white/10"
                                    x1={left}
                                    x2={width - right}
                                    y1={y(tick)}
                                    y2={y(tick)}
                                />
                                <text
                                    className="fill-slate-500 text-[10px]"
                                    textAnchor="end"
                                    x={left - 10}
                                    y={y(tick) + 4}
                                >
                                    {compactRupiah(tick)}
                                </text>
                            </g>
                        ))}
                        {series.map((line, seriesIndex) => {
                            const points = rows
                                .map(
                                    (row, index) =>
                                        `${x(index)},${y(row[line.key])}`,
                                )
                                .join(" ");
                            return (
                                <g key={line.key}>
                                    {line.area && rows.length > 1 && (
                                        <polygon
                                            fill={line.color}
                                            opacity="0.1"
                                            points={`${x(0)},${y(0)} ${points} ${x(rows.length - 1)},${y(0)}`}
                                        />
                                    )}
                                    <polyline
                                        fill="none"
                                        points={points}
                                        stroke={line.color}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={seriesIndex === 0 ? 4 : 3}
                                    />
                                    {rows.map((row, index) => (
                                        <circle
                                            cx={x(index)}
                                            cy={y(row[line.key])}
                                            fill={line.color}
                                            key={`${line.key}-${index}`}
                                            r="4"
                                        >
                                            <title>{`${row.label}: ${line.label} ${compactRupiah(row[line.key])}`}</title>
                                        </circle>
                                    ))}
                                </g>
                            );
                        })}
                        {rows.map((row, index) => (
                            <text
                                className="fill-slate-500 text-[10px]"
                                key={`${row.label}-${index}`}
                                textAnchor="middle"
                                x={x(index)}
                                y={height - 16}
                            >
                                {String(row.label).slice(0, 12)}
                            </text>
                        ))}
                    </svg>
                </div>
            )}
        </section>
    );
}

export function FinanceChart({
    title,
    subtitle,
    items = [],
    emptyText = "Belum ada data pada filter ini.",
    maxItems = 12,
    primaryLabel = "Nilai",
    secondaryLabel = null,
    valueFormatter = compactRupiah,
}) {
    const normalized = items
        .map((item) => ({
            ...item,
            value: Number(item.value || 0),
            secondary: Number(item.secondary || 0),
        }))
        .sort(
            (a, b) =>
                Math.max(b.value, b.secondary) - Math.max(a.value, a.secondary),
        );
    const visible = normalized.slice(0, maxItems);
    const max = Math.max(
        ...visible.flatMap((item) => [item.value, item.secondary]),
        0,
    );

    return (
        <section className="rounded-xl border border-white/80 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-white/7">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-black">{title}</h2>
                    {subtitle && (
                        <p className="mt-1 text-xs text-ink-soft">{subtitle}</p>
                    )}
                </div>
                <ChartLegend
                    series={[
                        {
                            key: "primary",
                            label: primaryLabel,
                            color: palette.emerald,
                        },
                        ...(secondaryLabel
                            ? [
                                  {
                                      key: "secondary",
                                      label: secondaryLabel,
                                      color: palette.amber,
                                  },
                              ]
                            : []),
                    ]}
                />
            </div>
            {!visible.length || max <= 0 ? (
                <div className="mt-4 grid min-h-36 place-items-center rounded-lg border border-dashed border-silver-deep/70 text-sm font-bold text-ink-soft dark:border-white/15">
                    {emptyText}
                </div>
            ) : (
                <div className="mt-5 grid gap-4">
                    {visible.map((item, index) => (
                        <div key={`${item.label}-${index}`}>
                            <div className="mb-1.5 flex items-center justify-between gap-3 text-xs">
                                <span className="truncate font-bold">
                                    {item.label}
                                </span>
                                <span className="shrink-0 font-black">
                                    {valueFormatter(item.value)}
                                    {secondaryLabel &&
                                        ` / ${valueFormatter(item.secondary)}`}
                                </span>
                            </div>
                            <div className="grid gap-1">
                                <div className="h-2.5 overflow-hidden rounded-full bg-silver-soft dark:bg-white/10">
                                    <div
                                        className={`h-full rounded-full ${item.tone ?? "bg-emerald-500"}`}
                                        style={{
                                            width: `${Math.max(2, (item.value / max) * 100)}%`,
                                        }}
                                    />
                                </div>
                                {secondaryLabel && (
                                    <div className="h-2.5 overflow-hidden rounded-full bg-silver-soft dark:bg-white/10">
                                        <div
                                            className={`h-full rounded-full ${item.secondaryTone ?? "bg-amber-500"}`}
                                            style={{
                                                width: `${Math.max(2, (item.secondary / max) * 100)}%`,
                                            }}
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                    {normalized.length > visible.length && (
                        <p className="text-xs font-semibold text-ink-soft">
                            Menampilkan {visible.length} nilai terbesar dari{" "}
                            {normalized.length} data. Detail lengkap tetap
                            tersedia pada tabel.
                        </p>
                    )}
                </div>
            )}
        </section>
    );
}

export { palette as financeChartPalette };
