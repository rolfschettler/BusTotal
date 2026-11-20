<?php

namespace App\Routing;

/**
 * Löst das Rundreiseproblem (TSP) für die freien Halte einer Tour: Von und Nach bleiben stehen, die Zwischenhalte
 * werden so geordnet, dass die Gesamtfahrzeit am kürzesten ist. Bis zu 10 freie Halte wird exakt gerechnet
 * (dynamische Programmierung nach Held und Karp), darüber mit Nächstem-Nachbarn und 2-opt-Verbesserung.
 */
class TourOptimizer
{
    private const EXACT_LIMIT = 10;

    /**
     * @param  list<list<?float>>  $cost  Fahrzeitmatrix; $cost[$a][$b] = Fahrzeit von a nach b (null: nicht erreichbar)
     * @param  int  $from  Index des festen Starts
     * @param  int  $to  Index des festen Ziels
     * @param  list<int>  $free  Indizes der freien Halte
     * @return list<int>  die freien Indizes in bester Reihenfolge
     */
    public function order(array $cost, int $from, int $to, array $free): array
    {
        $n = count($free);
        if ($n < 2) {
            return $free;
        }
        $big = 1.0e12;
        $c = fn (int $a, int $b): float => (float) ($cost[$a][$b] ?? $big);

        return $n <= self::EXACT_LIMIT ? $this->exact($c, $from, $to, $free) : $this->heuristic($c, $from, $to, $free);
    }

    /** @param  list<int>  $free */
    private function exact(callable $c, int $from, int $to, array $free): array
    {
        $n = count($free);
        $full = (1 << $n) - 1;
        $best = [];
        $prev = [];
        for ($j = 0; $j < $n; $j++) {
            $best[1 << $j][$j] = $c($from, $free[$j]);
        }
        for ($mask = 1; $mask <= $full; $mask++) {
            for ($j = 0; $j < $n; $j++) {
                if (! isset($best[$mask][$j])) {
                    continue;
                }
                for ($k = 0; $k < $n; $k++) {
                    if ($mask & (1 << $k)) {
                        continue;
                    }
                    $next = $mask | (1 << $k);
                    $d = $best[$mask][$j] + $c($free[$j], $free[$k]);
                    if (! isset($best[$next][$k]) || $d < $best[$next][$k]) {
                        $best[$next][$k] = $d;
                        $prev[$next][$k] = $j;
                    }
                }
            }
        }
        $last = 0;
        $min = INF;
        for ($j = 0; $j < $n; $j++) {
            $d = $best[$full][$j] + $c($free[$j], $to);
            if ($d < $min) {
                $min = $d;
                $last = $j;
            }
        }
        $order = [];
        $mask = $full;
        for ($i = 0; $i < $n; $i++) {
            $order[] = $free[$last];
            $p = $prev[$mask][$last] ?? null;
            $mask &= ~(1 << $last);
            $last = $p ?? 0;
        }

        return array_reverse($order);
    }

    /** @param  list<int>  $free */
    private function heuristic(callable $c, int $from, int $to, array $free): array
    {
        $order = [];
        $left = $free;
        $at = $from;
        while ($left) {
            $bestKey = array_key_first($left);
            foreach ($left as $key => $idx) {
                if ($c($at, $idx) < $c($at, $left[$bestKey])) {
                    $bestKey = $key;
                }
            }
            $at = $left[$bestKey];
            $order[] = $at;
            unset($left[$bestKey]);
        }

        // 2-opt: ein Teilstück umdrehen, solange es die Gesamtzeit verkürzt.
        $path = array_merge([$from], $order, [$to]);
        $n = count($path);
        $improved = true;
        while ($improved) {
            $improved = false;
            for ($i = 1; $i < $n - 2; $i++) {
                for ($j = $i + 1; $j < $n - 1; $j++) {
                    $before = $c($path[$i - 1], $path[$i]) + $c($path[$j], $path[$j + 1]);
                    $after = $c($path[$i - 1], $path[$j]) + $c($path[$i], $path[$j + 1]);
                    if ($after + 1e-9 < $before) {
                        array_splice($path, $i, $j - $i + 1, array_reverse(array_slice($path, $i, $j - $i + 1)));
                        $improved = true;
                    }
                }
            }
        }

        return array_slice($path, 1, -1);
    }
}
