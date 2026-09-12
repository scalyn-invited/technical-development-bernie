<?php

namespace Tests\Unit;

use App\Services\ComparisonCalculator;
use PHPUnit\Framework\TestCase;

class ComparisonCalculatorTest extends TestCase
{
    public function test_deltas_and_average_use_only_matched_skills(): void
    {
        $result = (new ComparisonCalculator)->calculate([
            ['baseline_score' => '60.25', 'final_score' => '85.10'],
            ['baseline_score' => '80.00', 'final_score' => '70.00'],
            ['baseline_score' => '95.00', 'final_score' => null],
        ]);
        $this->assertSame(['24.85', '-10.00', null], array_column($result['data'], 'delta'));
        $this->assertSame(['compared_skills' => 2, 'pending_skills' => 1, 'average_movement' => '7.43'], $result['summary']);
    }

    public function test_empty_and_unfinished_sets_have_no_average(): void
    {
        $calculator = new ComparisonCalculator;
        $this->assertNull($calculator->calculate([])['summary']['average_movement']);
        $result = $calculator->calculate([['baseline_score' => '0.00', 'final_score' => null]]);
        $this->assertNull($result['data'][0]['delta']);
        $this->assertSame(1, $result['summary']['pending_skills']);
        $this->assertNull($result['summary']['average_movement']);
    }

    public function test_boundaries_and_zero_are_preserved(): void
    {
        $result = (new ComparisonCalculator)->calculate([
            ['baseline_score' => '0.00', 'final_score' => '100.00'],
            ['baseline_score' => '100.00', 'final_score' => '0.00'],
            ['baseline_score' => '50.00', 'final_score' => '50.00'],
        ]);
        $this->assertSame(['100.00', '-100.00', '0.00'], array_column($result['data'], 'delta'));
        $this->assertSame('0.00', $result['summary']['average_movement']);
    }

    public function test_negative_half_rounds_away_from_zero(): void
    {
        $result = (new ComparisonCalculator)->calculate([
            ['baseline_score' => '0.01', 'final_score' => '0.00'],
            ['baseline_score' => '0.00', 'final_score' => '0.00'],
        ]);
        $this->assertSame('-0.01', $result['summary']['average_movement']);
    }

    public function test_invalid_scores_are_not_silently_calculated(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ComparisonCalculator)->calculate([['baseline_score' => '101.00', 'final_score' => '0.00']]);
    }
}
