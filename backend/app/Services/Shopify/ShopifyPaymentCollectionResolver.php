<?php

namespace App\Services\Shopify;

use App\Models\CollectionCompany;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountLinkingService;
use Illuminate\Support\Facades\Log;

/**
 * يحوّل وسيلة الدفع في Shopify (payment_gateway_names / gateway / payment_details)
 * إلى شركة تحصيل مطابقة بالاسم، وينشئها مع حساب ذمم عند عدم وجودها.
 *
 * القاعدة:
 *   - بوابات مميزة (Sympl / valU / Fawry …) لها الأولوية.
 *   - وإلا: إن ظهر برند بطاقة (Visa/Mastercard…) → شركة "Visa".
 *   - وإلا: بوابات الدفع بالبطاقة الغامضة (Paymob/Kashier…) باسمها.
 *   - وإلا: اسم البوابة كما ورد، أو الافتراضي.
 */
class ShopifyPaymentCollectionResolver
{
    public function __construct(
        private AccountLinkingService $accountLinking,
    ) {}

    /**
     * نص وسيلة الدفع كما يظهر في تفاصيل الطلب (للعرض والتتبّع).
     */
    public function extractGatewayLabel(array $payload): ?string
    {
        $names = $payload['payment_gateway_names'] ?? null;
        if (is_array($names)) {
            $clean = array_values(array_filter(
                array_map(fn ($n) => trim((string) $n), $names),
                fn ($n) => $n !== ''
            ));
            if ($clean !== []) {
                return implode(' / ', array_unique($clean));
            }
        }

        $gateway = trim((string) ($payload['gateway'] ?? ''));
        if ($gateway !== '') {
            return $gateway;
        }

        $brand = $this->extractCardBrand($payload);
        if ($brand !== null) {
            return $brand;
        }

        return null;
    }

    /**
     * برند البطاقة إن وُجد في الحمولة (payment_details / transactions).
     */
    public function extractCardBrand(array $payload): ?string
    {
        $brand = trim((string) data_get($payload, 'payment_details.credit_card_company', ''));
        if ($brand !== '') {
            return $brand;
        }

        foreach ((array) ($payload['transactions'] ?? []) as $tx) {
            if (! is_array($tx)) {
                continue;
            }
            $b = trim((string) data_get($tx, 'payment_details.credit_card_company', ''));
            if ($b !== '') {
                return $b;
            }
        }

        return null;
    }

    /**
     * الاسم القانوني لشركة التحصيل المطابقة لوسيلة الدفع.
     */
    public function resolveCanonicalName(?string $gatewayLabel, array $payload): string
    {
        $cardBrand = $this->extractCardBrand($payload);

        $blobParts = [];
        if ($gatewayLabel !== null && $gatewayLabel !== '') {
            $blobParts[] = $gatewayLabel;
        }
        if ($cardBrand !== null && $cardBrand !== '') {
            $blobParts[] = $cardBrand;
        }
        $blob = mb_strtolower(implode(' ', $blobParts), 'UTF-8');

        // 1) بوابات مميزة (Sympl / valU / Fawry ...) — أولوية مطلقة.
        foreach ((array) config('shopify_collection.gateway_map', []) as $needle => $name) {
            if ($blob !== '' && str_contains($blob, mb_strtolower((string) $needle, 'UTF-8'))) {
                return (string) $name;
            }
        }

        // 2) دفع بالبطاقة (برند ظاهر أو كلمة مفتاحية للبطاقة) → شركة البطاقة.
        if ($this->looksLikeCardPayment($blob, $cardBrand)) {
            return (string) config('shopify_collection.card_company', 'Visa');
        }

        // 3) بوابات قد تكون بطاقة لكن بلا برند ظاهر — باسم البوابة.
        foreach ((array) config('shopify_collection.ambiguous_gateway_map', []) as $needle => $name) {
            if ($blob !== '' && str_contains($blob, mb_strtolower((string) $needle, 'UTF-8'))) {
                return (string) $name;
            }
        }

        // 4) اسم البوابة كما ورد (شركة جديدة باسمها) أو الافتراضي.
        if ($gatewayLabel !== null && trim($gatewayLabel) !== '') {
            return trim($gatewayLabel);
        }

        return (string) config('shopify_collection.default_company', 'Visa');
    }

    private function looksLikeCardPayment(string $blob, ?string $cardBrand): bool
    {
        if ($cardBrand !== null && $cardBrand !== '') {
            return true;
        }
        if ($blob === '') {
            return false;
        }
        foreach ((array) config('shopify_collection.card_aliases', []) as $alias) {
            $alias = mb_strtolower((string) $alias, 'UTF-8');
            if ($alias !== '' && str_contains($blob, $alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * يجد شركة التحصيل المطابقة أو ينشئها مع حساب ذمم لها.
     *
     * @return array{company: CollectionCompany, created: bool, account: ?TreeAccount, gateway_label: ?string, canonical: string}|null
     */
    public function resolveOrCreateForPayload(array $payload): ?array
    {
        $gatewayLabel = $this->extractGatewayLabel($payload);
        $canonical = $this->resolveCanonicalName($gatewayLabel, $payload);
        if (trim($canonical) === '') {
            return null;
        }

        [$company, $created] = $this->findOrCreateCompany($canonical, $gatewayLabel);

        $account = $this->accountLinking->ensureCollectionCompanyAccount($company);
        if (! $account) {
            Log::warning('ShopifyPaymentCollectionResolver: collection company has no receivable account', [
                'collection_company_id' => $company->id,
                'name' => $company->name,
            ]);
        }

        return [
            'company' => $company->fresh() ?? $company,
            'created' => $created,
            'account' => $account,
            'gateway_label' => $gatewayLabel,
            'canonical' => $canonical,
        ];
    }

    /**
     * @return array{0: CollectionCompany, 1: bool}
     */
    private function findOrCreateCompany(string $canonicalName, ?string $gatewayLabel): array
    {
        $lower = mb_strtolower($canonicalName, 'UTF-8');

        $existing = CollectionCompany::query()
            ->whereRaw('LOWER(name) = ?', [$lower])
            ->first();

        if (! $existing) {
            $existing = CollectionCompany::query()
                ->whereRaw('LOWER(name) LIKE ?', ['%' . $lower . '%'])
                ->orderBy('id')
                ->first();
        }

        if ($existing) {
            return [$existing, false];
        }

        $company = CollectionCompany::create([
            'name' => $canonicalName,
            'status' => 'active',
            'notes' => 'أُنشئت تلقائياً من مزامنة Shopify — وسيلة الدفع: '
                . ($gatewayLabel !== null && $gatewayLabel !== '' ? $gatewayLabel : $canonicalName),
        ]);

        return [$company, true];
    }
}
