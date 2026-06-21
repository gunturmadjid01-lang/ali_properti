import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value ?? 0));

export default function Show({ title, receipt }) {
    return (
        <>
            <Head title={title} />
            <div className="print-shell grid gap-5">
                <div className="print-hidden flex flex-wrap items-center justify-between gap-3">
                    <Button as={Link} href="/admin/marketing/operasional/piutang" variant="outline">
                        <ArrowLeft size={16} /> Kembali
                    </Button>
                    <Button type="button" onClick={() => window.print()}>
                        <Printer size={16} /> Cetak Kwitansi
                    </Button>
                </div>

                <article className="receipt-sheet mx-auto w-full max-w-4xl border border-silver-deep bg-white p-8 text-ink shadow-soft md:p-12">
                    <header className="flex flex-col gap-5 border-b-[3px] border-ink pb-6 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.2em] text-ink-soft">PT Ali Properti Indonesia</p>
                            <h1 className="mt-2 font-display text-3xl font-extrabold tracking-[0.12em]">KWITANSI</h1>
                        </div>
                        <div className="text-left sm:text-right">
                            <p className="font-extrabold">{receipt.number}</p>
                            <p className="mt-1 text-sm text-ink-soft">{receipt.date}</p>
                        </div>
                    </header>

                    <dl className="mt-8 grid gap-x-8 gap-y-5 sm:grid-cols-[190px_1fr]">
                        <dt className="font-extrabold text-ink-soft">Telah diterima dari</dt>
                        <dd className="font-bold">{receipt.customer}</dd>
                        <dt className="font-extrabold text-ink-soft">Untuk pembayaran</dt>
                        <dd>{receipt.type} - {receipt.spr}</dd>
                        <dt className="font-extrabold text-ink-soft">Rekening penerima</dt>
                        <dd>{receipt.bank}</dd>
                        <dt className="font-extrabold text-ink-soft">Keterangan</dt>
                        <dd>{receipt.note || '-'}</dd>
                    </dl>

                    <div className="mt-9 rounded-xl bg-silver-soft px-6 py-5 text-2xl font-extrabold">
                        {money(receipt.amount)}
                    </div>

                    <footer className="mt-16 flex justify-end">
                        <div className="w-56 text-center">
                            <p>Penerima,</p>
                            <div className="h-24" />
                            <div className="border-t border-ink pt-2">Petugas Keuangan</div>
                        </div>
                    </footer>
                </article>
            </div>
        </>
    );
}

Show.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Kwitansi'}>{page}</AdminLayout>;
