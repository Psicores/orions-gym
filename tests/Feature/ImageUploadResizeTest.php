<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteCommunity;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Las imágenes que se suben desde el panel se achican en el navegador antes de subir, y el
 * límite de peso se mide sobre el original: una foto sacada con el celular tiene que entrar.
 */
class ImageUploadResizeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_the_client_photo_fits_in_800_by_800_without_cropping(): void
    {
        Livewire::test(EditClient::class, ['record' => Client::factory()->create()->getRouteKey()])
            ->assertFormFieldExists('foto_path', fn (FileUpload $field): bool => $field->getImageResizeMode() === 'contain'
                && $field->getImageResizeTargetWidth() === '800'
                && $field->getImageResizeTargetHeight() === '800'
                && ! $field->getImageResizeUpscale()
                && $field->getMaxSize() === 10240);
    }

    public function test_the_announcement_image_is_at_most_1600_wide(): void
    {
        Livewire::test(ManageAnnouncements::class)
            ->mountAction(CreateAction::class)
            ->assertFormFieldExists('imagen_path', fn (FileUpload $field): bool => $field->getImageResizeTargetWidth() === '1600'
                && $field->getImageResizeTargetHeight() === null
                && ! $field->getImageResizeUpscale()
                && $field->getMaxSize() === 10240);
    }

    public function test_site_images_accept_a_phone_photo_and_shrink_it(): void
    {
        Livewire::test(SiteCommunity::class)
            ->assertFormFieldExists('poster_path', fn (FileUpload $field): bool => $field->getImageResizeTargetWidth() !== null
                && $field->getMaxSize() === 10240);
    }
}
