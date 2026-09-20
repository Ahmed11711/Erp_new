<?php

namespace App\Http\Controllers;

use App\Models\Settlement;
use App\Services\Shipping\SettlementService;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function __construct(private SettlementService $settlements)
    {
    }

    public function index(Request $request)
    {
        $q = Settlement::query()->withCount('items')->orderByDesc('id');

        if ($request->filled('provider_type')) {
            $q->where('provider_type', $request->provider_type);
        }
        if ($request->filled('provider_id')) {
            $q->where('provider_id', (int) $request->provider_id);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        return response()->json($q->paginate($request->integer('per_page', 20)));
    }

    public function show(Settlement $settlement)
    {
        $settlement->load(['items.order:id,customer_name,order_status,net_total', 'createdBy:id,name']);

        return response()->json($settlement);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provider_type' => 'required|in:shipping_company,courier,collection_company',
            'provider_id' => 'required|integer|min:1',
            'period_from' => 'nullable|date',
            'period_to' => 'nullable|date|after_or_equal:period_from',
            'notes' => 'nullable|string',
            'order_ids' => 'nullable|array',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        try {
            $settlement = $this->settlements->createDraft($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($settlement, 201);
    }

    public function post(Request $request, Settlement $settlement)
    {
        $data = $request->validate([
            'settled_amount' => 'required|numeric|min:0.001',
        ]);

        try {
            $settlement = $this->settlements->post($settlement, (float) $data['settled_amount']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($settlement);
    }
}
