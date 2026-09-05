<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderResourceCollection;
use App\Http\Requests\StoreOrderRequest;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // Consistent error envelope
    private function errorResponse($code, $message, $details = [], $status = 422)
    {
        return response()->json([
            'error' => true,
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ], $status);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $user = $request->user();

        $orders = $user->role === 'approver'
            ? Order::paginate(10)
            : Order::where('user_id', $user->id)->paginate(10);

        return new OrderResourceCollection($orders);
    }

    public function store(StoreOrderRequest $request)
    {
        $this->authorize('create', Order::class);

        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;
        $order = Order::create($validated);

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        return new OrderResource($order);
    }

    public function update(StoreOrderRequest $request, Order $order)
    {
        $this->authorize('update', $order);

        $order->update($request->validated());
        return new OrderResource($order);
    }

    public function destroy(Request $request, Order $order)
    {
        $this->authorize('delete', $order);

        $order->delete();
        return response()->noContent();
    }
}