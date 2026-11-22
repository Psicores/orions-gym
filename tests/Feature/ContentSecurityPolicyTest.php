<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\ClientResource;
use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function report(array $overrides = []): array
    {
        return ['csp-report' => [
            'document-uri' => 'https://orions.test/mi-perfil',
            'effective-directive' => 'script-src-elem',
            'violated-directive' => 'script-src-elem',
            'blocked-uri' => 'https://malicioso.example/x.js',
            'source-file' => 'https://orions.test/mi-perfil',
            'line-number' => 12,
            'original-policy' => str_repeat('x', 50),
            ...$overrides,
        ]];
    }

    private function postReport(string $body, string $ip = '127.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->call('POST', route('csp.report'), server: ['CONTENT_TYPE' => 'application/csp-report'], content: $body);
    }

    public function test_the_public_site_the_portal_and_the_panel_carry_both_policies(): void
    {
        $responses = [
            $this->get(route('inicio')),
            $this->get(route('login')),
            $this->actingAs(User::factory()->create())->get(ClientResource::getUrl('index')),
        ];

        foreach ($responses as $response) {
            $response->assertOk()
                ->assertHeader('Content-Security-Policy', SecurityHeaders::ENFORCED_POLICY);

            $reportOnly = $response->headers->get('Content-Security-Policy-Report-Only');
            $this->assertStringContainsString("default-src 'self'", $reportOnly);
            $this->assertStringContainsString("connect-src 'self'", $reportOnly);
            $this->assertStringContainsString('report-uri /csp-reportes', $reportOnly);
        }
    }

    public function test_the_enforced_policy_blocks_plugins_foreign_forms_base_changes_and_framing(): void
    {
        foreach (["object-src 'none'", "base-uri 'self'", "form-action 'self'", "frame-ancestors 'self'"] as $rule) {
            $this->assertStringContainsString($rule, SecurityHeaders::ENFORCED_POLICY);
        }

        foreach (['script-src', 'style-src', 'img-src', 'default-src'] as $directive) {
            $this->assertStringNotContainsString($directive, SecurityHeaders::ENFORCED_POLICY);
        }
    }

    public function test_a_browser_report_is_logged_without_a_csrf_token_and_trimmed_to_the_useful_fields(): void
    {
        Log::spy();

        $this->postReport((string) json_encode($this->report()))->assertNoContent();

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'Aviso de la política de contenido (CSP)'
                && $context['blocked-uri'] === 'https://malicioso.example/x.js'
                && $context['effective-directive'] === 'script-src-elem'
                && $context['line-number'] === '12'
                && ! array_key_exists('original-policy', $context);
        });
    }

    public function test_long_values_are_cut_so_the_log_cannot_be_flooded(): void
    {
        Log::spy();

        $this->postReport((string) json_encode($this->report(['blocked-uri' => str_repeat('a', 2000)])))->assertNoContent();

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => mb_strlen($context['blocked-uri']) <= 303,
        );
    }

    public function test_junk_and_oversized_bodies_are_ignored(): void
    {
        Log::spy();

        $this->postReport('esto no es json')->assertNoContent();
        $this->postReport((string) json_encode(['otra-cosa' => true]))->assertNoContent();
        $this->postReport((string) json_encode($this->report(['blocked-uri' => str_repeat('a', 9000)])))->assertNoContent();

        Log::shouldNotHaveReceived('warning');
    }

    public function test_one_ip_can_send_at_most_twenty_reports_per_minute(): void
    {
        $body = (string) json_encode($this->report());

        foreach (range(1, 20) as $attempt) {
            $this->postReport($body)->assertNoContent();
        }

        $this->postReport($body)->assertTooManyRequests();
        $this->postReport($body, '10.0.0.9')->assertNoContent();
    }
}
