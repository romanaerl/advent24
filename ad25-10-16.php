<?php
declare(strict_types=1);

/**
 * Exact math solution for THIS one instance, without solvers.
 * Derivation is from symbolic elimination of A·x=b for this exact A.
 *
 * We search only over the 3 free parameters:
 *   a = x11, c = x12, t = x9
 * with parity constraints and non-negativity, then minimize sum(x).
 */

$buttons = [
    [1,4,5,6,7,8],
    [0,7],
    [0,1,2,3,5,6,7],
    [0,1,2,3,4,6,7],
    [1,2,4,7,8],
    [0,1,5,6],
    [6,8,9],
    [4,6,9],
    [1,2,3,4,5,6,7,8],
    [0,4,7,8],
    [0,3,5,7,8,9],
    [1,2,3,4,9],
    [0,1,2,3,5,7,8],
];
$b = [115,123,88,96,101,102,112,144,110,54];

function buildA(array $buttons, int $n, int $m): array {
    $A = array_fill(0, $n, array_fill(0, $m, 0));
    for ($j=0; $j<$m; $j++) {
        foreach ($buttons[$j] as $i) $A[$i][$j] = 1;
    }
    return $A;
}

function matVec(array $A, array $x): array {
    $n = count($A);
    $m = count($x);
    $r = array_fill(0, $n, 0);
    for ($i=0; $i<$n; $i++) {
        $s = 0;
        for ($j=0; $j<$m; $j++) $s += $A[$i][$j] * $x[$j];
        $r[$i] = $s;
    }
    return $r;
}

function vecEqual(array $u, array $v): bool {
    if (count($u) !== count($v)) return false;
    for ($i=0; $i<count($u); $i++) if ((int)$u[$i] !== (int)$v[$i]) return false;
    return true;
}

/**
 * General solution for this A·x=b (derived by elimination):
 * Let a=x11, c=x12, t=x9. Then:
 * x0  = (c-a)/2 + t
 * x1  = (5a - 4t + 39)/2
 * x2  = -a + 2t - 5
 * x3  = 29 - a
 * x4  = (-2a - c + 57)/2
 * x5  = (a - c - 2t + 70)/2
 * x6  = (a + 19)/2
 * x7  = (c-a)/2 + 8
 * x8  = (4a - c - 4t + 71)/2
 * x9  = t
 * x10 = (-2a - c + 73)/2
 * x11 = a
 * x12 = c
 *
 * Integrality requires: a odd, c odd (then all /2 are integers).
 */
function buildX(int $a, int $c, int $t): array {
    // assumes a,c odd so divisions are integers
    $x0  = intdiv(($c - $a), 2) + $t;
    $x1  = intdiv((5*$a - 4*$t + 39), 2);
    $x2  = -$a + 2*$t - 5;
    $x3  = 29 - $a;
    $x4  = intdiv((-2*$a - $c + 57), 2);
    $x5  = intdiv(($a - $c - 2*$t + 70), 2);
    $x6  = intdiv(($a + 19), 2);
    $x7  = intdiv(($c - $a), 2) + 8;
    $x8  = intdiv((4*$a - $c - 4*$t + 71), 2);
    $x9  = $t;
    $x10 = intdiv((-2*$a - $c + 73), 2);
    $x11 = $a;
    $x12 = $c;

    return [$x0,$x1,$x2,$x3,$x4,$x5,$x6,$x7,$x8,$x9,$x10,$x11,$x12];
}

// --- main search over (a,c,t) ---
$n = 10; $m = 13;
$A = buildA($buttons, $n, $m);

$bestSum = PHP_INT_MAX;
$bestX = null;
$bestParams = null;

/**
 * From derived sum:
 *   sum(x) = (3a)/2 - t + 393/2
 * With a odd => sum integer.
 * So we want smallest a, largest feasible t.
 *
 * We'll still brute-force tiny ranges safely (fast).
 */
for ($a = 1; $a <= 29; $a += 2) {           // a must be odd, and x3=29-a >= 0
    // t constraints from x2>=0 and x1>=0:
    // x2 = -a + 2t - 5 >= 0  => t >= (a+5)/2
    // x1 = (5a - 4t + 39)/2 >= 0 => t <= (5a+39)/4
    $tMin = intdiv(($a + 5 + 1), 2); // ceil((a+5)/2)
    $tMax = intdiv((5*$a + 39), 4);  // floor((5a+39)/4)
    if ($tMax < 0) continue;

    for ($t = max(0, $tMin); $t <= $tMax; $t++) {
        // Choose c (odd) that makes all nonnegative. Objective doesn't depend on c,
        // but feasibility does. We'll scan a small feasible range quickly.
        // Constraints (from nonneg):
        // x4 >=0 => c <= 57 - 2a
        // x10>=0 => c <= 73 - 2a
        // x8 >=0 => c <= 4a - 4t + 71
        // x5 >=0 => c <= a - 2t + 70
        // x7 >=0 => c >= a - 16
        // x0 >=0 => c >= a - 2t
        $cHi = min(57 - 2*$a, 73 - 2*$a, 4*$a - 4*$t + 71, $a - 2*$t + 70);
        $cLo = max(0, $a - 16, $a - 2*$t);

        if ($cHi < $cLo) continue;

        // scan odd c
        $cStart = ($cLo % 2 === 1) ? $cLo : ($cLo + 1);
        for ($c = $cStart; $c <= $cHi; $c += 2) {
            // c must be odd (same parity as a)
            $x = buildX($a, $c, $t);
            $ok = true;
            foreach ($x as $v) { if ($v < 0) { $ok = false; break; } }
            if (!$ok) continue;

            // verify A*x == b (safety)
            if (!vecEqual(matVec($A, $x), $b)) continue;

            $s = array_sum($x);
            if ($s < $bestSum) {
                $bestSum = $s;
                $bestX = $x;
                $bestParams = [$a,$c,$t];
            }
        }
    }

    // early exit: since sum grows with a, once we found any solution at a=1 with max t,
    // it is already optimal. In practice, for this instance, a=1 yields optimum.
    if ($bestParams !== null && $bestParams[0] === 1) {
        // keep going? not needed
        break;
    }
}

if ($bestX === null) {
    echo "No solution found.\n";
    exit(1);
}

[$a,$c,$t] = $bestParams;
echo "OPTIMAL total presses = {$bestSum}\n";
echo "Parameters: a=x11={$a} (odd), c=x12={$c} (odd), t=x9={$t}\n";
echo "Presses per button (x0..x12):\n";
foreach ($bestX as $i => $v) {
    if ($v != 0) {
        echo "  button#{$i} = {$v}\n";
    }
}
