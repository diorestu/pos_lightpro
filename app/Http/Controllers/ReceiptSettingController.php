<?php

namespace App\Http\Controllers;

use App\Models\ReceiptSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptSettingController extends Controller
{
    public function index(): Response
    {
        $settings = ReceiptSetting::getActive();

        return Inertia::render('ReceiptSettings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'footer_text' => 'nullable|string',
            'paper_size' => 'required|string|in:58mm,80mm,A5',
            'show_logo' => 'boolean',
            'show_cashier_name' => 'boolean',
            'show_customer_info' => 'boolean',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'remove_logo' => 'nullable|boolean',
        ]);

        $settings = ReceiptSetting::getActive();

        if ($request->boolean('remove_logo')) {
            if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $validated['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $path = $request->file('logo')->store('receipt_logos', 'public');
            $validated['logo_path'] = $path;
        }

        unset($validated['logo'], $validated['remove_logo']);

        $settings->update($validated);

        return back()->with('success', 'Pengaturan nota percetakan & logo berhasil diperbarui!');
    }
}
