<?php

namespace App\Http\Controllers\Admin\Management\KelompokHpp;

use App\Http\Controllers\Controller;
use App\Models\KelompokHpp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KelompokHppController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = KelompokHpp::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->orWhere('nama_hpp', 'like', "%{$search}%")
                        ->orWhere('kategori', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('kategori')
            ->orderBy('nama_hpp')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (KelompokHpp $row) => [
                ...$row->toArray(),
                'kategori_label' => $row->kategori_label,
            ]);

        return Inertia::render('Admin/Management/KelompokHpp/Index', [
            'title' => 'Management HPP',
            'description' => 'Daftar kelompok HPP standar sistem untuk perumahan, rumah, logistik, dan realisasi biaya.',
            'baseUrl' => route('admin.management.kelompok-hpp.index', absolute: false),
            'routeName' => 'admin.management.kelompok-hpp',
            'filters' => ['search' => $search],
            'rows' => $rows,
            'columns' => [
                ['key' => 'nama_hpp', 'label' => 'Nama HPP'],
                ['key' => 'kategori_label', 'label' => 'Kategori'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'fields' => [],
            'options' => [],
        ]);
    }
}
