<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 每個網頁回應都帶基本安全標頭，而且不影響相機掃描與本站自己的 iframe 彈出視窗。 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    public function test_pages_carry_basic_security_headers(): void
    {
        foreach (['/login', '/forgot-password'] as $path) {
            $this->get($path)
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        }

        $this->actingAs($this->loginAsAnyUser())->get(route('dashboard'))->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_hsts_only_on_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }
}
