<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingAssetsTest extends TestCase
{
    use RefreshDatabase;

    /** Peso máximo de una imagen de la landing, en bytes. */
    private const int MAX_IMAGE_BYTES = 300 * 1024;

    /** Peso máximo de un video de fondo, en bytes. */
    private const int MAX_VIDEO_BYTES = 3 * 1024 * 1024;

    /**
     * @return list<string>
     */
    private function referencedImages(string $html): array
    {
        preg_match_all('#/images/([A-Za-z0-9_./-]+\.(?:webp|jpe?g|png|avif))#', $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function test_every_image_of_the_public_pages_exists_and_is_light(): void
    {
        foreach ([route('inicio'), route('curev-hue')] as $url) {
            $images = $this->referencedImages($this->get($url)->assertOk()->getContent());

            $this->assertNotEmpty($images, "La página {$url} no muestra ninguna imagen.");

            foreach ($images as $image) {
                $path = public_path('images/'.$image);
                $this->assertFileExists($path);
                $this->assertLessThanOrEqual(self::MAX_IMAGE_BYTES, filesize($path), "images/{$image} pesa más de 300 KB.");
            }
        }
    }

    public function test_the_landing_photos_declare_their_size_and_load_lazily(): void
    {
        $html = $this->get(route('inicio'))->getContent();

        $this->assertMatchesRegularExpression('#src="[^"]*/images/barra-olimpica\.webp"[^>]*width="1200" height="931" loading="lazy"#', $html);
        $this->assertMatchesRegularExpression('#src="[^"]*/images/kettlebell\.webp"[^>]*width="1200" height="800" loading="lazy"#', $html);
    }

    public function test_every_video_of_the_public_pages_exists_is_light_and_has_a_poster(): void
    {
        foreach ([route('inicio'), route('curev-hue')] as $url) {
            $html = $this->get($url)->getContent();
            preg_match_all('#<video[^>]*>#', $html, $videos);
            preg_match_all('#<source src="[^"]*/images/([A-Za-z0-9_.-]+\.mp4)"#', $html, $sources);

            $this->assertNotEmpty($sources[1], "La página {$url} no tiene videos.");

            foreach ($videos[0] as $video) {
                $this->assertMatchesRegularExpression('#poster="[^"]*/images/[A-Za-z0-9_.-]+\.webp"#', $video);
            }

            foreach ($sources[1] as $file) {
                $this->assertFileExists(public_path('images/'.$file));
                $this->assertLessThanOrEqual(self::MAX_VIDEO_BYTES, filesize(public_path('images/'.$file)), "images/{$file} pesa más de 3 MB.");
            }
        }
    }

    public function test_the_community_video_is_only_downloaded_when_it_comes_into_view(): void
    {
        $html = $this->get(route('inicio'))->getContent();

        $this->assertMatchesRegularExpression('#<video\s+muted loop playsinline preload="none" data-reproducir-al-ver#', $html);
        $this->assertDoesNotMatchRegularExpression('#<video[^>]*\bautoplay\b[^>]*>\s*<source src="[^"]*video2\.mp4#', $html);
    }

    public function test_the_original_photos_and_videos_are_kept_outside_the_public_folder(): void
    {
        $this->assertSame([], glob(public_path('images/*.{jpg,jpeg,JPG}'), GLOB_BRACE));
        $this->assertSame([], array_diff(array_map('basename', glob(public_path('images/*.mp4'))), ['video-portada.mp4', 'video-comunidad.mp4', 'video-windsurf.mp4']));

        foreach (['image1.jpg', 'image2.jpg', 'image3.jpg', 'image4.jpg', 'logo1.jpg', 'Video1.mp4', 'video2.mp4', 'windsurf.mp4'] as $original) {
            $this->assertFileExists(resource_path('images-originales/'.$original));
        }
    }
}
