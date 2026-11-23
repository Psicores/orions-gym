<?php

namespace Tests\Feature;

use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Announcement;
use App\Models\Client;
use App\Models\User;
use App\Support\ImageOptimizer;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ImageOptimizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Como en un hosting común; con 128 MB el optimizador saltea las fotos grandes a propósito.
        ini_set('memory_limit', '512M');
    }

    /**
     * Foto con ruido (no se comprime casi nada), como una foto real de celular.
     */
    private function noisyJpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y += 4) {
            for ($x = 0; $x < $width; $x += 4) {
                imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            }
        }
        ob_start();
        imagejpeg($image, null, 95);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    public function test_a_big_photo_is_shrunk_and_compressed_to_webp(): void
    {
        $original = $this->noisyJpeg(3000, 2000);

        $result = ImageOptimizer::optimize($original, 1600);

        $this->assertNotNull($result);
        $this->assertSame('webp', $result['extension']);
        $this->assertLessThan(strlen($original), strlen($result['contents']));
        [$width, $height] = getimagesizefromstring($result['contents']);
        $this->assertSame([1600, 1067], [$width, $height]);
    }

    public function test_a_tall_photo_fits_in_the_box_without_cropping(): void
    {
        $result = ImageOptimizer::optimize($this->noisyJpeg(1200, 2400), 800, 800);

        [$width, $height] = getimagesizefromstring($result['contents']);
        $this->assertSame([400, 800], [$width, $height]);
    }

    public function test_a_small_light_image_is_left_as_is(): void
    {
        $small = UploadedFile::fake()->image('chica.jpg', 300, 200)->getContent();

        $this->assertNull(ImageOptimizer::optimize($small, 1600));
    }

    public function test_a_heavy_image_of_the_right_size_is_compressed_without_growing(): void
    {
        $heavy = $this->noisyJpeg(1000, 700);
        $this->assertGreaterThan(ImageOptimizer::MAX_BYTES, strlen($heavy));

        $result = ImageOptimizer::optimize($heavy, 1600);

        [$width] = getimagesizefromstring($result['contents']);
        $this->assertSame(1000, $width);
        $this->assertLessThan(strlen($heavy), strlen($result['contents']));
    }

    public function test_a_pdf_or_garbage_is_never_touched(): void
    {
        $this->assertNull(ImageOptimizer::optimize("%PDF-1.4\n...", 800, 800));
        $this->assertNull(ImageOptimizer::optimize('no es una imagen', 800, 800));
    }

    public function test_a_big_announcement_image_is_stored_light_and_the_original_discarded(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $upload = UploadedFile::fake()->createWithContent('banner.jpg', $this->noisyJpeg(3000, 1500));

        Livewire::test(ManageAnnouncements::class)
            ->callAction(CreateAction::class, ['titulo' => 'Grande', 'imagen_path' => $upload])
            ->assertHasNoFormErrors();

        $path = Announcement::query()->sole()->imagen_path;
        $this->assertStringEndsWith('.webp', $path);
        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(1600, $width);
        $this->assertSame([$path], Storage::disk('public')->allFiles('announcements'));
    }

    public function test_a_client_photo_is_stored_at_most_800_by_800(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $client = Client::factory()->create();

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['foto_path' => UploadedFile::fake()->createWithContent('foto.jpg', $this->noisyJpeg(1600, 1600))])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $client->refresh()->foto_path;
        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame([800, 800], [$width, $height]);
        $this->assertLessThanOrEqual(ImageOptimizer::MAX_BYTES, Storage::disk('local')->size($path));
    }

    public function test_a_pdf_certificate_is_stored_unchanged(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $client = Client::factory()->create();
        $pdf = UploadedFile::fake()->createWithContent('apto.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['certificado_salud_path' => $pdf])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $client->refresh()->certificado_salud_path;
        $this->assertStringEndsWith('.pdf', $path);
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('local')->get($path));
    }
}
