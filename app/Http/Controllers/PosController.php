<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\ReceiptSetting;
use App\Models\StockMutation;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $activeShift = CashierShift::where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        $categories = Category::withCount('products')->get();

        $products = Product::with(['category', 'rawMaterial'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $customers = Customer::orderBy('name')->get();
        $receiptSettings = ReceiptSetting::getActive();

        // Calculate next invoice number preview
        $todayDate = date('Ymd');
        $todayOrderCount = Order::whereDate('created_at', Carbon::today())->count() + 1;
        $nextInvoice = sprintf('INV-%s-%04d', $todayDate, $todayOrderCount);

        return Inertia::render('Pos/Index', [
            'activeShift' => $activeShift,
            'categories' => $categories,
            'products' => $products,
            'customers' => $customers,
            'receiptSettings' => $receiptSettings,
            'nextInvoice' => $nextInvoice,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $activeShift = CashierShift::where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if (! $activeShift) {
            return back()->with('error', 'Anda belum membuka Shift Kasir! Buka shift terlebih dahulu sebelum bertransaksi.');
        }

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'required|string',
            'items.*.pricing_type' => 'required|string|in:fixed,dimension_sqm',
            'items.*.unit' => 'required|string',
            'items.*.length' => 'nullable|numeric|min:0',
            'items.*.width' => 'nullable|numeric|min:0',
            'items.*.area' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.finishing_option' => 'nullable|string',
            'items.*.finishing_price' => 'nullable|numeric|min:0',
            'items.*.cost_price' => 'nullable|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            'items.*.design_note' => 'nullable|string',
            'subtotal' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'change_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|in:cash,transfer,qris,tempo',
            'order_status' => 'nullable|string|in:pending,processing,ready,completed',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $user, $activeShift) {
            $todayDate = date('Ymd');
            $todayOrderCount = Order::whereDate('created_at', Carbon::today())->count() + 1;
            $invoiceNumber = sprintf('INV-%s-%04d', $todayDate, $todayOrderCount);

            $grandTotal = (float) $validated['grand_total'];
            $paidAmount = (float) $validated['paid_amount'];

            // Determine payment status
            $paymentStatus = 'paid';
            if ($validated['payment_method'] === 'tempo' || $paidAmount < $grandTotal) {
                $paymentStatus = $paidAmount > 0 ? 'partial' : 'unpaid';
            }

            // Customer info
            $customerName = $validated['customer_name'] ?? 'Pelanggan Umum';
            $customerPhone = $validated['customer_phone'] ?? '-';
            if (! empty($validated['customer_id'])) {
                $customer = Customer::find($validated['customer_id']);
                if ($customer) {
                    $customerName = $customer->name;
                    $customerPhone = $customer->phone ?? '-';
                    $customer->increment('total_spent', $grandTotal);
                }
            }

            // Order status default
            $orderStatus = $validated['order_status'] ?? 'completed';

            // Create Order
            $order = Order::create([
                'invoice_number' => $invoiceNumber,
                'cashier_shift_id' => $activeShift->id,
                'user_id' => $user->id,
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'subtotal' => $validated['subtotal'],
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'tax_amount' => $validated['tax_amount'] ?? 0,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'change_amount' => $validated['change_amount'] ?? 0,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
                'order_status' => $orderStatus,
                'due_date' => $validated['due_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create items and handle stock deduction
            foreach ($validated['items'] as $itemData) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $itemData['product_id'] ?? null,
                    'product_name' => $itemData['product_name'],
                    'pricing_type' => $itemData['pricing_type'],
                    'unit' => $itemData['unit'],
                    'length' => $itemData['length'] ?? null,
                    'width' => $itemData['width'] ?? null,
                    'area' => $itemData['area'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'finishing_option' => $itemData['finishing_option'] ?? null,
                    'finishing_price' => $itemData['finishing_price'] ?? 0,
                    'cost_price' => $itemData['cost_price'] ?? 0,
                    'subtotal' => $itemData['subtotal'],
                    'design_note' => $itemData['design_note'] ?? null,
                ]);

                // Stock reduction logic
                if (! empty($itemData['product_id'])) {
                    $product = Product::with('rawMaterial')->find($itemData['product_id']);
                    if ($product) {
                        // If it's a fixed product with managed stock
                        if ($product->is_stock_managed && $product->stock > 0) {
                            $oldStock = $product->stock;
                            $newStock = max(0, $oldStock - $itemData['quantity']);
                            $product->update(['stock' => $newStock]);

                            StockMutation::create([
                                'item_type' => 'product',
                                'item_id' => $product->id,
                                'item_name' => $product->name,
                                'type' => 'out',
                                'quantity' => $itemData['quantity'],
                                'previous_stock' => $oldStock,
                                'current_stock' => $newStock,
                                'reference_type' => 'pos_order',
                                'reference_id' => $order->id,
                                'reason' => "Penjualan Kasir POS {$invoiceNumber}",
                                'user_id' => $user->id,
                            ]);
                        }

                        // If it's linked to raw material (bahan baku), reduce raw material stock
                        if ($product->rawMaterial) {
                            $raw = $product->rawMaterial;
                            $usedQty = ($product->pricing_type === 'dimension_sqm' && ! empty($itemData['area']))
                                ? ((float) $itemData['area'] * (float) $itemData['quantity'])
                                : (float) $itemData['quantity'];

                            $oldRawStock = $raw->stock;
                            $newRawStock = max(0, $oldRawStock - $usedQty);
                            $raw->update(['stock' => $newRawStock]);

                            StockMutation::create([
                                'item_type' => 'raw_material',
                                'item_id' => $raw->id,
                                'item_name' => $raw->name,
                                'type' => 'out',
                                'quantity' => $usedQty,
                                'previous_stock' => $oldRawStock,
                                'current_stock' => $newRawStock,
                                'reference_type' => 'pos_order',
                                'reference_id' => $order->id,
                                'reason' => "Pemakaian Bahan Cetak {$invoiceNumber} ({$product->name})",
                                'user_id' => $user->id,
                            ]);
                        }
                    }
                }
            }

            // Update Shift cash / non_cash
            $receivedNow = min($paidAmount, $grandTotal);
            if ($validated['payment_method'] === 'cash') {
                $activeShift->increment('cash_sales', $receivedNow);
                $activeShift->increment('expected_cash', $receivedNow);
            } else {
                $activeShift->increment('non_cash_sales', $receivedNow);
            }

            return redirect()->route('orders.show', $order->id)->with('success', "Transaksi {$invoiceNumber} berhasil disimpan!");
        });
    }

    public function quickCustomer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'customer_type' => 'required|string|in:regular,member,reseller,corporate',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        Customer::create($validated);

        return back()->with('success', "Pelanggan {$validated['name']} berhasil ditambahkan!");
    }
}
