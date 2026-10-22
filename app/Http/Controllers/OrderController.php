<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderReturn;
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

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Order::with(['user', 'customer', 'items', 'return']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        // Filter date
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Filter status
        if ($orderStatus = $request->input('order_status')) {
            $query->where('order_status', $orderStatus);
        }

        // Filter payment status
        if ($paymentStatus = $request->input('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        // Filter payment method
        if ($paymentMethod = $request->input('payment_method')) {
            $query->where('payment_method', $paymentMethod);
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'date_from', 'date_to', 'order_status', 'payment_status', 'payment_method']),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['user', 'customer', 'items.product', 'return.user', 'cancelledByUser', 'payments.user', 'payments.shift']);
        $receiptSettings = ReceiptSetting::getActive();

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'receiptSettings' => $receiptSettings,
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        if ($order->order_status === 'cancelled') {
            return back()->with('error', 'Pesanan ini sudah dibatalkan sebelumnya.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'restore_stock' => 'boolean',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($order, $validated, $user) {
            $restoreStock = $validated['restore_stock'] ?? true;

            if ($restoreStock) {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        $product = Product::with('rawMaterial')->find($item->product_id);
                        if ($product) {
                            if ($product->is_stock_managed) {
                                $oldStock = $product->stock;
                                $newStock = $oldStock + $item->quantity;
                                $product->update(['stock' => $newStock]);

                                StockMutation::create([
                                    'item_type' => 'product',
                                    'item_id' => $product->id,
                                    'item_name' => $product->name,
                                    'type' => 'in',
                                    'quantity' => $item->quantity,
                                    'previous_stock' => $oldStock,
                                    'current_stock' => $newStock,
                                    'reference_type' => 'order_cancelled',
                                    'reference_id' => $order->id,
                                    'reason' => "Pembatalan Order {$order->invoice_number}: {$validated['reason']}",
                                    'user_id' => $user->id,
                                ]);
                            }

                            if ($product->rawMaterial) {
                                $raw = $product->rawMaterial;
                                $restoredQty = ($product->pricing_type === 'dimension_sqm' && ! empty($item->area))
                                    ? ((float) $item->area * (float) $item->quantity)
                                    : (float) $item->quantity;

                                $oldRaw = $raw->stock;
                                $newRaw = $oldRaw + $restoredQty;
                                $raw->update(['stock' => $newRaw]);

                                StockMutation::create([
                                    'item_type' => 'raw_material',
                                    'item_id' => $raw->id,
                                    'item_name' => $raw->name,
                                    'type' => 'in',
                                    'quantity' => $restoredQty,
                                    'previous_stock' => $oldRaw,
                                    'current_stock' => $newRaw,
                                    'reference_type' => 'order_cancelled',
                                    'reference_id' => $order->id,
                                    'reason' => "Pengembalian Bahan (Batal {$order->invoice_number})",
                                    'user_id' => $user->id,
                                ]);
                            }
                        }
                    }
                }
            }

            $order->update([
                'order_status' => 'cancelled',
                'cancellation_reason' => $validated['reason'],
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
            ]);
        });

        return back()->with('success', "Order {$order->invoice_number} berhasil dibatalkan dan stok telah disesuaikan!");
    }

    public function returnOrder(Request $request, Order $order): RedirectResponse
    {
        if ($order->return) {
            return back()->with('error', 'Pesanan ini sudah pernah diretur.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'refund_amount' => 'required|numeric|min:0|max:' . $order->paid_amount,
            'restore_stock' => 'boolean',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($order, $validated, $user) {
            $todayDate = date('Ymd');
            $returnCount = OrderReturn::whereDate('created_at', Carbon::today())->count() + 1;
            $returnNumber = sprintf('RET-%s-%04d', $todayDate, $returnCount);

            $orderReturn = OrderReturn::create([
                'return_number' => $returnNumber,
                'order_id' => $order->id,
                'user_id' => $user->id,
                'refund_amount' => $validated['refund_amount'],
                'reason' => $validated['reason'],
                'restore_stock' => $validated['restore_stock'] ?? true,
            ]);

            if ($validated['restore_stock'] ?? true) {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        $product = Product::with('rawMaterial')->find($item->product_id);
                        if ($product && $product->is_stock_managed) {
                            $oldStock = $product->stock;
                            $newStock = $oldStock + $item->quantity;
                            $product->update(['stock' => $newStock]);

                            StockMutation::create([
                                'item_type' => 'product',
                                'item_id' => $product->id,
                                'item_name' => $product->name,
                                'type' => 'in',
                                'quantity' => $item->quantity,
                                'previous_stock' => $oldStock,
                                'current_stock' => $newStock,
                                'reference_type' => 'return',
                                'reference_id' => $orderReturn->id,
                                'reason' => "Retur {$returnNumber} ({$order->invoice_number}): {$validated['reason']}",
                                'user_id' => $user->id,
                            ]);
                        }
                    }
                }
            }
        });

        return back()->with('success', "Retur untuk nota {$order->invoice_number} berhasil diproses!");
    }

    public function updatePayment(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'order_payment_id' => 'nullable|exists:order_payments,id',
            'payment_method' => 'required|string|in:cash,transfer,qris',
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $payAmt = (float) $validated['amount'];
        $activeShift = CashierShift::where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        DB::transaction(function () use ($order, $validated, $user, $payAmt, $activeShift) {
            if (! empty($validated['order_payment_id'])) {
                $term = $order->payments()->findOrFail($validated['order_payment_id']);
                $newTermPaid = (float) $term->paid_amount + $payAmt;
                $term->update([
                    'paid_amount' => $newTermPaid,
                    'status' => $newTermPaid >= (float) $term->amount ? 'paid' : 'partial',
                    'paid_at' => now(),
                    'payment_method' => $validated['payment_method'],
                    'user_id' => $user->id,
                    'cashier_shift_id' => $activeShift?->id,
                    'notes' => (! empty($validated['notes']) ? ($term->notes ? $term->notes . ' | ' : '') . $validated['notes'] : $term->notes),
                ]);
            } else {
                // If no specific term was selected, allocate to pending terms or create a payment entry
                $pendingTerms = $order->payments()->whereIn('status', ['pending', 'partial'])->orderBy('term_index')->get();
                if ($pendingTerms->isNotEmpty()) {
                    $remainingToApply = $payAmt;
                    foreach ($pendingTerms as $term) {
                        if ($remainingToApply <= 0) {
                            break;
                        }
                        $owed = max(0, (float) $term->amount - (float) $term->paid_amount);
                        $apply = min($remainingToApply, $owed);
                        $newT = (float) $term->paid_amount + $apply;
                        $term->update([
                            'paid_amount' => $newT,
                            'status' => $newT >= (float) $term->amount ? 'paid' : 'partial',
                            'paid_at' => now(),
                            'payment_method' => $validated['payment_method'],
                            'user_id' => $user->id,
                            'cashier_shift_id' => $activeShift?->id,
                        ]);
                        $remainingToApply -= $apply;
                    }
                } else {
                    $nextIndex = ($order->payments()->max('term_index') ?? 0) + 1;
                    OrderPayment::create([
                        'order_id' => $order->id,
                        'cashier_shift_id' => $activeShift?->id,
                        'user_id' => $user->id,
                        'term_index' => $nextIndex,
                        'term_name' => "Termin {$nextIndex} (Pelunasan)",
                        'amount' => $payAmt,
                        'paid_amount' => $payAmt,
                        'payment_method' => $validated['payment_method'],
                        'status' => 'paid',
                        'paid_at' => now(),
                        'notes' => $validated['notes'] ?? 'Pelunasan sisa tagihan',
                    ]);
                }
            }

            if ($activeShift) {
                if ($validated['payment_method'] === 'cash') {
                    $activeShift->increment('cash_sales', $payAmt);
                    $activeShift->increment('expected_cash', $payAmt);
                } else {
                    $activeShift->increment('non_cash_sales', $payAmt);
                }
            }

            $newPaid = $order->paid_amount + $payAmt;
            $newPaymentStatus = $newPaid >= $order->grand_total ? 'paid' : 'partial';

            $order->update([
                'paid_amount' => $newPaid,
                'payment_status' => $newPaymentStatus,
                'notes' => ($order->notes ? $order->notes . "\n" : '') . "Pembayaran Rp " . number_format($payAmt, 0, ',', '.') . " via {$validated['payment_method']} pada " . now()->format('d/m/Y H:i'),
            ]);
        });

        return back()->with('success', "Pembayaran untuk pesanan {$order->invoice_number} berhasil dicatat!");
    }

    public function storeTerm(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'term_name' => 'required|string|max:100',
            'amount' => 'required|numeric|min:1',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $nextIndex = ($order->payments()->max('term_index') ?? 0) + 1;

        OrderPayment::create([
            'order_id' => $order->id,
            'term_index' => $nextIndex,
            'term_name' => $validated['term_name'],
            'amount' => $validated['amount'],
            'paid_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'pending',
            'due_date' => $validated['due_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', "Jadwal termin '{$validated['term_name']}' berhasil ditambahkan ke riwayat pesanan!");
    }

    public function deleteTerm(Order $order, OrderPayment $payment): RedirectResponse
    {
        if ($payment->order_id !== $order->id) {
            abort(404);
        }

        if ($payment->status === 'paid' || $payment->paid_amount > 0) {
            return back()->with('error', 'Termin yang sudah memiliki pembayaran tidak dapat dihapus.');
        }

        $payment->delete();

        return back()->with('success', 'Termin pembayaran berhasil dihapus.');
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'order_status' => 'required|string|in:pending,processing,ready,completed,cancelled',
        ]);

        $order->update(['order_status' => $validated['order_status']]);

        return back()->with('success', "Status pesanan {$order->invoice_number} diubah menjadi {$validated['order_status']}.");
    }
}
