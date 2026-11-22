<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingAssetsTest extends TestCase
{
    use RefreshDatabase;

    /** Peso máximo de una imagen de la landing, en bytes. */
    private const int MAX_IMAGE_BYTES = 300 * 1024;

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

    public function test_the_community_video_is_only_downloaded_when_it_comes_into_view(): void
    {
        $html = $this->get(route('inicio'))->getContent();

        $this->assertMatchesRegularExpression('#<video\s+muted loop playsinline preload="none" data-reproducir-al-ver#', $html);
        $this->assertDoesNotMatchRegularExpression('#<video[^>]*\bautoplay\b[^>]*>\s*<source src="[^"]*video2\.mp4#', $html);
    }

    public function test_the_original_photos_are_kept_outside_the_public_folder(): void
    {
        $this->assertSame([], glob(public_path('images/*.{jpg,jpeg,JPG}'), GLOB_BRACE));

        foreach (['image1.jpg', 'image2.jpg', 'image3.jpg', 'image4.jpg', 'logo1.jpg'] as $original) {
            $this->assertFileExists(resource_path('images-originales/'.$original));
        }
    }
}
