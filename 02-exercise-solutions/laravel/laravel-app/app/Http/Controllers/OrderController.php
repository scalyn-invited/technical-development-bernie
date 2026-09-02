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

    public function index()
    {
        $orders = Order::paginate(10);
        return new OrderResourceCollection($orders);
    }

    public function store(StoreOrderRequest $request)
    {
        $order = Order::create($request->validated());
        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show($id)
    {
        $order = Order::find($id);
        
        if (!$order) {
            return $this->errorResponse('not_found', 'Order not found', [], 404);
        }

        return new OrderResource($order);
    }

    public function update(StoreOrderRequest $request, $id)
    {
        $order = Order::find($id);
        
        if (!$order) {
            return $this->errorResponse('not_found', 'Order not found', [], 404);
        }

        $order->update($request->validated());
        return new OrderResource($order);
    }

    public function destroy($id)
    {
        $order = Order::find($id);
        
        if (!$order) {
            return $this->errorResponse('not_found', 'Order not found', [], 404);
        }

        $order->delete();
        return response()->noContent();
    }
}