<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyOrderImportService;
use ReflectionMethod;
use Tests\TestCase;

class ShopifyOrderImportServicePhoneTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $addr
     */
    private function resolvePhone(array $payload, array $addr = []): string
    {
        $service = app(ShopifyOrderImportService::class);
        $method = new ReflectionMethod(ShopifyOrderImportService::class, 'resolvePhone');
        $method->setAccessible(true);

        return $method->invoke($service, $payload, $addr);
    }

    private function normalizePhone(string $phone): string
    {
        $service = app(ShopifyOrderImportService::class);
        $method = new ReflectionMethod(ShopifyOrderImportService::class, 'normalizePhone');
        $method->setAccessible(true);

        return $method->invoke($service, $phone);
    }

    public function test_normalize_phone_converts_egypt_international_to_local(): void
    {
        $this->assertSame('01012345678', $this->normalizePhone('+20 10 1234 5678'));
        $this->assertSame('01012345678', $this->normalizePhone('201012345678'));
        $this->assertSame('01012345678', $this->normalizePhone('00201012345678'));
        $this->assertSame('01012345678', $this->normalizePhone('1012345678'));
        $this->assertSame('01012345678', $this->normalizePhone('01012345678'));
    }

    public function test_normalize_phone_fixes_common_double_zero_country_code_typo(): void
    {
        $this->assertSame('01012345678', $this->normalizePhone('+2101012345678'));
    }

    public function test_resolve_phone_prefers_note_attributes_over_empty_shipping_phone(): void
    {
        $phone = $this->resolvePhone([
            'shipping_address' => ['phone' => ''],
            'note_attributes' => [
                ['name' => 'رقم الموبايل', 'value' => '+20 11 2222 3333'],
            ],
        ]);

        $this->assertSame('01122223333', $phone);
    }

    public function test_resolve_phone_reads_mobile_note_attribute_name(): void
    {
        $phone = $this->resolvePhone([
            'note_attributes' => [
                ['name' => 'Mobile', 'value' => '01099887766'],
            ],
        ]);

        $this->assertSame('01099887766', $phone);
    }

    public function test_resolve_phone_uses_shipping_address_when_present(): void
    {
        $phone = $this->resolvePhone([
            'shipping_address' => ['phone' => '+201055544433'],
            'billing_address' => ['phone' => '+201066655544'],
        ], ['phone' => '+201055544433']);

        $this->assertSame('01055544433', $phone);
    }

    public function test_resolve_phone_handles_shopify_customer_contact_format(): void
    {
        // مثال حقيقي من شوبيفاي: +20 12 76450205 (طلب #57196)
        $phone = $this->resolvePhone([
            'phone' => '+20 12 76450205',
            'customer' => [
                'first_name' => 'Mohamed',
                'last_name' => 'Abdelhady',
                'phone' => '+20 12 76450205',
            ],
            'shipping_address' => [
                'first_name' => 'Mohamed',
                'last_name' => 'Abdelhady',
                'city' => 'ALX',
                'province' => 'Alexandria',
                'address1' => 'Sedra north cost Oruba',
                'address2' => 'Alexandria north cost Sedra km 38',
            ],
        ], [
            'first_name' => 'Mohamed',
            'last_name' => 'Abdelhady',
            'city' => 'ALX',
        ]);

        $this->assertSame('01276450205', $phone);
    }

    public function test_resolve_phone_skips_placeholder_zeros(): void
    {
        $phone = $this->resolvePhone([
            'shipping_address' => ['phone' => '0000000000'],
            'customer' => ['phone' => '+201012345678'],
        ]);

        $this->assertSame('01012345678', $phone);
    }
}
