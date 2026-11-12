<?php

namespace Tests\Feature;

use App\Filament\Resources\Plans\Pages\ManagePlans;
use App\Filament\Resources\Plans\PlanResource;
use App\Models\Plan;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_plans_are_managed_from_a_single_page_without_create_or_edit_routes(): void
    {
        $this->assertSame(['index'], array_keys(PlanResource::getPages()));

        $this->get(PlanResource::getUrl('index'))
            ->assertOk()
            ->assertSee('.orions-modal .fi-modal-header', escape: false);
    }

    public function test_a_plan_is_created_from_the_modal(): void
    {
        Livewire::test(ManagePlans::class)
            ->callAction(CreateAction::class, [
                'nombre' => 'Libre',
                'precio' => 45000.50,
                'creditos_otorgados' => 999,
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Plan::class, [
            'nombre' => 'Libre',
            'precio' => 45000.50,
            'creditos_otorgados' => 999,
        ]);
    }

    public function test_the_create_modal_rejects_a_plan_without_required_fields(): void
    {
        Livewire::test(ManagePlans::class)
            ->callAction(CreateAction::class, [
                'nombre' => null,
                'precio' => null,
                'creditos_otorgados' => null,
            ])
            ->assertHasFormErrors([
                'nombre' => 'required',
                'precio' => 'required',
                'creditos_otorgados' => 'required',
            ]);

        $this->assertDatabaseCount(Plan::class, 0);
    }

    public function test_a_plan_is_edited_from_the_modal(): void
    {
        $plan = Plan::factory()->create(['nombre' => '2x semana', 'creditos_otorgados' => 8]);

        Livewire::test(ManagePlans::class)
            ->callAction(TestAction::make(EditAction::class)->table($plan), [
                'nombre' => '3x semana',
                'creditos_otorgados' => 12,
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Plan::class, [
            'id' => $plan->id,
            'nombre' => '3x semana',
            'creditos_otorgados' => 12,
        ]);
    }

    public function test_a_plan_is_soft_deleted_from_its_row(): void
    {
        $plan = Plan::factory()->create();

        Livewire::test(ManagePlans::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($plan));

        $this->assertSoftDeleted($plan);
    }

    public function test_a_deleted_plan_is_restored_from_its_row(): void
    {
        $plan = Plan::factory()->trashed()->create();

        Livewire::test(ManagePlans::class)
            ->filterTable(TrashedFilter::class)
            ->callAction(TestAction::make(RestoreAction::class)->table($plan));

        $this->assertNotSoftDeleted($plan);
    }
}
