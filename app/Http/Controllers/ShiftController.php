<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $activeShift = CashierShift::with('user')
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        $perPage = min(max($request->integer('per_page', 15), 5), 100);
        $allShifts = CashierShift::with('user')
            ->latest('opened_at')
            ->paginate($perPage)
            ->withQueryString();

        $currentUserId = $user->id;
        $users = User::withCount(['orders', 'shifts', 'stockMutations', 'orderReturns', 'payments'])
            ->orderBy('name')
            ->get()
            ->map(function (User $u) use ($currentUserId) {
                $totalData = (int) ($u->orders_count + $u->shifts_count + $u->stock_mutations_count + $u->order_returns_count + $u->payments_count);
                $u->has_data = $totalData > 0;
                $u->total_data = $totalData;
                $u->is_current_user = ($u->id === $currentUserId);
                $u->can_delete = ($totalData === 0 && ! $u->is_current_user);

                return $u;
            });

        return Inertia::render('Shifts/Index', [
            'activeShift' => $activeShift,
            'shifts' => $allShifts,
            'users' => $users,
            'filters' => $request->only(['per_page']),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();

        $existing = CashierShift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return back()->with('error', 'Anda masih memiliki Shift Kasir yang sedang aktif.');
        }

        $validated = $request->validate([
            'starting_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        CashierShift::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'starting_cash' => $validated['starting_cash'],
            'cash_sales' => 0,
            'non_cash_sales' => 0,
            'expected_cash' => $validated['starting_cash'],
            'status' => 'open',
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Shift Kasir berhasil dibuka! Selamat bertugas.');
    }

    public function close(Request $request, CashierShift $shift): RedirectResponse
    {
        $user = $request->user();

        if ($shift->user_id !== $user->id && ! $user->isManager()) {
            return back()->with('error', 'Hanya kasir yang bersangkutan atau Manager/Admin yang dapat menutup shift ini.');
        }

        if ($shift->status === 'closed') {
            return back()->with('error', 'Shift ini sudah ditutup sebelumnya.');
        }

        $validated = $request->validate([
            'closing_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $closingCash = (float) $validated['closing_cash'];
        $expectedCash = (float) $shift->expected_cash;
        $difference = $closingCash - $expectedCash;

        $shift->update([
            'closed_at' => now(),
            'closing_cash' => $closingCash,
            'difference' => $difference,
            'status' => 'closed',
            'notes' => ($shift->notes ? $shift->notes . "\n" : '') . ($validated['notes'] ?? ''),
        ]);

        $diffMsg = $difference == 0
            ? 'Kas seimbang (tidak ada selisih).'
            : ($difference > 0
                ? 'Ada selisih LEBIH: Rp ' . number_format($difference, 0, ',', '.')
                : 'Ada selisih KURANG: Rp ' . number_format(abs($difference), 0, ',', '.'));

        return back()->with('success', "Shift Kasir berhasil ditutup! {$diffMsg}");
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:admin,kasir,manager,desainer',
            'phone' => 'nullable|string|max:50',
        ], [
            'name.required' => 'Nama lengkap karyawan wajib diisi.',
            'email.required' => 'Email login wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar untuk akun lain. Silakan gunakan alamat email berbeda.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal harus 6 karakter.',
            'role.required' => 'Peran / Role wajib dipilih.',
            'role.in' => 'Peran yang dipilih tidak valid.',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return back()->with('success', "Karyawan {$validated['name']} ({$validated['role']}) berhasil ditambahkan!");
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|string|in:admin,kasir,manager,desainer',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'required|boolean',
        ], [
            'name.required' => 'Nama lengkap karyawan wajib diisi.',
            'email.required' => 'Email login wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar untuk akun lain. Silakan gunakan alamat email berbeda.',
            'password.min' => 'Password minimal harus 6 karakter jika ingin diubah.',
            'role.required' => 'Peran / Role wajib dipilih.',
            'role.in' => 'Peran yang dipilih tidak valid.',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'],
        ];

        // Pastikan akun sendiri yang sedang login tidak dapat dinonaktifkan
        if ($user->id === $request->user()->id) {
            $updateData['is_active'] = true;
        }

        if (! empty($validated['password'])) {
            $updateData['password'] = $validated['password'];
        }

        $user->update($updateData);

        return back()->with('success', "Data karyawan {$user->name} berhasil diperbarui.");
    }

    public function switchUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);

        if (! $targetUser->is_active) {
            return back()->with('error', "Akun karyawan {$targetUser->name} sedang nonaktif dan tidak dapat digunakan.");
        }

        Auth::login($targetUser);

        return redirect()->route('pos.index')->with('success', "Beralih akun ke {$targetUser->name} ({$targetUser->role}).");
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun yang sedang Anda gunakan saat ini.');
        }

        $hasData = $user->orders()->exists()
            || $user->shifts()->exists()
            || $user->stockMutations()->exists()
            || $user->orderReturns()->exists()
            || $user->payments()->exists();

        if ($hasData) {
            return back()->with('error', "Karyawan {$user->name} tidak dapat dihapus karena sudah memiliki riwayat data/transaksi.");
        }

        $userName = $user->name;
        $user->delete();

        return back()->with('success', "Akun karyawan {$userName} berhasil dihapus.");
    }
}
