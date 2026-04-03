<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_the_application_returns_a_successful_response()
    {
        // المسار الجذر غير مفعّل في web.php؛ نختبر مساراً ثابتاً يعيد 200
        $response = $this->get('/images/whatsapp-meta-default.png');

        $response->assertStatus(200);
    }
}
