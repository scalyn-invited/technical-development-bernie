<?php

namespace App\Services;

use InvalidArgumentException;

/** Pure arithmetic on two-place scores; no framework or database dependencies. */
class ComparisonCalculator
{
    public function calculate(array $rows): array
    {
        $sum = 0;
        $count = 0;
        foreach ($rows as &$row) {
            $baseline = $this->hundredths($row['baseline_score']);
            $row['delta'] = null;
            if ($row['final_score'] !== null) {
                $movement = $this->hundredths($row['final_score']) - $baseline;
                $row['delta'] = $this->format($movement);
                $sum += $movement;
                $count++;
            }
        }
        unset($row);
        // Round the mean once, half away from zero, without floating arithmetic.
        $mean = $count ? intdiv(abs($sum), $count) : 0;
        if ($count && (abs($sum) % $count) * 2 >= $count) {
            $mean++;
        }

        return [
            'data' => array_values($rows),
            'summary' => [
                'compared_skills' => $count,
                'pending_skills' => count($rows) - $count,
                'average_movement' => $count ? $this->format($sum < 0 ? -$mean : $mean) : null,
            ],
        ];
    }

    private function hundredths(string $score): int
    {
        if (! preg_match('/^(\d{1,3})\.(\d{2})$/D', $score, $parts)) {
            throw new InvalidArgumentException('Scores must be fixed two-place decimal strings.');
        }
        $value = (int) $parts[1] * 100 + (int) $parts[2];
        if ($value > 10000) {
            throw new InvalidArgumentException('Scores must be between 0 and 100.');
        }

        return $value;
    }

    private function format(int $value): string
    {
        return ($value < 0 ? '-' : '').intdiv(abs($value), 100).'.'.str_pad((string) (abs($value) % 100), 2, '0', STR_PAD_LEFT);
    }
}
