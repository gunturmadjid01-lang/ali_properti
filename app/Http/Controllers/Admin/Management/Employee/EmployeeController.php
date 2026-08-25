<?php

namespace App\Http\Controllers\Admin\Management\Employee;

use App\Http\Controllers\Admin\Management\User\UserController;
use Illuminate\Http\RedirectResponse;

class EmployeeController extends UserController
{
    protected function routeName(): string
    {
        return 'admin.management.employee';
    }

    protected function permissionKey(): string
    {
        return 'employee';
    }

    protected function defaultSection(): string
    {
        return 'pegawai';
    }

    protected function title(): string
    {
        return 'Data Pegawai';
    }

    protected function description(): string
    {
        return 'Kelola identitas, jabatan, status kerja, BPJS, pajak, dan rekening penggajian pegawai.';
    }

    protected function tabs(): array
    {
        return [];
    }

    public function lock(string $id): RedirectResponse
    {
        $this->authorizeAction(request(), 'update');

        return parent::lock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        $this->authorizeAction(request(), 'update');

        return parent::unlock($id);
    }
}
