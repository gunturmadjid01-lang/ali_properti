<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function loginForm(Request $request): Response|RedirectResponse
    {
        return redirect()->route('attendance.index');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pin' => ['required', 'digits:8'],
        ]);
        $user = User::query()
            ->whereNotNull('employee_number')
            ->whereRaw('LOWER(name) = LOWER(?)', [trim($data['name'])])
            ->get()
            ->first(fn (User $employee) => $employee->attendance_pin && Hash::check($data['pin'], $employee->attendance_pin));

        if (! $user || $user->employment_status !== 'aktif') {
            return back()->withErrors(['name' => 'Nama lengkap atau PIN absensi tidak cocok dengan data pegawai aktif.'])->onlyInput('name');
        }

        $request->session()->regenerate();
        $request->session()->put('attendance_user_id', $user->id);
        return redirect()->route('attendance.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('attendance_user_id');
        $request->session()->regenerateToken();
        return redirect()->route('attendance.index');
    }

    public function index(Request $request): Response
    {
        $user = $this->attendanceUser($request);
        if (! $user) {
            return Inertia::render('Attendance/Login');
        }
        $user->loadMissing('kantorCabang');
        $branch = $user->kantorCabang;

        return Inertia::render('Attendance/Index', [
            'employee' => ['name' => $user->name, 'employee_number' => $user->employee_number],
            'branch' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->nama_cabang,
                'address' => $branch->address,
                'latitude' => $branch->latitude !== null ? (float) $branch->latitude : null,
                'longitude' => $branch->longtitude !== null ? (float) $branch->longtitude : null,
                'radius' => (int) $branch->attendance_radius_meters,
                'ready' => $branch->record_status === 'locked' && $branch->status === 'aktif' && $branch->latitude !== null && $branch->longtitude !== null,
            ] : null,
            'today' => AttendanceRecord::query()->where('user_id', $user->id)
                ->whereDate('attendance_date', today())->orderBy('recorded_at')->get()
                ->map(fn (AttendanceRecord $record) => [
                    'id' => $record->id,
                    'type' => $record->type,
                    'time' => $record->recorded_at->format('H:i'),
                    'distance' => round((float) $record->distance_meters),
                    'status' => $record->record_status,
                    'within_radius' => $record->is_within_radius,
                    'time_status' => $record->time_status,
                    'latitude' => (float) $record->latitude,
                    'longitude' => (float) $record->longitude,
                    'accuracy' => $record->accuracy_meters,
                    'photo_url' => route('media', ['path' => $record->photo_path]),
                ]),
            'schedule' => AttendanceSetting::query()->where('cabang_perusahaan_id', $branch?->id)->where('is_active', true)->first()?->only(['check_in_time', 'check_out_time', 'late_tolerance_minutes', 'checkout_tolerance_minutes', 'work_days']),
        ]);
    }

    public function store(Request $request, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['check_in', 'check_out'])],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'photo' => ['required', 'image', 'max:5120'],
            'outside_radius_confirmed' => ['nullable', 'boolean'],
        ]);

        $user = $this->attendanceUser($request);
        abort_unless($user, 403, 'Sesi absensi tidak valid. Silakan masuk kembali.');
        $user->loadMissing('kantorCabang');
        $alreadyRecorded = AttendanceRecord::query()->where('user_id', $user->id)
            ->whereDate('attendance_date', today())->where('type', $data['type'])->exists();
        throw_if($alreadyRecorded, ValidationException::withMessages(['type' => 'Jenis absensi ini sudah direkam hari ini.']));
        if ($data['type'] === 'check_out') {
            $hasCheckedIn = AttendanceRecord::query()->where('user_id', $user->id)
                ->whereDate('attendance_date', today())->where('type', 'check_in')->exists();
            throw_unless($hasCheckedIn, ValidationException::withMessages(['type' => 'Absen masuk harus direkam sebelum absen pulang.']));
        }
        $branch = $user->kantorCabang;
        abort_unless($branch, 422, 'Pegawai belum ditautkan ke kantor cabang. Hubungi admin.');
        abort_unless($branch->record_status === 'locked' && $branch->status === 'aktif', 422, 'Lokasi kantor belum aktif/final. Hubungi admin.');
        abort_if($branch->latitude === null || $branch->longtitude === null, 422, 'Koordinat kantor belum diatur. Hubungi admin.');

        $distance = $this->distanceMeters((float) $branch->latitude, (float) $branch->longtitude, (float) $data['latitude'], (float) $data['longitude']);
        $withinRadius = $distance <= (int) $branch->attendance_radius_meters;
        if (! $withinRadius && ! $request->boolean('outside_radius_confirmed')) {
            throw ValidationException::withMessages(['outside_radius_confirmed' => 'Anda berada di luar radius. Pilih Lanjutkan jika tetap ingin merekam absensi.']);
        }
        $recordedAt = now();
        $setting = AttendanceSetting::query()->where('cabang_perusahaan_id', $branch->id)->where('is_active', true)->first();
        $timeStatus = 'on_time';
        $difference = null;
        if ($setting) {
            $scheduled = $recordedAt->copy()->setTimeFromTimeString($data['type'] === 'check_in' ? $setting->check_in_time : $setting->check_out_time);
            $difference = $scheduled->diffInMinutes($recordedAt, false);
            if ($data['type'] === 'check_in' && $difference > (int) $setting->late_tolerance_minutes) $timeStatus = 'late';
            if ($data['type'] === 'check_out' && $difference < -((int) $setting->checkout_tolerance_minutes)) $timeStatus = 'early_leave';
            if ($data['type'] === 'check_out' && $difference > (int) $setting->checkout_tolerance_minutes) $timeStatus = 'late_leave';
        }

        $record = DB::transaction(function () use ($request, $data, $user, $branch, $distance, $withinRadius, $recordedAt, $timeStatus, $difference) {
            $record = AttendanceRecord::create([
                'user_id' => $user->id,
                'cabang_perusahaan_id' => $branch->id,
                'attendance_date' => today(),
                'type' => $data['type'],
                'recorded_at' => $recordedAt,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'accuracy_meters' => isset($data['accuracy_meters']) ? round((float) $data['accuracy_meters']) : null,
                'distance_meters' => $distance,
                'is_within_radius' => $withinRadius,
                'outside_radius_confirmed' => ! $withinRadius && $request->boolean('outside_radius_confirmed'),
                'time_status' => $timeStatus,
                'schedule_difference_minutes' => $difference,
                'photo_path' => $request->file('photo')->store('attendance/'.today()->format('Y/m'), 'public'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'record_status' => 'locked',
                'locked_at' => now(),
                'locked_by' => $user->id,
            ]);

            return $record;
        });

        $approval = $workflow->submitLocked($record, 'attendance');
        return back()->with('success', ($data['type'] === 'check_in' ? 'Absen masuk' : 'Absen pulang').' berhasil direkam. Status approval: '.($approval->status === 'approved' ? 'disetujui' : 'menunggu review').'.');
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function attendanceUser(Request $request): ?User
    {
        return User::query()->find($request->session()->get('attendance_user_id'));
    }
}
