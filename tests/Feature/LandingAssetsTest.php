<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingAssetsTest extends TestCase
{
    use RefreshDatabase;

    /** Peso máximo de una imagen de las páginas públicas, en bytes. */
    private const int MAX_IMAGE_BYTES = 300 * 1024;

    /** Peso máximo de un video de fondo, en bytes. */
    private const int MAX_VIDEO_BYTES = 3 * 1024 * 1024;

    /**
     * Archivos de /images (en public/) o /storage (disco "public") que usa una página, con su ruta en disco.
     *
     * @return array<string, string>
     */
    private function referencedFiles(string $html, string $extensions): array
    {
        preg_match_all('#/(images|storage)/([A-Za-z0-9_./-]+\.(?:'.$extensions.'))#', $html, $matches, PREG_SET_ORDER);

        $files = [];
        foreach ($matches as [, $folder, $file]) {
            $files[$folder.'/'.$file] = $folder === 'images' ? public_path('images/'.$file) : Storage::disk('public')->path($file);
        }

        return $files;
    }

    public function test_every_image_of_the_public_pages_exists_and_is_light(): void
    {
        foreach ([route('inicio'), route('curev-hue')] as $url) {
            $images = $this->referencedFiles($this->get($url)->assertOk()->getContent(), 'webp|jpe?g|png|avif');

            $this->assertNotEmpty($images, "La página {$url} no muestra ninguna imagen.");

            foreach ($images as $name => $path) {
                $this->assertFileExists($path);
                $this->assertLessThanOrEqual(self::MAX_IMAGE_BYTES, filesize($path), "{$name} pesa más de 300 KB.");
            }
        }
    }

    public function test_every_video_of_the_public_pages_exists_is_light_and_has_a_poster(): void
    {
        foreach ([route('inicio'), route('curev-hue')] as $url) {
            $html = $this->get($url)->getContent();
            preg_match_all('#<video\b[^>]*>#', $html, $videos);
            $files = $this->referencedFiles($html, 'mp4');

            $this->assertNotEmpty($files, "La página {$url} no tiene videos.");

            foreach ($videos[0] as $video) {
                $this->assertMatchesRegularExpression('#poster="[^"]*/(images|storage)/[A-Za-z0-9_./-]+\.webp"#', $video);
            }

            foreach ($files as $name => $path) {
                $this->assertFileExists($path);
                $this->assertLessThanOrEqual(self::MAX_VIDEO_BYTES, filesize($path), "{$name} pesa más de 3 MB.");
            }
        }
    }

    public function test_the_community_photos_load_lazily(): void
    {
        $html = $this->get(route('inicio'))->getContent();

        $this->assertMatchesRegularExpression('#src="[^"]*/storage/site/comunidad/barra-olimpica\.webp"[^>]*loading="lazy"#', $html);
        $this->assertMatchesRegularExpression('#src="[^"]*/storage/site/comunidad/kettlebell\.webp"[^>]*loading="lazy"#', $html);
    }

    public function test_the_community_video_is_only_downloaded_when_it_comes_into_view(): void
    {
        $html = $this->get(route('inicio'))->getContent();

        $this->assertMatchesRegularExpression('#<video\s+muted loop playsinline preload="none" data-reproducir-al-ver#', $html);
        $this->assertDoesNotMatchRegularExpression('#<video[^>]*\bautoplay\b[^>]*>\s*<source src="[^"]*video-comunidad\.mp4#', $html);
    }

    public function test_the_original_photos_and_videos_are_kept_outside_the_public_folder(): void
    {
        $this->assertSame([], glob(public_path('images/*.{jpg,jpeg,JPG}'), GLOB_BRACE));
        $this->assertSame([], array_diff(array_map('basename', glob(public_path('images/*.mp4'))), ['video-portada.mp4', 'video-windsurf.mp4']));

        foreach (['image1.jpg', 'image2.jpg', 'image3.jpg', 'image4.jpg', 'logo1.jpg', 'Video1.mp4', 'video2.mp4', 'windsurf.mp4'] as $original) {
            $this->assertFileExists(resource_path('images-originales/'.$original));
        }
    }
}
