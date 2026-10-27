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

        $users = User::orderBy('name')->get();

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

    public function switchUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        Auth::login($targetUser);

        return redirect()->route('pos.index')->with('success', "Beralih akun ke {$targetUser->name} ({$targetUser->role}).");
    }
}
