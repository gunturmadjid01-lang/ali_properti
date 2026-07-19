import { Head, useForm } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    CheckSquare2,
    FilePlus2,
    Save,
    Search,
    XCircle,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import {
    Button,
    CurrencyInput,
    Dropdown,
    Input,
    Modal,
    Textarea,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

function money(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function SearchPicker({
    id,
    label,
    placeholder,
    rows = [],
    selected,
    onSelect,
    error,
    required = false,
    className = "md:col-span-2",
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState(selected?.label ?? "");
    const rootRef = useRef(null);

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) {
            return rows.slice(0, 10);
        }

        return rows
            .filter(
                (row) =>
                    row.search?.includes(needle) ||
                    row.label?.toLowerCase().includes(needle),
            )
            .slice(0, 10);
    }, [query, rows]);

    useEffect(() => {
        setQuery(selected?.label ?? "");
    }, [selected?.id, selected?.label]);

    useEffect(() => {
        const handlePointerDown = (event) => {
            if (rootRef.current && !rootRef.current.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener("mousedown", handlePointerDown);
        return () =>
            document.removeEventListener("mousedown", handlePointerDown);
    }, []);

    const commitTypedSelection = () => {
        const normalized = query.trim().toLowerCase();
        const exact = rows.find(
            (row) => String(row.label ?? "").trim().toLowerCase() === normalized,
        );
        const candidate = exact ?? (filtered.length === 1 ? filtered[0] : null);

        if (candidate && candidate.is_available !== false) {
            setQuery(candidate.label ?? "");
            setOpen(false);
            onSelect(candidate);
            return true;
        }

        return false;
    };

    useEffect(() => {
        if (selected?.id) return;

        const normalized = query.trim().toLowerCase();
        const exact = rows.find(
            (row) => String(row.label ?? "").trim().toLowerCase() === normalized,
        );
        if (exact && exact.is_available !== false) {
            onSelect(exact);
        }
    }, [query, rows, selected?.id]);

    return (
        <div ref={rootRef} className={`relative grid gap-3 ${className}`}>
            <Input
                label={label}
                id={id}
                required={required}
                icon={<Search size={17} />}
                value={query}
                placeholder={placeholder}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onClick={() => setOpen(true)}
                onKeyDown={(event) => {
                    if (event.key === "Enter") {
                        event.preventDefault();
                        commitTypedSelection();
                    }
                }}
                onBlur={() =>
                    window.setTimeout(() => {
                        commitTypedSelection();
                        setOpen(false);
                    }, 120)
                }
            />
            {selected && (
                <span className="absolute right-3 top-2 rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-extrabold uppercase tracking-wide text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">
                    Terpilih
                </span>
            )}
            {open && (
                <div className="absolute left-0 right-0 top-full z-40 mt-1 grid gap-2 rounded-lg border border-silver-deep/70 bg-white p-2 shadow-soft dark:border-white/10 dark:bg-graphite">
                    <div className="max-h-72 overflow-y-auto">
                        {filtered.map((row) => (
                            <button
                                key={row.id}
                                type="button"
                                className={`w-full rounded-lg px-3 py-2 text-left text-sm font-bold transition ${
                                    selected?.id === row.id
                                        ? "bg-ink text-white"
                                        : row.is_available === false
                                          ? "cursor-not-allowed bg-silver/70 text-ink-soft/70 dark:bg-white/6 dark:text-white/35"
                                          : "text-ink hover:bg-silver dark:text-white dark:hover:bg-white/10"
                                }`}
                                disabled={row.is_available === false}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => {
                                    setQuery(row.label ?? "");
                                    setOpen(false);
                                    onSelect(row);
                                }}
                            >
                                <span className="block">{row.label}</span>
                                {row.is_available === false && (
                                    <span className="mt-1 block text-[11px] font-bold uppercase tracking-[0.12em] text-amber-600 dark:text-amber-300">
                                        {row.availability_label ??
                                            "Tidak tersedia"}
                                    </span>
                                )}
                            </button>
                        ))}
                        {filtered.length === 0 && (
                            <p className="px-3 py-4 text-center text-sm font-bold text-ink-soft dark:text-white/50">
                                Data tidak ditemukan.
                            </p>
                        )}
                    </div>
                </div>
            )}
            {error && (
                <span className="text-xs font-bold text-red-600 dark:text-red-300">
                    {error}
                </span>
            )}
        </div>
    );
}

function SectionTitle({ title, description }) {
    return (
        <div className="space-y-1">
            <h3 className="text-lg font-extrabold text-ink dark:text-white">
                {title}
            </h3>
            {description && (
                <p className="text-sm leading-6 text-ink-soft dark:text-white/60">
                    {description}
                </p>
            )}
        </div>
    );
}

export default function SprForm({
    title,
    description,
    baseUrl,
    submitUrl,
    method = "post",
    mode = "create",
    row = {},
    customers = [],
    units = [],
    reservations = [],
    bankKreditOptions = [],
    bankBranchOptions = [],
    bankCreditProductOptions = [],
    cashInstallmentSchemeOptions = [],
    developerKprProductOptions = [],
    dokumenOptions = [],
    repositoryDocuments = [],
    options = {},
}) {
    const [validationModalOpen, setValidationModalOpen] = useState(false);
    const [requirementsOpen, setRequirementsOpen] = useState(false);
    const yesNoOptions = [
        { value: "1", label: "Ya" },
        { value: "0", label: "Tidak" },
    ];
    const initialPaymentMethod = row?.metode_key ?? "kpr_bank";
    const documentCategoryFor = (paymentMethod) =>
        ({
            bertahap: "cash_bertahap",
            cash_bertahap: "cash_bertahap",
            kpr_bank: "kpr_bank",
            kpr_developer: "kpr_developer",
        })[paymentMethod] ?? null;
    const buildInitialBerkasRows = (
        paymentMethod = initialPaymentMethod,
        customerId = row?.costumer_id ?? "",
    ) => {
        return dokumenOptions
            .filter((dokumen) => dokumen.category === "spr")
            .map((dokumen) => {
                const existing = row?.berkas?.find(
                    (item) =>
                        Number(item.dokumen_costumer_id) ===
                        Number(dokumen.value),
                );
                const repositoryDocument = repositoryDocuments.find(
                    (item) =>
                        Number(item.customer_id) === Number(customerId) &&
                        Number(item.document_type_id) === Number(dokumen.value),
                );

                return {
                    dokumen_costumer_id: dokumen.value,
                    customer_document_id:
                        existing?.customer_document_id ??
                        repositoryDocument?.id ??
                        "",
                    selected: Boolean(existing),
                    file_upload: null,
                    keterangan: existing?.keterangan ?? "",
                    file_name: existing?.nama_file ?? "",
                    dokumen_label: dokumen.label,
                    required: Boolean(dokumen.required),
                    existing_file: existing
                        ? {
                              id: existing.id,
                              nama_file: existing.nama_file,
                              path_file: existing.path_file,
                          }
                        : null,
                };
            });
    };

    const [isMobile, setIsMobile] = useState(false);
    const [berkasRows, setBerkasRows] = useState(
        buildInitialBerkasRows(initialPaymentMethod, row?.costumer_id),
    );
    const form = useForm({
        housing_reservation_id: row?.housing_reservation_id
            ? String(row.housing_reservation_id)
            : "",
        costumer_id: row?.costumer_id ? String(row.costumer_id) : "",
        detail_rumah_id: row?.detail_rumah_id
            ? String(row.detail_rumah_id)
            : "",
        tanggal_spr: row?.tanggal_spr ?? new Date().toISOString().slice(0, 10),
        metode_pembayaran: initialPaymentMethod,
        bank_kredit_id: row?.bank_kredit_id ? String(row.bank_kredit_id) : "",
        bank_branch_id: row?.bank_branch_id ? String(row.bank_branch_id) : "",
        bank_credit_product_id: row?.bank_credit_product_id
            ? String(row.bank_credit_product_id)
            : "",
        cash_installment_scheme_id: row?.cash_installment_scheme_id
            ? String(row.cash_installment_scheme_id)
            : "",
        developer_kpr_product_id: row?.developer_kpr_product_id
            ? String(row.developer_kpr_product_id)
            : "",
        kpr_tenor_bulan: row?.kpr_tenor_bulan
            ? String(row.kpr_tenor_bulan)
            : "",
        kpr_bunga_tahunan: row?.kpr_bunga_tahunan
            ? String(row.kpr_bunga_tahunan)
            : "",
        harga_jual: row?.harga_jual ? String(row.harga_jual) : "",
        booking_fee: row?.booking_fee ? String(row.booking_fee) : "",
        booking_fee_includes_dp: row?.booking_fee_includes_dp ? "1" : "0",
        tanggal_pembayaran_booking_fee:
            row?.tanggal_pembayaran_booking_fee ?? "",
        uang_muka: row?.uang_muka ? String(row.uang_muka) : "",
        uang_muka_jumlah_pembayaran: row?.uang_muka_jumlah_pembayaran
            ? String(row.uang_muka_jumlah_pembayaran)
            : "",
        tanggal_jatuh_tempo_dp: row?.tanggal_jatuh_tempo_dp ?? "",
        nilai_pengajuan_kpr: row?.nilai_pengajuan_kpr
            ? String(row.nilai_pengajuan_kpr)
            : "",
        penambahan_tanah: row?.penambahan_tanah ?? "",
        harga_penambahan_tanah: row?.harga_penambahan_tanah
            ? String(row.harga_penambahan_tanah)
            : "",
        penambahan_lain_lain: row?.penambahan_lain_lain ?? "",
        harga_penambahan_lain_lain: row?.harga_penambahan_lain_lain
            ? String(row.harga_penambahan_lain_lain)
            : "",
        total_penambahan_tanah: row?.total_penambahan_tanah
            ? String(row.total_penambahan_tanah)
            : "",
        total_penambahan_lain_lain: row?.total_penambahan_lain_lain
            ? String(row.total_penambahan_lain_lain)
            : "",
        total_penambahan: row?.total_penambahan
            ? String(row.total_penambahan)
            : "",
        nilai_pengajuan_akhir: row?.nilai_pengajuan_akhir
            ? String(row.nilai_pengajuan_akhir)
            : "",
        jumlah_termin: row?.jumlah_termin ? String(row.jumlah_termin) : "",
        nominal_termin: row?.nominal_termin ? String(row.nominal_termin) : "",
        tanggal_jatuh_tempo_angsuran: row?.tanggal_jatuh_tempo_angsuran ?? "",
        catatan: row?.catatan ?? "",
        berkas: buildInitialBerkasRows(
            initialPaymentMethod,
            row?.costumer_id,
        ).map(({ file_name, dokumen_label, existing_file, ...item }) => item),
    });
    const fieldLabels = {
        costumer_id: "Pelanggan",
        detail_rumah_id: "Unit rumah",
        tanggal_spr: "Tanggal SPR",
        metode_pembayaran: "Metode pembayaran",
        bank_kredit_id: "Bank kredit",
        bank_branch_id: "Cabang bank",
        bank_credit_product_id: "Produk kredit",
        cash_installment_scheme_id: "Skema Tunai Bertahap",
        developer_kpr_product_id: "Produk KPR Developer",
        kpr_tenor_bulan: "Tenor KPR",
        kpr_bunga_tahunan: "Bunga atau margin",
        harga_jual: "Harga jual",
        booking_fee: "Booking fee",
        uang_muka: "Uang muka",
        nilai_pengajuan_kpr: "Nilai pengajuan KPR",
        jumlah_termin: "Jumlah termin",
        tanggal_jatuh_tempo_angsuran: "Jatuh tempo angsuran pertama",
        berkas: "Dokumen pelanggan",
    };
    const validationErrors = Object.entries(form.errors ?? {}).filter(
        ([, message]) => Boolean(message),
    );
    const fieldDomId = (key) => `spr-field-${String(key).replaceAll(".", "-")}`;
    const errorLabel = (key) =>
        key.startsWith("berkas.")
            ? "Dokumen pelanggan"
            : (fieldLabels[key] ?? key.replaceAll("_", " "));
    const goToError = (key) => {
        setValidationModalOpen(false);
        window.setTimeout(() => {
            const target =
                document.getElementById(fieldDomId(key)) ??
                (key.startsWith("berkas.")
                    ? document.getElementById("spr-field-berkas")
                    : null) ??
                document.getElementById("spr-form-fields");
            target?.scrollIntoView({ behavior: "smooth", block: "center" });
            target
                ?.querySelector?.("input, button, textarea, select")
                ?.focus?.();
            target?.focus?.();
        }, 120);
    };

    useEffect(() => {
        if (!form.data.costumer_id) return;

        const reservation = reservations.find(
            (item) => String(item.costumer_id) === String(form.data.costumer_id),
        );
        if (!reservation) return;

        const reservedUnit = units.find(
            (item) => String(item.id) === String(reservation.detail_rumah_id),
        );
        form.setData({
            ...form.data,
            housing_reservation_id: String(reservation.id),
            detail_rumah_id: String(reservation.detail_rumah_id),
            metode_pembayaran: reservation.payment_method,
            booking_fee: String(reservation.booking_fee ?? ""),
            tanggal_pembayaran_booking_fee: reservation.paid_at ?? "",
            harga_jual: reservedUnit?.harga_jual
                ? String(reservedUnit.harga_jual)
                : form.data.harga_jual,
            cash_installment_scheme_id: reservation.cash_installment_scheme_id
                ? String(reservation.cash_installment_scheme_id)
                : "",
            developer_kpr_product_id: reservation.developer_kpr_product_id
                ? String(reservation.developer_kpr_product_id)
                : "",
            bank_credit_product_id: reservation.bank_credit_product_id
                ? String(reservation.bank_credit_product_id)
                : "",
            bank_kredit_id: reservation.bank_kredit_id
                ? String(reservation.bank_kredit_id)
                : "",
            bank_branch_id: reservation.bank_branch_id
                ? String(reservation.bank_branch_id)
                : "",
            kpr_tenor_bulan: reservation.kpr_tenor_bulan
                ? String(reservation.kpr_tenor_bulan)
                : form.data.kpr_tenor_bulan,
            kpr_bunga_tahunan: reservation.kpr_bunga_tahunan != null
                ? String(reservation.kpr_bunga_tahunan)
                : form.data.kpr_bunga_tahunan,
        });
    }, [form.data.costumer_id]);

    const selectedCustomer = customers.find(
        (customer) => Number(customer.id) === Number(form.data.costumer_id),
    );
    const selectedUnit = units.find(
        (unit) => Number(unit.id) === Number(form.data.detail_rumah_id),
    );
    const selectedBankKredit = bankKreditOptions.find(
        (bank) => String(bank.value) === String(form.data.bank_kredit_id),
    );
    const selectedCreditProduct = bankCreditProductOptions.find(
        (product) =>
            String(product.value) === String(form.data.bank_credit_product_id),
    );
    const selectedCashScheme = cashInstallmentSchemeOptions.find(
        (scheme) =>
            String(scheme.value) ===
            String(form.data.cash_installment_scheme_id),
    );
    const selectedDeveloperProduct = developerKprProductOptions.find(
        (product) =>
            String(product.value) ===
            String(form.data.developer_kpr_product_id),
    );
    const currentBerkas = berkasRows;

    const calcTanahQty = Number(form.data.penambahan_tanah || 0);
    const calcTanahPrice = Number(form.data.harga_penambahan_tanah || 0);
    const calcTanah = calcTanahQty * calcTanahPrice;
    const calcLain = Number(form.data.harga_penambahan_lain_lain || 0);
    const calcTotal = calcTanah + calcLain;
    const calcFinal =
        Number(selectedUnit?.harga_jual || form.data.harga_jual || 0) +
        calcTotal;
    const isBertahap = ["bertahap", "cash_bertahap"].includes(
        form.data.metode_pembayaran,
    );
    const isKprBank = form.data.metode_pembayaran === "kpr_bank";
    const isKprDeveloper = form.data.metode_pembayaran === "kpr_developer";
    const customerProjectId = String(selectedCustomer?.perumahan_id ?? "");
    const projectId = String(selectedUnit?.perumahan_id ?? customerProjectId);
    const availableUnits = units.filter(
        (unit) =>
            (unit.is_available &&
                String(unit.perumahan_id) === customerProjectId) ||
            String(unit.id) === String(form.data.detail_rumah_id),
    );
    const appliesToProject = (item) =>
        item.perumahan_ids?.length
            ? item.perumahan_ids.map(String).includes(String(projectId))
            : !item.perumahan_id ||
              String(item.perumahan_id) === String(projectId);
    const activeCashSchemes = cashInstallmentSchemeOptions.filter(
        (item) =>
            appliesToProject(item) ||
            String(item.value) === String(form.data.cash_installment_scheme_id),
    );
    const activeDeveloperProducts = developerKprProductOptions.filter(
        (item) =>
            appliesToProject(item) ||
            String(item.value) === String(form.data.developer_kpr_product_id),
    );
    const activeBanks = bankKreditOptions.filter(
        (item) =>
            item.perumahan_ids?.includes(projectId) ||
            String(item.value) === String(form.data.bank_kredit_id),
    );
    const activeBankBranches = bankBranchOptions.filter(
        (item) =>
            item.bank_id === String(form.data.bank_kredit_id) &&
            (item.perumahan_ids?.includes(projectId) ||
                String(item.value) === String(form.data.bank_branch_id)),
    );
    const activeCreditProducts = bankCreditProductOptions.filter(
        (item) =>
            item.bank_id === String(form.data.bank_kredit_id) &&
            ((item.branch_id === String(form.data.bank_branch_id) &&
                item.perumahan_ids?.includes(projectId)) ||
                String(item.value) ===
                    String(form.data.bank_credit_product_id)),
    );
    const calcNominalTermin =
        isBertahap && Number(form.data.jumlah_termin || 0) > 0
            ? Math.round(
                  Math.max(
                      0,
                      calcFinal -
                          Number(form.data.booking_fee || 0) -
                          Number(form.data.uang_muka || 0),
                  ) / Number(form.data.jumlah_termin || 1),
              )
            : 0;
    const kprRate = Number(
        form.data.kpr_bunga_tahunan ||
            selectedCreditProduct?.indicative_interest_margin ||
            0,
    );
    const kprMonths = Math.max(
        1,
        Number(
            form.data.kpr_tenor_bulan ||
                selectedCreditProduct?.maximum_tenor_months ||
                1,
        ),
    );
    const kprPrincipal = Number(form.data.nilai_pengajuan_kpr || 0);
    const kprMonthlyRate = kprRate / 100 / 12;
    const kprInstallment =
        kprPrincipal <= 0
            ? 0
            : kprMonthlyRate > 0
              ? (kprPrincipal *
                    (kprMonthlyRate * (1 + kprMonthlyRate) ** kprMonths)) /
                ((1 + kprMonthlyRate) ** kprMonths - 1)
              : kprPrincipal / kprMonths;
    const kprMinimalDp = Number(
        selectedCreditProduct?.minimum_down_payment || 0,
    );
    const kprProvisi = Number(selectedCreditProduct?.provision_fee || 0);
    const minimumDpFor = (master) =>
        master?.dp_type === "percentage"
            ? (calcFinal * Number(master.minimum_dp || 0)) / 100
            : Number(master?.minimum_dp || 0);
    const cashMinimumDp = minimumDpFor(selectedCashScheme);
    const developerMinimumDp = minimumDpFor(selectedDeveloperProduct);
    const developerFinancingBase =
        selectedDeveloperProduct?.financing_basis === "sale_price"
            ? Number(selectedUnit?.harga_jual || form.data.harga_jual || 0)
            : selectedDeveloperProduct?.financing_basis === "final_less_booking"
              ? Math.max(0, calcFinal - Number(form.data.booking_fee || 0))
              : selectedDeveloperProduct?.financing_basis ===
                  "final_less_booking_dp"
                ? Math.max(
                      0,
                      calcFinal -
                          Number(form.data.booking_fee || 0) -
                          Number(form.data.uang_muka || 0),
                  )
                : calcFinal;
    const developerMaximumFinancing =
        selectedDeveloperProduct?.financing_type === "percentage"
            ? (developerFinancingBase *
                  Number(selectedDeveloperProduct.maximum_financing || 0)) /
              100
            : Number(selectedDeveloperProduct?.maximum_financing || 0);
    const developerTenorOptions = !selectedDeveloperProduct
        ? []
        : selectedDeveloperProduct.tenor_mode === "custom"
          ? (selectedDeveloperProduct.allowed_tenors ?? []).map((value) => ({
                value: String(value),
                label: `${value} bulan`,
            }))
          : Array.from(
                {
                    length: Math.max(
                        0,
                        Math.floor(
                            (Number(
                                selectedDeveloperProduct.maximum_tenor_months ||
                                    0,
                            ) -
                                Number(
                                    selectedDeveloperProduct.minimum_tenor_months ||
                                        0,
                                )) /
                                Math.max(
                                    1,
                                    Number(
                                        selectedDeveloperProduct.tenor_increment ||
                                            1,
                                    ),
                                ),
                        ) + 1,
                    ),
                },
                (_, index) => {
                    const value =
                        Number(selectedDeveloperProduct.minimum_tenor_months) +
                        index *
                            Math.max(
                                1,
                                Number(
                                    selectedDeveloperProduct.tenor_increment ||
                                        1,
                                ),
                            );
                    return { value: String(value), label: `${value} bulan` };
                },
            );
    const selectedDeveloperMarginTier = (
        selectedDeveloperProduct?.margin_tiers ?? []
    ).find(
        (tier) =>
            Number(tier.tenor_months ?? tier.tenor ?? 0) ===
            Number(form.data.kpr_tenor_bulan || 0),
    );
    const developerMargin =
        selectedDeveloperProduct?.margin_scope === "per_tenor"
            ? Number(
                  selectedDeveloperMarginTier?.annual_margin ??
                      selectedDeveloperMarginTier?.margin ??
                      selectedDeveloperMarginTier?.value ??
                      0,
              )
            : Number(selectedDeveloperProduct?.annual_margin ?? 0);

    const syncBerkas = (rows) => {
        setBerkasRows(rows);
        form.setData(
            "berkas",
            rows.map(
                ({ file_name, dokumen_label, existing_file, ...item }) => item,
            ),
        );
    };

    const updateBerkasRow = (index, patch) => {
        setBerkasRows((rows) => {
            const nextRows = rows.map((rowItem, rowIndex) =>
                rowIndex === index ? { ...rowItem, ...patch } : rowItem,
            );
            form.setData(
                "berkas",
                nextRows.map(
                    ({ file_name, dokumen_label, existing_file, ...item }) =>
                        item,
                ),
            );
            return nextRows;
        });
    };

    useEffect(() => {
        const media = window.matchMedia("(max-width: 767px)");
        const update = () => setIsMobile(media.matches);
        update();
        media.addEventListener("change", update);
        return () => media.removeEventListener("change", update);
    }, []);

    useEffect(() => {
        if (validationErrors.length > 0) setValidationModalOpen(true);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [Object.keys(form.errors ?? {}).join("|")]);

    useEffect(() => {
        const nextRows = buildInitialBerkasRows(
            form.data.metode_pembayaran,
            form.data.costumer_id,
        );
        syncBerkas(nextRows);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        row?.id,
        dokumenOptions,
        repositoryDocuments,
        form.data.metode_pembayaran,
        form.data.costumer_id,
    ]);

    useEffect(() => {
        if (selectedUnit)
            form.setData("harga_jual", String(selectedUnit.harga_jual ?? 0));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedUnit?.id, selectedUnit?.harga_jual]);

    useEffect(() => {
        if (!selectedCashScheme) return;
        form.setData({
            ...form.data,
            jumlah_termin: String(selectedCashScheme.installment_count ?? ""),
            booking_fee: String(
                Math.max(
                    Number(form.data.booking_fee || 0),
                    Number(selectedCashScheme.minimum_booking_fee || 0),
                ),
            ),
            booking_fee_includes_dp:
                selectedCashScheme.booking_fee_deducts === "down_payment"
                    ? "1"
                    : form.data.booking_fee_includes_dp,
            uang_muka: String(
                Math.max(Number(form.data.uang_muka || 0), cashMinimumDp),
            ),
            kpr_tenor_bulan: "",
            kpr_bunga_tahunan: "",
            nilai_pengajuan_kpr: "",
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedCashScheme?.value, cashMinimumDp]);

    useEffect(() => {
        if (!selectedCreditProduct) return;
        const dp = Math.max(
            Number(form.data.uang_muka || 0),
            Number(selectedCreditProduct.minimum_down_payment || 0),
        );
        const availableFinancing = Math.max(
            0,
            calcFinal - dp - Number(form.data.booking_fee || 0),
        );
        form.setData({
            ...form.data,
            uang_muka: String(dp),
            kpr_tenor_bulan: String(
                form.data.kpr_tenor_bulan ||
                    selectedCreditProduct.maximum_tenor_months ||
                    "",
            ),
            kpr_bunga_tahunan: String(
                selectedCreditProduct.indicative_interest_margin ?? 0,
            ),
            nilai_pengajuan_kpr: String(
                Math.min(
                    availableFinancing,
                    Number(selectedCreditProduct.maximum_ceiling || availableFinancing),
                ),
            ),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedCreditProduct?.value]);

    useEffect(() => {
        if (!selectedDeveloperProduct) return;
        const firstTenor =
            developerTenorOptions[0]?.value ??
            String(selectedDeveloperProduct.maximum_tenor_months ?? "");
        const dp = Math.max(
            Number(form.data.uang_muka || 0),
            developerMinimumDp,
        );
        form.setData({
            ...form.data,
            uang_muka: String(dp),
            kpr_tenor_bulan: developerTenorOptions.some(
                (item) => item.value === String(form.data.kpr_tenor_bulan),
            )
                ? String(form.data.kpr_tenor_bulan)
                : firstTenor,
            kpr_bunga_tahunan: String(developerMargin),
            nilai_pengajuan_kpr: String(
                Math.max(
                    0,
                    Math.min(
                        developerMaximumFinancing,
                        calcFinal - dp - Number(form.data.booking_fee || 0),
                    ),
                ),
            ),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        selectedDeveloperProduct?.value,
        developerMinimumDp,
        developerMaximumFinancing,
        calcFinal,
        form.data.kpr_tenor_bulan,
        developerMargin,
    ]);

    useEffect(() => {
        form.setData("total_penambahan_tanah", String(calcTanah));
        form.setData("total_penambahan_lain_lain", String(calcLain));
        form.setData("total_penambahan", String(calcTotal));
        form.setData("nilai_pengajuan_akhir", String(calcFinal));
        if (isBertahap) {
            form.setData("nominal_termin", String(calcNominalTermin));
        } else {
            form.setData("nominal_termin", "");
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        calcTanah,
        calcLain,
        calcTotal,
        calcFinal,
        calcNominalTermin,
        isBertahap,
    ]);

    const selectedUnitInfo = useMemo(() => selectedUnit, [selectedUnit]);

    const submit = (event) => {
        event.preventDefault();
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onError: () => setValidationModalOpen(true),
        };

        if (method === "put") form.put(submitUrl, options);
        else form.post(submitUrl, options);
    };

    const fieldsContent = (
        <div id="spr-form-fields" className="grid gap-4 lg:grid-cols-3">
            {form.data.housing_reservation_id && (
                <div className="lg:col-span-3 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                    <p className="font-extrabold">Reservasi final ditemukan otomatis</p>
                    <p className="mt-1">
                        {reservations.find((item) => String(item.id) === String(form.data.housing_reservation_id))?.label}
                    </p>
                    <p className="mt-1 text-xs font-semibold">
                        Unit, metode pembayaran, Booking Fee, dan tanggal pembayaran mengikuti reservasi ini.
                    </p>
                </div>
            )}
            <div className="lg:col-span-3 sticky top-0 z-20 -mx-5 -mt-2 border-b border-silver-deep/60 bg-white/95 px-5 py-4 backdrop-blur dark:border-white/10 dark:bg-graphite/95">
                <div className="grid gap-4 lg:grid-cols-2">
                    <SearchPicker
                        id={fieldDomId("costumer_id")}
                        className="lg:col-span-1"
                        label="Pilih Pelanggan"
                        placeholder="Cari nama, no identitas, atau telepon"
                        rows={customers}
                        required
                        selected={selectedCustomer}
                        error={form.errors.costumer_id}
                        onSelect={(customer) => {
                            const reservation = reservations.find(
                                (item) => String(item.costumer_id) === String(customer.id),
                            );
                            const reservedUnit = reservation
                                ? units.find((item) => String(item.id) === String(reservation.detail_rumah_id))
                                : null;
                            form.setData({
                                ...form.data,
                                costumer_id: String(customer.id),
                                housing_reservation_id: reservation ? String(reservation.id) : "",
                                detail_rumah_id: reservation ? String(reservation.detail_rumah_id) : "",
                                metode_pembayaran: reservation?.payment_method ?? form.data.metode_pembayaran,
                                booking_fee: reservation ? String(reservation.booking_fee) : "",
                                tanggal_pembayaran_booking_fee: reservation?.paid_at ?? "",
                                harga_jual: reservedUnit?.harga_jual
                                    ? String(reservedUnit.harga_jual)
                                    : form.data.harga_jual,
                                bank_kredit_id: "",
                                bank_branch_id: "",
                                bank_credit_product_id: "",
                                cash_installment_scheme_id: "",
                                developer_kpr_product_id: "",
                            });
                        }}
                    />
                    <SearchPicker
                        id={fieldDomId("detail_rumah_id")}
                        className="lg:col-span-1"
                        label="Pilih Unit Rumah"
                        placeholder={
                            customerProjectId
                                ? "Cari unit tersedia pada perumahan pelanggan"
                                : "Pilih pelanggan terlebih dahulu"
                        }
                        rows={availableUnits}
                        required
                        selected={selectedUnit}
                        error={form.errors.detail_rumah_id}
                        onSelect={(unit) => {
                            form.setData("detail_rumah_id", String(unit.id));
                            form.setData(
                                "harga_jual",
                                unit.harga_jual
                                    ? String(unit.harga_jual)
                                    : form.data.harga_jual,
                            );
                        }}
                    />
                </div>
            </div>

            <Input
                id={fieldDomId("tanggal_spr")}
                label="Tanggal SPR"
                required
                type="date"
                value={form.data.tanggal_spr}
                error={form.errors.tanggal_spr}
                onChange={(event) =>
                    form.setData("tanggal_spr", event.target.value)
                }
            />
            <div id={fieldDomId("metode_pembayaran")} className="grid gap-2">
                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">
                    Metode Pembayaran <span className="text-red-500">*</span>
                </span>
                <Dropdown
                    value={form.data.metode_pembayaran}
                    options={options.paymentOptions ?? []}
                    onChange={(value) => {
                        form.setData("metode_pembayaran", value);
                        if (value !== "cash_bertahap") {
                            form.setData("cash_installment_scheme_id", "");
                            form.setData("jumlah_termin", "");
                            form.setData("nominal_termin", "");
                            form.setData("tanggal_jatuh_tempo_angsuran", "");
                            form.setData("uang_muka_jumlah_pembayaran", "");
                        }
                        if (value !== "kpr_bank") {
                            form.setData("bank_kredit_id", "");
                            form.setData("bank_branch_id", "");
                            form.setData("bank_credit_product_id", "");
                            form.setData("kpr_tenor_bulan", "");
                            form.setData("kpr_bunga_tahunan", "");
                        }
                        if (value !== "kpr_developer")
                            form.setData("developer_kpr_product_id", "");
                    }}
                />
                {form.errors.metode_pembayaran && (
                    <span className="text-xs font-bold text-red-600 dark:text-red-300">
                        {form.errors.metode_pembayaran}
                    </span>
                )}
            </div>
            {isBertahap && (
                <div
                    id={fieldDomId("cash_installment_scheme_id")}
                    className="grid gap-2 lg:col-span-2"
                >
                    <span className="text-sm font-extrabold">
                        Skema Tunai Bertahap{" "}
                        <span className="text-red-500">*</span>
                    </span>
                    <Dropdown
                        value={form.data.cash_installment_scheme_id}
                        options={activeCashSchemes}
                        label={
                            projectId
                                ? "Pilih skema aktif untuk perumahan"
                                : "Pilih unit terlebih dahulu"
                        }
                        disabled={!projectId}
                        onChange={(value) =>
                            form.setData("cash_installment_scheme_id", value)
                        }
                    />
                    {form.errors.cash_installment_scheme_id && (
                        <span className="text-xs font-bold text-red-600">
                            {form.errors.cash_installment_scheme_id}
                        </span>
                    )}
                </div>
            )}
            {selectedCashScheme && (
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-950 lg:col-span-3">
                    <b>{selectedCashScheme.label}</b>
                    <br />
                    Booking minimum{" "}
                    {money(selectedCashScheme.minimum_booking_fee)} · DP minimum{" "}
                    {money(cashMinimumDp)} ·{" "}
                    {selectedCashScheme.installment_count} tagihan · denda{" "}
                    {selectedCashScheme.penalty_method === "none"
                        ? "tidak ada"
                        : `${selectedCashScheme.penalty_method} ${selectedCashScheme.penalty_method === "fixed" ? money(selectedCashScheme.penalty_value) : `${selectedCashScheme.penalty_value}%`}`}
                    .<br />
                    <span className="text-xs">
                        Jadwal, tahapan, denda, syarat, dan serah terima
                        mengikuti master ini dan disimpan sebagai snapshot SPR.
                    </span>
                    {selectedCashScheme.steps?.length > 0 && (
                        <div className="mt-2 grid gap-1">
                            {selectedCashScheme.steps.map((step) => (
                                <span key={`${step.sequence}-${step.name}`}>
                                    Tahap {step.sequence}: {step.name} · bulan
                                    ke-{step.due_offset_months}
                                </span>
                            ))}
                        </div>
                    )}
                </div>
            )}
            {isKprDeveloper && (
                <div
                    id={fieldDomId("developer_kpr_product_id")}
                    className="grid gap-2 lg:col-span-2"
                >
                    <span className="text-sm font-extrabold">
                        Produk KPR Developer{" "}
                        <span className="text-red-500">*</span>
                    </span>
                    <Dropdown
                        value={form.data.developer_kpr_product_id}
                        options={activeDeveloperProducts}
                        label={
                            projectId
                                ? "Pilih produk aktif untuk perumahan"
                                : "Pilih unit terlebih dahulu"
                        }
                        disabled={!projectId}
                        onChange={(value) =>
                            form.setData("developer_kpr_product_id", value)
                        }
                    />
                    {form.errors.developer_kpr_product_id && (
                        <span className="text-xs font-bold text-red-600">
                            {form.errors.developer_kpr_product_id}
                        </span>
                    )}
                </div>
            )}
            {selectedDeveloperProduct && (
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-950 lg:col-span-3">
                    <b>{selectedDeveloperProduct.label}</b>
                    <br />
                    DP minimum {money(developerMinimumDp)} · pembiayaan maksimum{" "}
                    {money(developerMaximumFinancing)} · margin{" "}
                    {selectedDeveloperProduct.annual_margin}% per tahun ·
                    penghasilan minimum{" "}
                    {money(selectedDeveloperProduct.minimum_income)}.<br />
                    <span className="text-xs">
                        Pilihan tenor, margin, biaya, denda, kelayakan, dan
                        serah terima otomatis mengikuti produk master.
                    </span>
                </div>
            )}
            {isKprBank && (
                <div className="grid gap-4 lg:col-span-3 lg:grid-cols-3">
                    <div
                        id={fieldDomId("bank_kredit_id")}
                        className="grid gap-2"
                    >
                        <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">
                            Bank Kredit <span className="text-red-500">*</span>
                        </span>
                        <Dropdown
                            value={form.data.bank_kredit_id}
                            options={activeBanks}
                            onChange={(value) => {
                                form.setData({
                                    ...form.data,
                                    bank_kredit_id: value,
                                    bank_branch_id: "",
                                    bank_credit_product_id: "",
                                    kpr_tenor_bulan: "",
                                    kpr_bunga_tahunan: "",
                                });
                            }}
                        />
                        {form.errors.bank_kredit_id && (
                            <span className="text-xs font-bold text-red-600 dark:text-red-300">
                                {form.errors.bank_kredit_id}
                            </span>
                        )}
                    </div>
                    <div
                        id={fieldDomId("bank_branch_id")}
                        className="grid gap-2"
                    >
                        <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">
                            Cabang Bank <span className="text-red-500">*</span>
                        </span>
                        <Dropdown
                            value={form.data.bank_branch_id}
                            options={activeBankBranches}
                            disabled={!form.data.bank_kredit_id}
                            onChange={(value) =>
                                form.setData({
                                    ...form.data,
                                    bank_branch_id: value,
                                    bank_credit_product_id: "",
                                    kpr_tenor_bulan: "",
                                    kpr_bunga_tahunan: "",
                                })
                            }
                        />
                        {form.errors.bank_branch_id && (
                            <span className="text-xs font-bold text-red-600">
                                {form.errors.bank_branch_id}
                            </span>
                        )}
                    </div>
                    <div
                        id={fieldDomId("bank_credit_product_id")}
                        className="grid gap-2"
                    >
                        <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">
                            Produk Kredit{" "}
                            <span className="text-red-500">*</span>
                        </span>
                        <Dropdown
                            value={form.data.bank_credit_product_id}
                            options={activeCreditProducts}
                            disabled={!form.data.bank_branch_id}
                            onChange={(value) => {
                                const product = bankCreditProductOptions.find(
                                    (item) => item.value === value,
                                );
                                form.setData({
                                    ...form.data,
                                    bank_credit_product_id: value,
                                    kpr_tenor_bulan: String(
                                        product?.maximum_tenor_months ?? "",
                                    ),
                                    kpr_bunga_tahunan: String(
                                        product?.indicative_interest_margin ??
                                            "",
                                    ),
                                });
                            }}
                        />
                        {form.errors.bank_credit_product_id && (
                            <span className="text-xs font-bold text-red-600 dark:text-red-300">
                                {form.errors.bank_credit_product_id}
                            </span>
                        )}
                    </div>
                    <Input
                        id={fieldDomId("kpr_tenor_bulan")}
                        label="Tenor KPR (Bulan)"
                        required
                        type="number"
                        min="1"
                        max={
                            selectedCreditProduct?.maximum_tenor_months ??
                            undefined
                        }
                        value={form.data.kpr_tenor_bulan}
                        error={form.errors.kpr_tenor_bulan}
                        onChange={(event) =>
                            form.setData("kpr_tenor_bulan", event.target.value)
                        }
                    />
                    <Input
                        label="Bunga KPR / Tahun (%) dari Master"
                        type="number"
                        step="0.01"
                        value={form.data.kpr_bunga_tahunan}
                        readOnly
                        disabled
                        error={form.errors.kpr_bunga_tahunan}
                    />
                    {selectedCreditProduct && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200 lg:col-span-3">
                            Produk {selectedCreditProduct.label}. Minimum DP:{" "}
                            {money(kprMinimalDp)}. Estimasi cicilan:{" "}
                            {money(kprInstallment)} / bulan. Provisi:{" "}
                            {money(kprProvisi)}. Administrasi:{" "}
                            {money(selectedCreditProduct.administration_fee)}.
                        </div>
                    )}
                </div>
            )}
            {isKprDeveloper && selectedDeveloperProduct && (
                <div className="grid gap-4 lg:col-span-3 lg:grid-cols-3">
                    <div
                        id={fieldDomId("kpr_tenor_bulan")}
                        className="grid gap-2"
                    >
                        <span className="text-sm font-extrabold">
                            Tenor KPR Developer{" "}
                            <span className="text-red-500">*</span>
                        </span>
                        <Dropdown
                            value={form.data.kpr_tenor_bulan}
                            options={developerTenorOptions}
                            onChange={(value) =>
                                form.setData("kpr_tenor_bulan", value)
                            }
                        />
                        {form.errors.kpr_tenor_bulan && (
                            <span className="text-xs font-bold text-red-600">
                                {form.errors.kpr_tenor_bulan}
                            </span>
                        )}
                    </div>
                    <Input
                        label="Margin / Tahun (%)"
                        value={form.data.kpr_bunga_tahunan}
                        readOnly
                        disabled
                    />
                    <CurrencyInput
                        id={fieldDomId("nilai_pengajuan_kpr")}
                        label="Pembiayaan Developer"
                        required
                        value={form.data.nilai_pengajuan_kpr}
                        readOnly
                        disabled
                        error={form.errors.nilai_pengajuan_kpr}
                        onChange={() => {}}
                    />
                </div>
            )}
            <CurrencyInput
                id={fieldDomId("harga_jual")}
                label="Harga Jual Unit"
                required
                value={form.data.harga_jual}
                readOnly
                disabled
                error={form.errors.harga_jual}
                onChange={() => {}}
            />
            <CurrencyInput
                id={fieldDomId("booking_fee")}
                label="Booking Fee"
                required={isBertahap}
                value={form.data.booking_fee}
                error={form.errors.booking_fee}
                onChange={(value) => form.setData("booking_fee", value)}
            />
            <div className="grid gap-2">
                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">
                    Booking Fee Termasuk DP?
                </span>
                <Dropdown
                    value={form.data.booking_fee_includes_dp}
                    options={yesNoOptions}
                    onChange={(value) =>
                        form.setData("booking_fee_includes_dp", value)
                    }
                />
                {form.errors.booking_fee_includes_dp && (
                    <span className="text-xs font-bold text-red-600 dark:text-red-300">
                        {form.errors.booking_fee_includes_dp}
                    </span>
                )}
            </div>
            <Input
                label="Tanggal Pembayaran Booking Fee"
                type="date"
                value={form.data.tanggal_pembayaran_booking_fee}
                error={form.errors.tanggal_pembayaran_booking_fee}
                onChange={(event) =>
                    form.setData(
                        "tanggal_pembayaran_booking_fee",
                        event.target.value,
                    )
                }
            />
            <CurrencyInput
                id={fieldDomId("uang_muka")}
                label="Uang Muka"
                required={isBertahap || isKprBank || isKprDeveloper}
                value={form.data.uang_muka}
                error={form.errors.uang_muka}
                onChange={(value) => form.setData("uang_muka", value)}
            />
            <Input
                label="Tanggal Jatuh Tempo DP"
                type="date"
                value={form.data.tanggal_jatuh_tempo_dp}
                error={form.errors.tanggal_jatuh_tempo_dp}
                onChange={(event) =>
                    form.setData("tanggal_jatuh_tempo_dp", event.target.value)
                }
            />
            {isKprBank && (
                <CurrencyInput
                    id={fieldDomId("nilai_pengajuan_kpr")}
                    label="Nilai Pengajuan KPR"
                    required
                    value={form.data.nilai_pengajuan_kpr}
                    error={form.errors.nilai_pengajuan_kpr}
                    onChange={(value) =>
                        form.setData("nilai_pengajuan_kpr", value)
                    }
                />
            )}
            <Input
                label="Penambahan Tanah (m2)"
                type="number"
                min="0"
                value={form.data.penambahan_tanah}
                error={form.errors.penambahan_tanah}
                onChange={(event) =>
                    form.setData("penambahan_tanah", event.target.value)
                }
            />
            <CurrencyInput
                label="Harga Penambahan Tanah"
                value={form.data.harga_penambahan_tanah}
                error={form.errors.harga_penambahan_tanah}
                onChange={(value) =>
                    form.setData("harga_penambahan_tanah", value)
                }
            />
            <CurrencyInput
                label="Total Harga Penambahan Tanah"
                value={form.data.total_penambahan_tanah}
                readOnly
                disabled
                onChange={() => {}}
            />

            <Input
                label="Penambahan Lain-Lain"
                value={form.data.penambahan_lain_lain}
                error={form.errors.penambahan_lain_lain}
                onChange={(event) =>
                    form.setData("penambahan_lain_lain", event.target.value)
                }
            />
            <CurrencyInput
                label="Harga Penambahan Lain-Lain"
                value={form.data.harga_penambahan_lain_lain}
                error={form.errors.harga_penambahan_lain_lain}
                onChange={(value) =>
                    form.setData("harga_penambahan_lain_lain", value)
                }
            />
            <CurrencyInput
                label="Total Harga Penambahan Lain-Lain"
                value={form.data.total_penambahan_lain_lain}
                readOnly
                disabled
                onChange={() => {}}
            />

            <CurrencyInput
                label="Total Penambahan"
                value={form.data.total_penambahan}
                readOnly
                disabled
                onChange={() => {}}
            />
            <CurrencyInput
                label="Harga Akhir (Unit + Penambahan)"
                value={form.data.nilai_pengajuan_akhir}
                readOnly
                disabled
                onChange={() => {}}
            />
            {isBertahap && (
                <div className="grid gap-4 md:grid-cols-2 lg:col-span-3">
                    <Input
                        id={fieldDomId("jumlah_termin")}
                        label="Jumlah Termin dari Master"
                        required
                        type="number"
                        min="1"
                        value={form.data.jumlah_termin}
                        readOnly
                        disabled
                        error={form.errors.jumlah_termin}
                    />
                    <CurrencyInput
                        label="Nominal Termin"
                        value={form.data.nominal_termin}
                        readOnly
                        disabled
                        onChange={() => {}}
                    />
                    <Input
                        id={fieldDomId("tanggal_jatuh_tempo_angsuran")}
                        label="Tanggal Jatuh Tempo Angsuran Pertama"
                        required
                        type="date"
                        value={form.data.tanggal_jatuh_tempo_angsuran}
                        error={form.errors.tanggal_jatuh_tempo_angsuran}
                        onChange={(event) =>
                            form.setData(
                                "tanggal_jatuh_tempo_angsuran",
                                event.target.value,
                            )
                        }
                    />
                </div>
            )}

            <Textarea
                className="lg:col-span-3"
                label="Catatan"
                value={form.data.catatan}
                error={form.errors.catatan}
                onChange={(event) =>
                    form.setData("catatan", event.target.value)
                }
            />
        </div>
    );

    const berkasContent = (
        <div id={fieldDomId("berkas")} className="grid gap-4">
            <div className="flex flex-col gap-3 rounded-xl border border-silver-deep/60 bg-silver-soft/40 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-white/5">
                <div>
                    <p className="font-extrabold text-ink dark:text-white">
                        {currentBerkas.filter((item) => item.selected).length}{" "}
                        dari {currentBerkas.length} dokumen dipilih
                    </p>
                    <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                        Dokumen berasal dari Repositori Dokumen Pelanggan.
                        Berkas baru yang diunggah di sini otomatis masuk ke
                        repository.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    disabled={!form.data.costumer_id}
                    onClick={() => setRequirementsOpen(true)}
                >
                    <CheckSquare2 size={17} /> Lihat Persyaratan Dokumen
                </Button>
            </div>
            {form.errors.berkas && (
                <p className="rounded-lg bg-red-50 p-3 text-sm font-bold text-red-700">
                    {form.errors.berkas}
                </p>
            )}
            {!form.data.costumer_id && (
                <p className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-800">
                    Pilih pelanggan terlebih dahulu untuk melihat dokumen
                    repository-nya.
                </p>
            )}
        </div>
    );

    const summaryContent = (
        <div className="grid gap-3 text-sm">
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">
                    Pelanggan
                </p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">
                    {selectedCustomer?.label ?? "-"}
                </p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    {selectedCustomer?.telepon ?? "-"}
                </p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">
                    Unit
                </p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">
                    {selectedUnitInfo?.label ?? "-"}
                </p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    {selectedUnitInfo
                        ? `LT ${selectedUnitInfo.luas_tanah ?? "-"} | LB ${selectedUnitInfo.luas_bangunan ?? "-"} | ${selectedUnitInfo.status_penjualan ?? "-"}`
                        : "-"}
                </p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">
                    Harga Jual
                </p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">
                    {money(form.data.harga_jual)}
                </p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">
                    Booking Fee
                </p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">
                    {money(form.data.booking_fee)}
                </p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    {form.data.booking_fee_includes_dp === "1"
                        ? "Termasuk DP"
                        : "Tidak termasuk DP"}
                </p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    Dibayar: {form.data.tanggal_pembayaran_booking_fee || "-"}
                </p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">
                    Uang Muka
                </p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">
                    {money(form.data.uang_muka)}
                </p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    Dibayar {form.data.uang_muka_jumlah_pembayaran || 0} kali
                </p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    Jatuh tempo DP: {form.data.tanggal_jatuh_tempo_dp || "-"}
                </p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">
                    Total Penambahan
                </p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">
                    {money(calcTotal)}
                </p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    Tanah: {calcTanahQty || 0} m2 x {money(calcTanahPrice)} ={" "}
                    {money(calcTanah)} | Lain-lain: {money(calcLain)}
                </p>
            </div>
            {isBertahap && (
                <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                    <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">
                        Skema Bertahap
                    </p>
                    <p className="mt-1 text-base font-extrabold text-ink dark:text-white">
                        Bertahap
                    </p>
                    <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                        Nominal termin dihitung otomatis dari hasil akhir dibagi
                        jumlah termin.
                    </p>
                </div>
            )}
            {isKprBank && (
                <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                    <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">
                        KPR Bank
                    </p>
                    <p className="mt-1 text-base font-extrabold text-ink dark:text-white">
                        {selectedBankKredit?.label ?? row?.bank_kredit ?? "-"}
                    </p>
                    <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                        {kprRate || 0}% / tahun, {kprMonths} bulan, cicilan
                        estimasi {money(kprInstallment)} / bulan
                    </p>
                </div>
            )}
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">
                    Hasil Akhir
                </p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">
                    {money(calcFinal)}
                </p>
            </div>
            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/50">
                    Status
                </p>
                <p className="mt-1 text-base font-extrabold text-ink dark:text-white">
                    {row?.status_label ?? "Draf"}
                </p>
                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                    {row?.record_status_label ?? "Draf"}
                </p>
            </div>
        </div>
    );

    return (
        <>
            <Head title={title} />
            <Modal
                open={validationModalOpen && validationErrors.length > 0}
                onClose={() => setValidationModalOpen(false)}
                title="SPR belum dapat disimpan"
                size="sm"
                footer={
                    <Button
                        type="button"
                        onClick={() => setValidationModalOpen(false)}
                    >
                        Tutup dan Perbaiki
                    </Button>
                }
            >
                <div className="grid gap-4">
                    <div className="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
                        <AlertTriangle className="mt-0.5 shrink-0" size={20} />
                        <div>
                            <p className="font-black">
                                Ada {validationErrors.length} bagian yang perlu
                                diperbaiki.
                            </p>
                            <p className="mt-1 text-sm">
                                Klik salah satu error untuk menuju ke input
                                terkait.
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-2">
                        {validationErrors.map(([key, message]) => (
                            <button
                                key={key}
                                type="button"
                                className="rounded-lg border border-red-200 px-4 py-3 text-left transition hover:bg-red-50"
                                onClick={() => goToError(key)}
                            >
                                <span className="block text-sm font-black text-red-800">
                                    {errorLabel(key)}
                                </span>
                                <span className="mt-1 block text-sm text-red-700">
                                    {String(message)}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>
            </Modal>
            <Modal
                open={requirementsOpen}
                onClose={() => setRequirementsOpen(false)}
                title="Persyaratan Dokumen SPR"
                size="xl"
                footer={
                    <Button
                        type="button"
                        onClick={() => setRequirementsOpen(false)}
                    >
                        <CheckSquare2 size={17} /> Terapkan Pilihan
                    </Button>
                }
            >
                <div className="grid gap-4">
                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                        Centang dokumen yang akan dipakai untuk pengajuan ini.
                        Dokumen tidak dipindahkan atau digandakan; SPR hanya
                        menyimpan hubungan ke file di repository customer.
                    </div>
                    {currentBerkas.map((berkas, index) => {
                        const availableDocuments = repositoryDocuments.filter(
                            (item) =>
                                Number(item.customer_id) ===
                                    Number(form.data.costumer_id) &&
                                Number(item.document_type_id) ===
                                    Number(berkas.dokumen_costumer_id),
                        );
                        const repositoryOptions = availableDocuments.map(
                            (item) => ({
                                value: String(item.id),
                                label: `${item.file_name} (versi ${item.version})`,
                            }),
                        );
                        return (
                            <div
                                key={berkas.dokumen_costumer_id}
                                className={`grid gap-4 rounded-xl border p-4 ${berkas.selected ? "border-emerald-300 bg-emerald-50/60" : "border-silver-deep/60 bg-white"}`}
                            >
                                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <label className="flex cursor-pointer items-start gap-3">
                                        <input
                                            className="mt-1 h-5 w-5 accent-emerald-600"
                                            type="checkbox"
                                            checked={Boolean(berkas.selected)}
                                            onChange={(event) =>
                                                updateBerkasRow(index, {
                                                    selected:
                                                        event.target.checked,
                                                })
                                            }
                                        />
                                        <span>
                                            <span className="block font-black text-ink">
                                                {berkas.dokumen_label}
                                            </span>
                                            <span className="mt-1 block text-xs font-bold text-ink-soft">
                                                {berkas.required
                                                    ? "Wajib untuk metode ini"
                                                    : "Dokumen tambahan"}
                                            </span>
                                        </span>
                                    </label>
                                    <span
                                        className={`rounded-full px-3 py-1 text-xs font-black ${repositoryOptions.length ? "bg-emerald-100 text-emerald-800" : "bg-amber-100 text-amber-800"}`}
                                    >
                                        {repositoryOptions.length
                                            ? `${repositoryOptions.length} file tersedia`
                                            : "Belum ada di repository"}
                                    </span>
                                </div>
                                <div className="grid gap-3 lg:grid-cols-2">
                                    <div className="grid gap-2">
                                        <span className="text-sm font-extrabold">
                                            Pilih dari Repositori
                                        </span>
                                        <Dropdown
                                            value={String(
                                                berkas.customer_document_id ??
                                                    "",
                                            )}
                                            options={repositoryOptions}
                                            disabled={!repositoryOptions.length}
                                            onChange={(value) =>
                                                updateBerkasRow(index, {
                                                    customer_document_id: value,
                                                    file_upload: null,
                                                    file_name: "",
                                                    selected: true,
                                                })
                                            }
                                        />
                                    </div>
                                    <label className="grid gap-2 text-sm font-extrabold">
                                        <span>Atau Unggah Berkas Baru</span>
                                        <input
                                            className="min-h-11 rounded-lg border border-silver-deep/70 bg-white px-3 py-2 file:mr-3 file:rounded-md file:border-0 file:bg-ink file:px-3 file:py-2 file:font-bold file:text-white"
                                            type="file"
                                            onChange={(event) => {
                                                const file =
                                                    event.target.files?.[0] ??
                                                    null;
                                                updateBerkasRow(index, {
                                                    customer_document_id: "",
                                                    file_upload: file,
                                                    file_name: file?.name ?? "",
                                                    selected: Boolean(file),
                                                });
                                            }}
                                        />
                                        {berkas.file_name && (
                                            <span className="text-xs text-emerald-700">
                                                File baru: {berkas.file_name}
                                            </span>
                                        )}
                                    </label>
                                </div>
                                <Input
                                    label="Keterangan"
                                    value={berkas.keterangan}
                                    onChange={(event) =>
                                        updateBerkasRow(index, {
                                            keterangan: event.target.value,
                                        })
                                    }
                                />
                            </div>
                        );
                    })}
                    <a
                        className="text-sm font-black text-blue-700 underline"
                        href={`/admin/repository-dokumen-customer?customer=${form.data.costumer_id}`}
                        target="_blank"
                        rel="noreferrer"
                    >
                        Buka Repositori Dokumen Pelanggan di halaman baru
                    </a>
                </div>
            </Modal>
            <div className="grid gap-6">
                <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-ink via-graphite to-[#27323b] p-6 text-white shadow-lg">
                    <div className="absolute -right-16 -top-24 h-56 w-56 rounded-full bg-gold/15 blur-2xl" />
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="relative">
                            <p className="text-[11px] font-black uppercase tracking-[0.18em] text-champagne">
                                Marketing · Surat Pemesanan Rumah
                            </p>
                            <h1 className="mt-2 text-2xl font-black md:text-3xl">
                                {title}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm leading-6 text-white/65">
                                {description}
                            </p>
                        </div>
                        <Button
                            as="a"
                            href={baseUrl}
                            variant="outline"
                            className="relative border-white/20 bg-white/10 text-white hover:bg-white/20"
                        >
                            <ArrowLeft size={16} /> Kembali
                        </Button>
                    </div>
                </section>

                <form className="grid gap-5" onSubmit={submit}>
                    {validationErrors.length > 0 && (
                        <div className="sticky top-3 z-40 flex flex-col gap-3 rounded-xl border border-red-300 bg-red-50 p-4 shadow-lg md:flex-row md:items-center md:justify-between">
                            <div className="flex gap-3 text-red-800">
                                <AlertTriangle
                                    className="mt-0.5 shrink-0"
                                    size={20}
                                />
                                <div>
                                    <p className="font-black">
                                        SPR belum dapat disimpan.
                                    </p>
                                    <p className="text-sm">
                                        Ada {validationErrors.length} error.
                                        Field bermasalah ditandai merah di bawah
                                        input.
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                className="border-red-300 text-red-700"
                                onClick={() => setValidationModalOpen(true)}
                            >
                                Lihat Rincian Error
                            </Button>
                        </div>
                    )}
                    <div className="grid items-start gap-5 xl:grid-cols-[1.65fr_0.65fr]">
                        <section className="rounded-2xl border border-white/80 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                            <SectionTitle
                                title="Data SPR"
                                description="Lengkapi data transaksi, penambahan, dan termin."
                            />
                            <div className="mt-4">{fieldsContent}</div>
                            <div className="mt-6">
                                <SectionTitle
                                    title="Berkas Pelanggan"
                                    description="Setiap jenis dokumen diambil dari master dokument pelanggan."
                                />
                                <div className="mt-4">{berkasContent}</div>
                            </div>
                        </section>
                        <section className="rounded-2xl border border-white/80 bg-white/85 p-5 shadow-soft xl:sticky xl:top-5 dark:border-white/10 dark:bg-white/8">
                            <SectionTitle
                                title="Ringkasan SPR"
                                description="Lihat ringkasan pelanggan, unit, dan total akhir."
                            />
                            <div className="mt-4">{summaryContent}</div>
                        </section>
                    </div>

                    <div className="sticky bottom-0 z-20 flex flex-wrap justify-end gap-3 border-t border-silver-deep/60 bg-white/95 px-5 py-4 backdrop-blur dark:border-white/10 dark:bg-graphite/95">
                        <Button
                            as="a"
                            href={baseUrl}
                            variant="outline"
                            type="button"
                        >
                            <XCircle size={17} /> Batal
                        </Button>
                        <Button disabled={form.processing} type="submit">
                            <Save size={17} />{" "}
                            {form.processing
                                ? "Menyimpan..."
                                : mode === "edit"
                                  ? "Simpan Perubahan"
                                  : "Simpan SPR"}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

SprForm.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "SPR"}>{page}</AdminLayout>
);
