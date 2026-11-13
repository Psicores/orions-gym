<?php

namespace Tests\Feature;

use App\Livewire\GymOccupancyMeter;
use App\Models\Attendance;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GymOccupancyMeterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{int, string, string, string}>
     */
    public static function occupancyLevels(): array
    {
        return [
            'empty gym' => [0, 'tranqui', 'Tranqui, ideal para entrenar.', 'bg-emerald-400'],
            'top of quiet' => [15, 'tranqui', 'Tranqui, ideal para entrenar.', 'bg-emerald-400'],
            'bottom of busy' => [16, 'movido', 'Movido (compartiendo máquinas).', 'bg-amber-400'],
            'top of busy' => [25, 'movido', 'Movido (compartiendo máquinas).', 'bg-amber-400'],
            'full' => [26, 'a_pleno', 'A pleno.', 'bg-oxido'],
        ];
    }

    #[DataProvider('occupancyLevels')]
    public function test_it_shows_the_level_for_the_recent_check_ins(int $checkIns, string $level, string $text, string $colour): void
    {
        $this->checkIns($checkIns, minutesAgo: 30);

        Livewire::test(GymOccupancyMeter::class)
            ->assertSeeInOrder(['Ocupación actual:', $text])
            ->assertSeeHtml('data-estado="'.$level.'"')
            ->assertSeeHtml($colour);
    }

    public function test_only_check_ins_from_the_last_ninety_minutes_count(): void
    {
        $this->checkIns(30, minutesAgo: 91);
        $this->checkIns(3, minutesAgo: 89);

        $component = Livewire::test(GymOccupancyMeter::class)->assertSeeHtml('data-estado="tranqui"');

        $this->assertSame(3, $component->instance()->currentOccupancy());
    }

    public function test_it_never_shows_the_number_of_people_and_refreshes_every_five_minutes(): void
    {
        $this->checkIns(17, minutesAgo: 10);

        Livewire::test(GymOccupancyMeter::class)
            ->assertDontSeeText('17')
            ->assertSeeHtml('wire:poll.300s')
            ->assertSeeHtml('animate-pulse');
    }

    public function test_the_member_dashboard_shows_the_meter_above_everything_else(): void
    {
        $client = Client::factory()->create(['nombre' => 'Juan']);

        $this->actingAs($client, 'client')
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeLivewire(GymOccupancyMeter::class)
            ->assertSeeInOrder(['Ocupación actual:', 'Hola,', 'Juan']);
    }

    private function checkIns(int $count, int $minutesAgo): void
    {
        if ($count === 0) {
            return;
        }

        Attendance::factory()->count($count)->create(['fecha_hora' => now()->subMinutes($minutesAgo)]);
    }
}
