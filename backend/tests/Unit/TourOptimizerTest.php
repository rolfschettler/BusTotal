<?php

namespace Tests\Unit;

use App\Routing\TourOptimizer;
use PHPUnit\Framework\TestCase;

class TourOptimizerTest extends TestCase
{
    /** Punkte auf einer Linie: Die Fahrzeit ist der Abstand. */
    private function line(array $positions): array
    {
        return array_map(fn ($a) => array_map(fn ($b) => (float) abs($a - $b), $positions), $positions);
    }

    public function test_ordnet_zwischenhalte_exakt_nach_fahrzeit(): void
    {
        // 0 = Von (0), 1..3 = Über (30, 10, 20), 4 = Nach (40)
        $this->assertSame([2, 3, 1], (new TourOptimizer)->order($this->line([0, 30, 10, 20, 40]), 0, 4, [1, 2, 3]));
    }

    public function test_einzelner_zwischenhalt_bleibt(): void
    {
        $this->assertSame([1], (new TourOptimizer)->order($this->line([0, 5, 9]), 0, 2, [1]));
    }

    public function test_viele_zwischenhalte_mit_naeherung(): void
    {
        $positions = [0, ...array_reverse(range(10, 140, 10)), 150];
        $order = (new TourOptimizer)->order($this->line($positions), 0, 15, range(1, 14));

        $this->assertSame(range(10, 140, 10), array_map(fn ($i) => $positions[$i], $order));
    }
}
