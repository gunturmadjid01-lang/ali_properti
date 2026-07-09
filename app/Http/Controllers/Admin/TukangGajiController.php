<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tukang;
use App\Models\TukangGaji;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TukangGajiController extends Controller
{
    public function index(Tukang $tukang): Response
    {
        $this->authorizeAction('view');

        $gajis = $tukang->daftarGaji()
            ->get()
            ->map(fn (TukangGaji $gaji) => [
                'id' => $gaji->id,
                'nominal' => (string) $gaji->nominal,
                'tanggal_berlaku' => $gaji->tanggal_berlaku?->format('Y-m-d'),
                'tanggal_berlaku_label' => $gaji->tanggal_berlaku?->translatedFormat('d F Y'),
                'status' => $gaji->status,
                'status_label' => $gaji->status === 'aktif' ? 'Aktif' : 'Tidak Aktif',
            ]);

        return Inertia::render('Admin/Tukang/Gaji', [
            'title' => 'Gaji Tukang',
            'baseUrl' => route('admin.tukang.gaji.index', $tukang, false),
            'tukang' => [
                'id' => $tukang->id,
                'nama' => $tukang->nama,
                'alamat' => $tukang->alamat,
                'posisi_label' => Tukang::POSITIONS[$tukang->posisi] ?? $tukang->posisi,
            ],
            'gajis' => $gajis,
            'statusOptions' => [
                ['value' => 'aktif', 'label' => 'Aktif'],
                ['value' => 'nonaktif', 'label' => 'Tidak Aktif'],
            ],
        ]);
    }

    public function store(Request $request, Tukang $tukang): RedirectResponse
    {
        $this->authorizeAction('update');
        $payload = $this->payload($request);

        DB::transaction(function () use ($tukang, $payload): void {
            if ($payload['status'] === 'aktif') {
                $tukang->daftarGaji()->where('status', 'aktif')->update([
                    'status' => 'nonaktif',
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);
            }

            $tukang->daftarGaji()->create([
                ...$payload,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Gaji tukang berhasil ditambahkan.');
    }

    public function update(Request $request, Tukang $tukang, TukangGaji $gaji): RedirectResponse
    {
        $this->authorizeAction('update');
        $this->ensureOwnedBy($tukang, $gaji);
        $payload = $this->payload($request);

        DB::transaction(function () use ($tukang, $gaji, $payload): void {
            if ($payload['status'] === 'aktif') {
                $tukang->daftarGaji()
                    ->whereKeyNot($gaji->id)
                    ->where('status', 'aktif')
                    ->update([
                        'status' => 'nonaktif',
                        'updated_by' => auth()->id(),
                        'updated_at' => now(),
                    ]);
            }

            $gaji->update([
                ...$payload,
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Gaji tukang berhasil diperbarui.');
    }

    public function activate(Tukang $tukang, TukangGaji $gaji): RedirectResponse
    {
        $this->authorizeAction('update');
        $this->ensureOwnedBy($tukang, $gaji);

        DB::transaction(function () use ($tukang, $gaji): void {
            $tukang->daftarGaji()->where('status', 'aktif')->update([
                'status' => 'nonaktif',
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);
            $gaji->update([
                'status' => 'aktif',
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Gaji aktif berhasil diganti.');
    }

    public function destroy(Tukang $tukang, TukangGaji $gaji): RedirectResponse
    {
        $this->authorizeAction('delete');
        $this->ensureOwnedBy($tukang, $gaji);
        $gaji->delete();

        return back()->with('success', 'Riwayat gaji berhasil dihapus.');
    }

    private function payload(Request $request): array
    {
        return $request->validate([
            'nominal' => ['required', 'numeric', 'min:0', 'max:99999999999999.99'],
            'tanggal_berlaku' => ['required', 'date'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [], [
            'nominal' => 'Nominal gaji',
            'tanggal_berlaku' => 'Tanggal berlaku',
            'status' => 'Status gaji',
        ]);
    }

    private function ensureOwnedBy(Tukang $tukang, TukangGaji $gaji): void
    {
        abort_unless($gaji->tukang_id === $tukang->id, 404);
    }

    private function authorizeAction(string $action): void
    {
        abort_unless(auth()->user()?->can("tukang.{$action}"), 403);
    }
}
