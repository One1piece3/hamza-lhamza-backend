<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CustomerOrderStatusNotification;
use App\Mail\NewOrderAdminNotification;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderController extends Controller
{
    protected function sendCustomerOrderNotification(Order $order, string $notificationType): ?string
    {
        if (empty($order->customer_email)) {
            return null;
        }

        try {
            Mail::to($order->customer_email)->queue(
                new CustomerOrderStatusNotification($order, $notificationType)
            );

            return null;
        } catch (Throwable $exception) {
            Log::warning('Customer order notification failed', [
                'order_id' => $order->id,
                'customer_email' => $order->customer_email,
                'notification_type' => $notificationType,
                'error' => $exception->getMessage(),
            ]);

            return 'La commande a ete mise a jour, mais l email client n a pas pu etre envoye.';
        }
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => 'nullable|in:pending,confirmed,shipping,delivered,cancelled',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $orders = Order::query()
            ->when(!empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $search = trim($filters['search']);

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('reference', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%");
                });
            })
            ->when(!empty($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function stats()
    {
        $orders = Order::latest()->get();
        $paidStatuses = ['confirmed', 'shipping', 'delivered'];
        $topProducts = [];

        foreach ($orders as $order) {
            foreach ($order->items ?? [] as $item) {
                $key = (string) ($item['id'] ?? $item['name'] ?? uniqid('item_', true));

                if (!isset($topProducts[$key])) {
                    $topProducts[$key] = [
                        'product_id' => $item['id'] ?? null,
                        'name' => $item['name'] ?? 'Produit',
                        'quantity' => 0,
                        'revenue' => 0,
                    ];
                }

                $quantity = (int) ($item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? 0);
                $topProducts[$key]['quantity'] += $quantity;
                $topProducts[$key]['revenue'] += $quantity * $price;
            }
        }

        usort($topProducts, fn ($a, $b) => $b['quantity'] <=> $a['quantity']);

        return response()->json([
            'revenue_total' => (float) $orders->whereIn('status', $paidStatuses)->sum('total'),
            'orders_today' => $orders->where('created_at', '>=', now()->startOfDay())->count(),
            'pending_orders' => $orders->where('status', 'pending')->count(),
            'confirmed_orders' => $orders->where('status', 'confirmed')->count(),
            'shipping_orders' => $orders->where('status', 'shipping')->count(),
            'delivered_orders' => $orders->where('status', 'delivered')->count(),
            'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
            'average_order_value' => round((float) $orders->avg('total'), 2),
            'top_products' => array_slice(array_map(function (array $item) {
                return [
                    ...$item,
                    'revenue' => round((float) $item['revenue'], 2),
                ];
            }, $topProducts), 0, 5),
        ]);
    }

    public function customerOrders(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer',
        ]);

        $orders = Order::query()
            ->when(
                !empty($data['user_id']),
                fn ($query) => $query->where('customer_user_id', $data['user_id']),
                fn ($query) => $query->where('customer_email', $data['email'])
            )
            ->when(
                !empty($data['name']),
                fn ($query) => $query->orWhere(function ($fallbackQuery) use ($data) {
                    $fallbackQuery
                        ->whereNull('customer_email')
                        ->where('customer_name', $data['name']);
                })
            )
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_user_id' => 'nullable|integer',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => ['required', 'string', 'max:50', 'regex:/^[0-9+\s()-]{8,20}$/'],
            'customer_city' => 'required|string|max:255',
            'customer_address' => 'required|string|min:5',
            'customer_note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.name' => 'required|string|max:255',
            'items.*.price' => 'required|numeric|min:0.01',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.size' => 'required|string|max:255',
            'items.*.color' => 'required|string|max:255',
            'subtotal' => 'required|numeric|min:0',
            'delivery_fee' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
        ]);

        $calculatedSubtotal = 0.0;

        foreach ($data['items'] as $item) {
            $product = Product::find($item['id']);

            if (!$product) {
                throw ValidationException::withMessages([
                    'items' => ["Le produit {$item['name']} n'est plus disponible."],
                ]);
            }

            if ($product->stock < (int) $item['quantity']) {
                throw ValidationException::withMessages([
                    'items' => ["Stock insuffisant pour {$product->name}."],
                ]);
            }

            $calculatedSubtotal += (float) $item['price'] * (int) $item['quantity'];
        }

        $calculatedSubtotal = round($calculatedSubtotal, 2);
        $deliveryFee = round((float) $data['delivery_fee'], 2);
        $calculatedTotal = round($calculatedSubtotal + $deliveryFee, 2);

        if (round((float) $data['subtotal'], 2) !== $calculatedSubtotal || round((float) $data['total'], 2) !== $calculatedTotal) {
            throw ValidationException::withMessages([
                'total' => ['Le montant de la commande ne correspond plus au panier actuel.'],
            ]);
        }

        $order = Order::create([
            ...$data,
            'customer_name' => trim($data['customer_name']),
            'customer_email' => !empty($data['customer_email']) ? strtolower($data['customer_email']) : null,
            'customer_phone' => preg_replace('/\s+/', ' ', trim($data['customer_phone'])),
            'customer_city' => trim($data['customer_city']),
            'customer_address' => trim($data['customer_address']),
            'customer_note' => !empty($data['customer_note']) ? trim($data['customer_note']) : null,
            'reference' => 'CMD-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
            'status' => 'pending',
        ]);

        $adminEmail = config('mail.admin_order_notification_email');
        $mailWarning = null;
        $customerMailWarning = $this->sendCustomerOrderNotification($order, 'created');

        if (!empty($adminEmail)) {
            try {
                Mail::to($adminEmail)->queue(new NewOrderAdminNotification($order));
            } catch (Throwable $exception) {
                $mailWarning = 'La commande a ete enregistree mais la notification email a echoue.';

                Log::warning('Order admin notification failed', [
                    'order_id' => $order->id,
                    'admin_email' => $adminEmail,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Commande enregistree avec succes',
            'mail_warning' => $mailWarning,
            'customer_mail_warning' => $customerMailWarning,
            'order' => $order,
        ], 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,shipping,delivered,cancelled',
        ]);

        $result = DB::transaction(function () use ($id, $data) {
            $order = Order::lockForUpdate()->findOrFail($id);
            $previousStatus = $order->status;
            $nextStatus = $data['status'];
            $missingProducts = [];
            $stockReservedStatuses = ['confirmed', 'shipping', 'delivered'];

            if ($previousStatus === $nextStatus) {
                return [
                    'order' => $order,
                    'missing_products' => $missingProducts,
                    'status_changed' => false,
                ];
            }

            if (!in_array($previousStatus, $stockReservedStatuses, true)
                && in_array($nextStatus, $stockReservedStatuses, true)) {
                foreach ($order->items ?? [] as $item) {
                    $productId = $item['id'] ?? $item['product_id'] ?? null;
                    $product = Product::lockForUpdate()->find($productId);

                    if (!$product) {
                        $missingProducts[] = $item['name'] ?? 'Produit supprime';
                        continue;
                    }

                    $quantity = (int) ($item['quantity'] ?? 0);

                    if ($product->stock < $quantity) {
                        abort(422, "Stock insuffisant pour {$product->name}.");
                    }

                    $product->decrement('stock', $quantity);
                }
            }

            if (in_array($previousStatus, $stockReservedStatuses, true)
                && !in_array($nextStatus, $stockReservedStatuses, true)) {
                foreach ($order->items ?? [] as $item) {
                    $productId = $item['id'] ?? $item['product_id'] ?? null;
                    $product = Product::lockForUpdate()->find($productId);

                    if (!$product) {
                        continue;
                    }

                    $quantity = (int) ($item['quantity'] ?? 0);
                    $product->increment('stock', $quantity);
                }
            }

            $order->status = $nextStatus;
            $order->confirmed_at = in_array($nextStatus, $stockReservedStatuses, true)
                ? ($order->confirmed_at ?? Carbon::now())
                : null;
            $order->save();

            return [
                'order' => $order,
                'missing_products' => $missingProducts,
                'status_changed' => true,
            ];
        });

        $message = 'Statut de commande mis a jour';
        $statusNotificationTypes = [
            'confirmed' => 'confirmed',
            'shipping' => 'shipping',
            'delivered' => 'delivered',
        ];
        $customerMailWarning = null;

        if (!empty($result['missing_products'])) {
            $message .= ' (certains produits lies a la commande ont deja ete supprimes)';
        }

        $nextStatus = $result['order']->status;

        if (($result['status_changed'] ?? false) && isset($statusNotificationTypes[$nextStatus])) {
            $customerMailWarning = $this->sendCustomerOrderNotification(
                $result['order'],
                $statusNotificationTypes[$nextStatus]
            );
        }

        return response()->json([
            'message' => $message,
            'customer_mail_warning' => $customerMailWarning,
            'order' => $result['order'],
            'missing_products' => $result['missing_products'],
        ]);
    }
}
