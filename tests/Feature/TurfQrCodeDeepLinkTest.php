<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Turf;
use App\Models\TurfQrScan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TurfQrCodeDeepLinkTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $customer;
    protected Location $location;
    protected Turf $turf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->owner = User::factory()->create([
            'name' => 'Rohit Venue Owner',
            'email' => 'rohit@venue.com',
            'mobile' => '9811111111',
        ]);
        $this->owner->assignRole('turf-admin');

        $this->customer = User::factory()->create([
            'name' => 'Hardik Player',
            'email' => 'hardik@player.com',
            'mobile' => '9822222222',
        ]);
        $this->customer->assignRole('customer');

        $this->location = Location::create([
            'user_id' => $this->owner->id,
            'name' => 'Wankhede Box Arena',
            'address' => 'Marine Drive, Mumbai',
            'latitude' => 18.9389,
            'longitude' => 72.8258,
        ]);

        $this->turf = Turf::create([
            'location_id' => $this->location->id,
            'name' => 'Pitch 1',
            'type' => 'Synthetic',
            'status' => 'Approved',
            'is_active' => true,
        ]);
    }

    public function test_turf_automatically_generates_unique_8_char_qr_code_and_can_regenerate(): void
    {
        $this->assertNotEmpty($this->turf->qr_code);
        $this->assertEquals(8, strlen($this->turf->qr_code));

        $oldCode = $this->turf->qr_code;
        session(['active_turf_id' => $this->turf->id]);

        Volt::actingAs($this->owner)
            ->test('turf.qr-code-manager')
            ->assertSee($oldCode)
            ->call('regenerateQrCode')
            ->assertHasNoErrors();

        $newCode = $this->turf->fresh()->qr_code;
        $this->assertNotEquals($oldCode, $newCode);
        $this->assertEquals(8, strlen($newCode));
    }

    public function test_public_smart_landing_page_renders_and_logs_scan_and_unknown_code_returns_404(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8)',
        ])->get(route('turf.qr.landing', $this->turf->qr_code));

        $response->assertStatus(200);
        $response->assertSee('Pitch 1');
        $response->assertSee('Wankhede Box Arena');
        $response->assertSee('turfbooking://turf/' . $this->turf->qr_code, false);

        $scan = TurfQrScan::where('turf_id', $this->turf->id)->first();
        $this->assertNotNull($scan);
        $this->assertEquals('android', $scan->platform);
        $this->assertEquals('web', $scan->source);

        $this->get(route('turf.qr.landing', 'INVALID99'))->assertStatus(404);
    }

    public function test_well_known_assetlinks_and_apple_app_site_association_return_valid_json(): void
    {
        $this->get('/.well-known/assetlinks.json')
            ->assertStatus(200)
            ->assertJsonFragment(['package_name' => 'com.infoleena.turf.booking']);

        $this->get('/.well-known/apple-app-site-association')
            ->assertStatus(200)
            ->assertJsonPath('applinks.details.0.paths.0', '/t/*');
    }

    public function test_png_svg_and_a3_a4_a5_poster_pdf_downloads_work_and_block_unauthorized_users(): void
    {
        // Customer is blocked (403)
        $this->actingAs($this->customer)
            ->get(route('turf.qr.download.png', $this->turf->id))
            ->assertStatus(403);

        // Turf Owner can download PNG, SVG, and A3 / A4 / A5 Poster PDFs
        $pngRes = $this->actingAs($this->owner)->get(route('turf.qr.download.png', $this->turf->id));
        $pngRes->assertStatus(200);
        $this->assertEquals('image/png', $pngRes->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $pngRes->getContent());

        $svgRes = $this->actingAs($this->owner)->get(route('turf.qr.download.svg', $this->turf->id));
        $svgRes->assertStatus(200);
        $this->assertStringContainsString('<svg', $svgRes->getContent());

        foreach (['a3', 'a4', 'a5'] as $size) {
            $pdfRes = $this->actingAs($this->owner)->get(route('turf.qr.download.poster', [
                'turf' => $this->turf->id,
                'size' => $size,
            ]));
            $pdfRes->assertStatus(200);
            $this->assertEquals('application/pdf', $pdfRes->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF', $pdfRes->getContent());
        }
    }

    public function test_api_resolves_turf_by_qr_code_and_records_conversion_attribution(): void
    {
        $res = $this->getJson("/api/turfs/by-code/{$this->turf->qr_code}");
        $res->assertStatus(200)
            ->assertJsonPath('id', $this->turf->id)
            ->assertJsonPath('name', 'Pitch 1');

        $attrRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/qr/attribution', [
                'code' => $this->turf->qr_code,
                'platform' => 'android',
            ]);

        $attrRes->assertStatus(200)
            ->assertJsonPath('turf_id', $this->turf->id);

        $this->assertDatabaseHas('turf_qr_scans', [
            'turf_id' => $this->turf->id,
            'converted_user_id' => $this->customer->id,
        ]);
    }
}
