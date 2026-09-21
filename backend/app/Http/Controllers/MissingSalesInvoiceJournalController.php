<?php

namespace App\Http\Controllers;

use App\Services\Accounting\MissingSalesInvoiceJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissingSalesInvoiceJournalController extends Controller
{
    private const MAX_RANGE_DAYS = 93;

    public function __construct(
        private MissingSalesInvoiceJournalService $missingJournals
    ) {
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $this->validateRange($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $preview = $this->missingJournals->preview(
            $request->user(),
            $validated['date_from'],
            $validated['date_to'],
            $validated['mode']
        );

        return response()->json($preview);
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $this->validateRange($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $result = $this->missingJournals->postMissing(
            $request->user(),
            $validated['date_from'],
            $validated['date_to'],
            $validated['mode']
        );

        if (($result['success'] ?? false) === false) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function previewOrder(Request $request, int $id): JsonResponse
    {
        $preview = $this->missingJournals->previewOrder($request->user(), $id);
        $status = ($preview['reason'] ?? null) === 'الطلب غير موجود أو غير مصرح بعرضه.' ? 404 : 200;

        return response()->json($preview, $status);
    }

    public function postOrder(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['nullable', 'array', 'min:2', 'max:20'],
            'lines.*.account_id' => ['required_with:lines', 'integer', 'min:1'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'journals' => ['nullable', 'array', 'max:8'],
            'journals.*.key' => ['required_with:journals', 'string', 'in:invoice,cogs,prepaid,delivery,collection,shipping_expense'],
            'journals.*.date' => ['nullable', 'date'],
            'journals.*.description' => ['nullable', 'string', 'max:255'],
            'journals.*.lines' => ['required_with:journals', 'array', 'min:2', 'max:20'],
            'journals.*.lines.*.account_id' => ['required_with:journals', 'integer', 'min:1'],
            'journals.*.lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'journals.*.lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'journals.*.lines.*.description' => ['nullable', 'string', 'max:255'],
            'journals.*.sync_operational' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->missingJournals->postOrder(
                $request->user(),
                $id,
                $validated['lines'] ?? null,
                $validated['date'] ?? null,
                $validated['description'] ?? null,
                $validated['journals'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if (($result['status'] ?? null) === 'not_found') {
            return response()->json($result, 404);
        }

        if (($result['success'] ?? false) === false) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    /**
     * @return array{date_from: string, date_to: string, mode: string}|JsonResponse
     */
    private function validateRange(Request $request): array|JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'mode' => ['nullable', 'in:order_date,shipping_date'],
        ]);
        $validated['mode'] = MissingSalesInvoiceJournalService::normalizeMode(
            (string) ($validated['mode'] ?? MissingSalesInvoiceJournalService::MODE_ORDER_DATE)
        );

        if (MissingSalesInvoiceJournalService::maxRangeDaysExceeded(
            $validated['date_from'],
            $validated['date_to'],
            self::MAX_RANGE_DAYS
        )) {
            return response()->json([
                'message' => 'نطاق التاريخ كبير جداً (الحد حوالي ثلاثة أشهر). قلّل الفترة ثم أعد المحاولة.',
            ], 422);
        }

        return $validated;
    }
}
