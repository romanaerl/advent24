<?php
declare(strict_types=1);

/**
 * Parallel hybrid solver (fixed + fractional-safe math):
 *  - per machine: MATH-first
 *      * RREF on exact rationals (Frac)
 *      * supports fractional parameterizations via scaling (LCM denominators) + modular constraints
 *      * searches params with tight bounds + BnB in parameter space
 *  - fallback to BnB ONLY if math hits limits (except m167: math-only)
 *  - each machine in its own child process (bounded by --procs)
 *  - robust: worker errors are retried once; m167 never escalates to BnB
 *
 * Usage:
 *   php ad25-10-17-fixed.php <inputfile> [--procs=8] [--limit=60] [--maxfree=3] [--status=1]
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (php_sapi_name() !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
foreach (['pcntl_fork','pcntl_waitpid','socket_create_pair','posix_kill'] as $fn) {
    if (!function_exists($fn)) {
        fwrite(STDERR, "Missing required extension/function: {$fn}\nNeed: pcntl, sockets, posix\n");
        exit(1);
    }
}

/* =========================
   FRACTIONS (exact rational)
   ========================= */
final class Frac {
    public int $n;
    public int $d;

    public function __construct(int $n = 0, int $d = 1) {
        if ($d === 0) throw new RuntimeException("Frac: zero denominator");
        if ($d < 0) { $n = -$n; $d = -$d; }
        $g = self::gcd(abs($n), $d);
        $this->n = intdiv($n, $g);
        $this->d = intdiv($d, $g);
    }
    public static function zero(): self { return new self(0,1); }
    public static function one(): self { return new self(1,1); }

    public static function gcd(int $a, int $b): int {
        while ($b !== 0) { $t = $a % $b; $a = $b; $b = $t; }
        return max($a, 1);
    }
    public static function lcm(int $a, int $b): int {
        $a = abs($a); $b = abs($b);
        if ($a === 0 || $b === 0) return 0;
        $g = self::gcd($a, $b);
        return intdiv($a, $g) * $b;
    }

    public function add(self $o): self { return new self($this->n*$o->d + $o->n*$this->d, $this->d*$o->d); }
    public function sub(self $o): self { return new self($this->n*$o->d - $o->n*$this->d, $this->d*$o->d); }
    public function mul(self $o): self { return new self($this->n*$o->n, $this->d*$o->d); }
    public function div(self $o): self {
        if ($o->n === 0) throw new RuntimeException("Frac: div by zero");
        return new self($this->n*$o->d, $this->d*$o->n);
    }
    public function neg(): self { return new self(-$this->n, $this->d); }
    public function isZero(): bool { return $this->n === 0; }
    public function toInt(): ?int { return $this->d === 1 ? $this->n : null; }
}

/* =========================
   SOLVER (math-first + fallback BnB)
   ========================= */
final class HybridSolver {

    /** Parse file like your old examples: tuples () and jolts {} */
    public function parseFile(string $filePath): array {
        if (!is_file($filePath)) throw new RuntimeException("File not found: $filePath");
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) throw new RuntimeException("Cannot read: $filePath");

        $allButtons = [];
        $allJolts = [];

        foreach ($lines as $id => $line) {
            if (!preg_match_all('/\(([^)]*)\)/', $line, $mT)) {
                throw new RuntimeException("cant read tuples on line $id");
            }
            if (!preg_match('/\{([^}]*)\}/', $line, $mJ)) {
                throw new RuntimeException("cant read jolts on line $id");
            }

            $buttons = [];
            foreach ($mT[1] as $t) {
                $t = trim($t);
                if ($t === '') { $buttons[] = []; continue; }
                $parts = array_filter(array_map('trim', explode(',', $t)), fn($x)=>$x!=='');
                $buttons[] = array_map('intval', $parts);
            }

            $jParts = array_filter(array_map('trim', explode(',', trim($mJ[1]))), fn($x)=>$x!=='');
            $jolts = array_map('intval', $jParts);

            $allButtons[$id] = $buttons;
            $allJolts[$id] = $jolts;
        }

        return [$allButtons, $allJolts];
    }

    /**
     * Solve one machine:
     * - default: math-first then fallback bnb if needed
     * - if $forceBnb: skip math and do bnb only
     *
     * NOTE: m167 forced MATH ONLY.
     */
    public function solveOne($mid, array $buttons, array $jolts, int $limit, int $maxFree, bool $forceBnb = false): array {

        // ===== HARD OVERRIDE FOR m167 =====
        if ($mid === 167) {
            $limit167   = 400;
            $maxFree167 = 8;

            $math = $this->solveMath($buttons, $jolts, $limit167, $maxFree167, true);
            if ($math === null) {
                throw new RuntimeException(
                    "m167 forced-math failed (limit={$limit167}, maxfree={$maxFree167})"
                );
            }

            return [
                'res'  => $math,
                'mode' => "MATH(FORCED m167, limit={$limit167}, maxfree={$maxFree167})"
            ];
        }
        // ===== END OVERRIDE =====

        if (!$forceBnb) {
            $math = $this->solveMath($buttons, $jolts, $limit, $maxFree, false);
            if ($math !== null) {
                return ['res'=>$math, 'mode'=>"MATH(maxfree=${maxFree},limit=${limit})"];
            }
        }

        $bnb = $this->solveBnBStable($buttons, $jolts);
        return ['res'=>$bnb, 'mode'=>$forceBnb ? "BNB(force)" : "BNB(fallback)"];
    }

    /* ---------- Math solver (fractional-safe) ---------- */

    /**
     * Fractional-safe math solver:
     * - RREF with exact rationals => x = x0 + Σ t_k v_k
     * - Scale by L=lcm(denoms) => X = Lx, X0 = Lx0, V = Lv
     * - Integrality of x becomes: X_i ≡ 0 (mod L) for all i
     *   -> search residues r_k = t_k mod L that satisfy all congruences
     * - then set t_k = r_k + L z_k, and search integers z_k in bounded range via BnB
     */
    public function solveMath(array $buttons, array $jolts, int $limit, int $maxFree, bool $isForced = false): ?int {
        $m = count($jolts);   // contacts
        $n = count($buttons); // buttons
        if ($m === 0) return 0;

        // Build A as Fractions (m x n): A[i][j]=1 if button j touches contact i else 0
        $A = [];
        for ($i=0; $i<$m; $i++) $A[] = array_fill(0, $n, Frac::zero());
        for ($j=0; $j<$n; $j++) {
            foreach ($buttons[$j] as $idx) {
                if ($idx < 0 || $idx >= $m) return null;
                $A[$idx][$j] = Frac::one();
            }
        }

        [$R, $pivotCols] = $this->rrefAug($A, $jolts);
        if ($R === null) return null;

        // pivot map (col -> row)
        $pivotByCol = array_fill(0, $n, -1);
        foreach ($pivotCols as $r => $c) $pivotByCol[$c] = $r;

        // free columns
        $freeCols = [];
        for ($c=0; $c<$n; $c++) if ($pivotByCol[$c] === -1) $freeCols[] = $c;

        $d = count($freeCols);
        if ($d > $maxFree) return null;

        // x0F (free=0)
        $x0F = array_fill(0, $n, Frac::zero());
        for ($c=0; $c<$n; $c++) {
            $r = $pivotByCol[$c];
            if ($r !== -1) $x0F[$c] = $R[$r][$n];
        }

        // basisF vectors v_k
        $basisF = [];
        foreach ($freeCols as $fc) {
            $v = array_fill(0, $n, Frac::zero());
            $v[$fc] = Frac::one();
            foreach ($pivotCols as $r => $pc) {
                $v[$pc] = $R[$r][$fc]->neg();
            }
            $basisF[] = $v;
        }

        // Compute L = lcm of denominators in x0F and basisF
        $L = 1;
        for ($i=0; $i<$n; $i++) $L = Frac::lcm($L, $x0F[$i]->d);
        for ($k=0; $k<$d; $k++) for ($i=0; $i<$n; $i++) $L = Frac::lcm($L, $basisF[$k][$i]->d);
        if ($L <= 0) return null;

        // Safety: very large L makes residue enumeration explode (fallback unless forced)
        $Lmax = $isForced ? 256 : 64;
        if ($L > $Lmax) return null;

        // Scale: X0[i] = x0F[i] * L, V[k][i] = basisF[k][i] * L
        $X0 = array_fill(0, $n, 0);
        for ($i=0; $i<$n; $i++) $X0[$i] = $x0F[$i]->n * intdiv($L, $x0F[$i]->d);

        $V = [];
        for ($k=0; $k<$d; $k++) {
            $vk = array_fill(0, $n, 0);
            for ($i=0; $i<$n; $i++) $vk[$i] = $basisF[$k][$i]->n * intdiv($L, $basisF[$k][$i]->d);
            $V[$k] = $vk;
        }

        // d==0: unique solution, must satisfy X0 divisible by L and x>=0
        if ($d === 0) {
            for ($i=0; $i<$n; $i++) {
                if ($X0[$i] < 0) return null;
                if ((($X0[$i] % $L) + $L) % $L !== 0) return null;
            }
            // verify exact by reconstructing x = X0/L and checking A*x=b
            $x = array_fill(0, $n, 0);
            for ($j=0; $j<$n; $j++) $x[$j] = intdiv($X0[$j], $L);

            $acc = array_fill(0, count($jolts), 0);
            for ($j=0; $j<$n; $j++) {
                $cnt = $x[$j];
                if ($cnt === 0) continue;
                foreach ($buttons[$j] as $idx) $acc[$idx] += $cnt;
            }
            for ($i=0; $i<count($jolts); $i++) if ($acc[$i] !== $jolts[$i]) return null;

            return array_sum($x);
        }

        // Parameter bounds for t: start with [-limit, limit]
        $loT = array_fill(0, $d, -$limit);
        $hiT = array_fill(0, $d,  $limit);

        // Tighten bounds from nonnegativity: X0 + Σ t V >= 0
        if (!$this->tightenParamBoundsInt($X0, $V, $loT, $hiT)) return null;
        if (!$this->feasibleWithBoundsInt($X0, $V, $loT, $hiT)) return null;

        // Precompute sumV[k] and const sumX0
        $sumX0 = 0;
        for ($i=0; $i<$n; $i++) $sumX0 += $X0[$i];

        $sumV = array_fill(0, $d, 0);
        for ($k=0; $k<$d; $k++) {
            $s = 0;
            for ($i=0; $i<$n; $i++) $s += $V[$k][$i];
            $sumV[$k] = $s;
        }

        // Enumerate residue vectors r_k (t_k mod L) that satisfy:
        // for each i: X0[i] + Σ r_k V[k][i] ≡ 0 (mod L)
        $validResidues = $this->enumerateValidResidues($X0, $V, $L);
        if (empty($validResidues)) return null;

        $bestSumX = PHP_INT_MAX;

        foreach ($validResidues as $rvec) {
            // enforce t bounds (t ≡ r mod L): compute z bounds from loT/hiT
            $loZ = array_fill(0, $d, 0);
            $hiZ = array_fill(0, $d, -1);
            $ok = true;

            for ($k=0; $k<$d; $k++) {
                $r = $rvec[$k];

                // t = r + L z must lie in [loT, hiT]
                // z >= ceil((loT - r)/L), z <= floor((hiT - r)/L)
                $a = $loT[$k] - $r;
                $b = $hiT[$k] - $r;

                $loZ[$k] = $this->ceilDivSignedByPos($a, $L);
                $hiZ[$k] = $this->floorDivSignedByPos($b, $L);
                if ($loZ[$k] > $hiZ[$k]) { $ok = false; break; }
            }
            if (!$ok) continue;

            // Build base vector Xbase = X0 + Σ r V
            $Xbase = $X0;
            $sumXbase = $sumX0;
            for ($k=0; $k<$d; $k++) {
                $r = $rvec[$k];
                if ($r === 0) continue;
                $sumXbase += $r * $sumV[$k];
                for ($i=0; $i<$n; $i++) $Xbase[$i] += $r * $V[$k][$i];
            }

            // Now X = Xbase + Σ (L z_k) V_k  => coeff matrix W[k][i] = L * V[k][i]
            $W = [];
            $objCz = array_fill(0, $d, 0);
            for ($k=0; $k<$d; $k++) {
                $wk = array_fill(0, $n, 0);
                for ($i=0; $i<$n; $i++) $wk[$i] = $L * $V[$k][$i];
                $W[$k] = $wk;
                $objCz[$k] = $L * $sumV[$k];
            }

            // Feasibility with z bounds
            if (!$this->feasibleWithBoundsInt($Xbase, $W, $loZ, $hiZ)) continue;

            // BnB on z to minimize sumX (equivalently sumX/L)
            $order = range(0, $d-1);
            usort($order, fn($a,$b) => abs($objCz[$b]) <=> abs($objCz[$a]));
            $assigned = array_fill(0, $d, false);

            $dfs = function(int $pos, array $Xcur, int $sumXcur) use (
                &$dfs, &$bestSumX, $d, $order, &$assigned, $W, $loZ, $hiZ, $objCz
            ) {
                if ($sumXcur >= $bestSumX) return;

                // Feasibility prune: for each i must be possible to reach >=0 with remaining vars
                $nLocal = count($Xcur);
                for ($i=0; $i<$nLocal; $i++) {
                    $max = 0;
                    for ($k=0; $k<$d; $k++) {
                        if ($assigned[$k]) continue;
                        $a = $W[$k][$i];
                        $max += ($a >= 0) ? $a * $hiZ[$k] : $a * $loZ[$k];
                    }
                    if ($Xcur[$i] + $max < 0) return;
                }

                // Objective lower bound using remaining vars
                $lb = $sumXcur;
                for ($k=0; $k<$d; $k++) {
                    if ($assigned[$k]) continue;
                    $c = $objCz[$k];
                    $lb += ($c >= 0) ? ($c * $loZ[$k]) : ($c * $hiZ[$k]);
                    if ($lb >= $bestSumX) return;
                }
                if ($lb >= $bestSumX) return;

                if ($pos === $d) {
                    // All assigned, require Xcur >= 0
                    for ($i=0; $i<$nLocal; $i++) if ($Xcur[$i] < 0) return;
                    if ($sumXcur < $bestSumX) $bestSumX = $sumXcur;
                    return;
                }

                $k = $order[$pos];
                $assigned[$k] = true;

                // iterate z in direction that tends to reduce objective
                if ($objCz[$k] >= 0) {
                    for ($z = $loZ[$k]; $z <= $hiZ[$k]; $z++) {
                        $Xnext = $Xcur;
                        if ($z !== 0) {
                            for ($i=0; $i<$nLocal; $i++) $Xnext[$i] += $W[$k][$i] * $z;
                        }
                        $dfs($pos+1, $Xnext, $sumXcur + $objCz[$k] * $z);
                    }
                } else {
                    for ($z = $hiZ[$k]; $z >= $loZ[$k]; $z--) {
                        $Xnext = $Xcur;
                        if ($z !== 0) {
                            for ($i=0; $i<$nLocal; $i++) $Xnext[$i] += $W[$k][$i] * $z;
                        }
                        $dfs($pos+1, $Xnext, $sumXcur + $objCz[$k] * $z);
                    }
                }

                $assigned[$k] = false;
            };

            $dfs(0, $Xbase, $sumXbase);
        }

        if ($bestSumX === PHP_INT_MAX) return null;

        // Convert sumX to sum(x) = sumX / L (must be integer)
        if ($bestSumX % $L !== 0) return null;
        return intdiv($bestSumX, $L);
    }

    /* ---------- RREF for augmented matrix ---------- */

    private function rrefAug(array $A, array $b): array {
        $m = count($A);
        if ($m === 0) return [[], []];
        $n = count($A[0]);

        for ($i=0; $i<$m; $i++) $A[$i][] = new Frac((int)$b[$i], 1);

        $row = 0;
        $pivotCols = [];

        for ($col=0; $col<$n && $row<$m; $col++) {
            $sel = -1;
            for ($i=$row; $i<$m; $i++) {
                if (!$A[$i][$col]->isZero()) { $sel = $i; break; }
            }
            if ($sel === -1) continue;

            if ($sel !== $row) { $tmp = $A[$sel]; $A[$sel] = $A[$row]; $A[$row] = $tmp; }

            $pivot = $A[$row][$col];
            for ($j=$col; $j<=$n; $j++) $A[$row][$j] = $A[$row][$j]->div($pivot);

            for ($i=0; $i<$m; $i++) {
                if ($i === $row) continue;
                $factor = $A[$i][$col];
                if ($factor->isZero()) continue;
                for ($j=$col; $j<=$n; $j++) {
                    $A[$i][$j] = $A[$i][$j]->sub($factor->mul($A[$row][$j]));
                }
            }

            $pivotCols[] = $col;
            $row++;
        }

        // Inconsistency: 0..0 | c != 0
        for ($i=0; $i<$m; $i++) {
            $allZero = true;
            for ($j=0; $j<$n; $j++) {
                if (!$A[$i][$j]->isZero()) { $allZero = false; break; }
            }
            if ($allZero && !$A[$i][$n]->isZero()) return [null, null];
        }

        return [$A, $pivotCols];
    }

    /* ---------- Integer bound tightening for inequalities ---------- */

    // ceil(a/b) and floor(a/b) for b>0, a can be negative
    private function ceilDivSignedByPos(int $a, int $b): int {
        if ($b <= 0) throw new RuntimeException("ceilDivSignedByPos expects b>0");
        // ceil(a/b) = -floor((-a)/b)
        return -$this->floorDivSignedByPos(-$a, $b);
    }
    private function floorDivSignedByPos(int $a, int $b): int {
        if ($b <= 0) throw new RuntimeException("floorDivSignedByPos expects b>0");
        if ($a >= 0) return intdiv($a, $b);
        // floor( -p / b ) = -ceil(p/b)
        $p = -$a;
        return -intdiv($p + $b - 1, $b);
    }

    /**
     * Tighten [lo..hi] for parameters t_k using constraints:
     * for each i: X0[i] + Σ_k A[k][i]*t_k >= 0
     * where A is coeff matrix (d x n) in our representation "basis-like": coeff[k][i]
     *
     * Existence-tightening: for each inequality and each k assume others can be chosen to maximize LHS.
     */
    private function tightenParamBoundsInt(array $X0, array $coeff, array &$lo, array &$hi): bool {
        $d = count($coeff);
        $n = count($X0);

        $changed = true;
        $iters = 0;

        while ($changed && $iters < 250) {
            $iters++;
            $changed = false;

            for ($i=0; $i<$n; $i++) {
                for ($k=0; $k<$d; $k++) {
                    $a = $coeff[$k][$i];
                    if ($a === 0) continue;

                    $maxOther = 0;
                    for ($j=0; $j<$d; $j++) {
                        if ($j === $k) continue;
                        $aj = $coeff[$j][$i];
                        $maxOther += ($aj >= 0) ? $aj * $hi[$j] : $aj * $lo[$j];
                    }

                    // Need: X0 + a*t_k + maxOther >= 0  => a*t_k >= -(X0+maxOther)
                    $rhs = -($X0[$i] + $maxOther);

                    if ($a > 0) {
                        $newLo = $this->ceilDivSignedByPos($rhs, $a);
                        if ($newLo > $lo[$k]) { $lo[$k] = $newLo; $changed = true; }
                    } else { // a < 0 => t_k <= floor(rhs/a)
                        // floor(rhs/a) with a<0: floor(rhs/a) = -ceil(rhs/(-a))
                        $newHi = -$this->ceilDivSignedByPos($rhs, -$a);
                        if ($newHi < $hi[$k]) { $hi[$k] = $newHi; $changed = true; }
                    }

                    if ($lo[$k] > $hi[$k]) return false;
                }
            }
        }
        return true;
    }

    private function feasibleWithBoundsInt(array $X0, array $coeff, array $lo, array $hi): bool {
        $d = count($coeff);
        $n = count($X0);

        for ($i=0; $i<$n; $i++) {
            $max = 0;
            for ($k=0; $k<$d; $k++) {
                $a = $coeff[$k][$i];
                $max += ($a >= 0) ? $a * $hi[$k] : $a * $lo[$k];
            }
            if ($X0[$i] + $max < 0) return false;
        }
        return true;
    }

    /* ---------- Residue enumeration (t mod L) ---------- */

    /**
     * Enumerate all residue vectors r_k in [0..L-1]^d such that:
     * for all i: X0[i] + Σ_k r_k*V[k][i] ≡ 0 (mod L)
     *
     * Uses incremental modulo accumulation to avoid O(L^d * n * d) blowups.
     */
    private function enumerateValidResidues(array $X0, array $V, int $L): array {
        $d = count($V);
        $n = count($X0);
        if ($d === 0) return [[]];

        // current mods per i: S_i ≡ X0[i] + Σ assigned r*V mod L
        $S = array_fill(0, $n, 0);
        for ($i=0; $i<$n; $i++) $S[$i] = (($X0[$i] % $L) + $L) % $L;

        $res = [];
        $rvec = array_fill(0, $d, 0);

        $dfs = function(int $k) use (&$dfs, &$res, &$rvec, &$S, $V, $L, $d, $n) {
            if ($k === $d) {
                for ($i=0; $i<$n; $i++) if ($S[$i] !== 0) return;
                $res[] = $rvec;
                return;
            }

            // Try all residues for this parameter
            // (If L is small, brute is fine; pruning happens at end only.)
            // Lightweight early prune: none reliable without analyzing gcds; keep simple & correct.
            for ($r=0; $r<$L; $r++) {
                // apply
                $old = $S;
                if ($r !== 0) {
                    for ($i=0; $i<$n; $i++) {
                        $S[$i] = ($S[$i] + ($r * ($V[$k][$i] % $L)) ) % $L;
                    }
                }
                $rvec[$k] = $r;

                $dfs($k+1);

                // rollback
                $S = $old;
            }
        };

        // Safety cap: prevent accidental blow-up on big L^d
        $cap = 250000; // enough for L=2, d<=18 etc.
        $dfsLimited = function(int $k) use (&$dfsLimited, &$res, &$rvec, &$S, $V, $L, $d, $n, $cap) {
            if (count($res) >= $cap) return;
            if ($k === $d) {
                for ($i=0; $i<$n; $i++) if ($S[$i] !== 0) return;
                $res[] = $rvec;
                return;
            }
            for ($r=0; $r<$L; $r++) {
                $old = $S;
                if ($r !== 0) {
                    for ($i=0; $i<$n; $i++) {
                        $S[$i] = ($S[$i] + ($r * ($V[$k][$i] % $L)) ) % $L;
                    }
                }
                $rvec[$k] = $r;
                $dfsLimited($k+1);
                $S = $old;
                if (count($res) >= $cap) return;
            }
        };

        $dfsLimited(0);
        return $res;
    }

    /* ---------- Stable BnB fallback (recursive, correct) ---------- */

    public function solveBnBStable(array $buttons, array $jolts): int {
        $j = array_map('intval', $jolts);
        $m = count($j);

        // Fast exit
        $allZero = true;
        foreach ($j as $v) { if ($v !== 0) { $allZero = false; break; } }
        if ($allZero) return 0;

        // Build button infos
        $infos = [];
        foreach ($buttons as $sw) {
            $sw = array_values(array_map('intval', $sw));
            if (empty($sw)) continue;

            $mp = PHP_INT_MAX;
            foreach ($sw as $idx) $mp = min($mp, $j[$idx] ?? PHP_INT_MAX);
            if ($mp === PHP_INT_MAX) $mp = 0;
            if ($mp < 0) $mp = 0;

            $infos[] = [
                'sw' => $sw,
                'len'=> count($sw),
                'mp' => $mp,
            ];
        }
        if (empty($infos)) throw new RuntimeException("BNB: no buttons but jolts non-zero");

        // Sort: longer first, then higher mp
        usort($infos, function($a,$b){
            if ($a['len'] === $b['len']) return $b['mp'] <=> $a['mp'];
            return $b['len'] <=> $a['len'];
        });

        $btnCnt = count($infos);

        $globalMaxLen = 0;
        foreach ($infos as $inf) $globalMaxLen = max($globalMaxLen, $inf['len']);

        // remaining capacity per pos
        $remainingCapacity = [];
        $remainingCapacity[$btnCnt] = array_fill(0, $m, 0);
        for ($pos=$btnCnt-1; $pos>=0; $pos--) {
            $cap = $remainingCapacity[$pos+1];
            $mp = $infos[$pos]['mp'];
            if ($mp > 0) foreach ($infos[$pos]['sw'] as $idx) $cap[$idx] += $mp;
            $remainingCapacity[$pos] = $cap;
        }

        $best = PHP_INT_MAX;

        $ceilDiv = function(int $a, int $b): int {
            if ($b <= 0) return PHP_INT_MAX;
            if ($a <= 0) return 0;
            return intdiv($a + $b - 1, $b);
        };

        $dfs = function(int $pos, array $remaining, int $steps) use (
            &$dfs, &$best, $infos, $btnCnt, $m, $remainingCapacity, $globalMaxLen, $ceilDiv
        ) {
            if ($steps >= $best) return;

            foreach ($remaining as $v) if ($v < 0) return;

            if ($pos === $btnCnt) {
                foreach ($remaining as $v) if ($v !== 0) return;
                $best = min($best, $steps);
                return;
            }

            $cap = $remainingCapacity[$pos];
            for ($i=0; $i<$m; $i++) if ($remaining[$i] > $cap[$i]) return;

            $sumRem = 0;
            foreach ($remaining as $v) $sumRem += $v;
            if ($sumRem === 0) { $best = min($best, $steps); return; }

            $lb = $ceilDiv($sumRem, $globalMaxLen);
            if ($steps + $lb >= $best) return;

            $sw = $infos[$pos]['sw'];
            $mp = $infos[$pos]['mp'];

            $mf = $mp;
            if (!empty($sw)) {
                $localMin = PHP_INT_MAX;
                foreach ($sw as $idx) $localMin = min($localMin, $remaining[$idx]);
                $mf = min($mf, $localMin);
                if ($mf < 0) $mf = 0;
            } else {
                $mf = 0;
            }

            for ($p=0; $p<=$mf; $p++) {
                $newSteps = $steps + $p;
                if ($newSteps >= $best) break;

                if ($p === 0) {
                    $dfs($pos+1, $remaining, $newSteps);
                    continue;
                }

                $newRemaining = $remaining;
                foreach ($sw as $idx) {
                    $newRemaining[$idx] -= $p;
                    if ($newRemaining[$idx] < 0) continue 2;
                }
                $dfs($pos+1, $newRemaining, $newSteps);
            }
        };

        $dfs(0, $j, 0);

        if ($best === PHP_INT_MAX) throw new RuntimeException("BNB: no solution (search exhausted)");
        return $best;
    }
}

/* =========================
   PARALLEL RUNNER
   ========================= */

function nowSec(): int { return time(); }

function readAllFromSocket($sock): string {
    $buf = '';
    while (($chunk = socket_read($sock, 8192)) !== false && $chunk !== '') $buf .= $chunk;
    return $buf;
}

/* =========================
   CLI
   ========================= */
$args = $argv;
array_shift($args);

if (count($args) < 1) {
    fwrite(STDERR, "Usage: php ad25-10-17-fixed.php <inputfile> [--procs=8] [--limit=60] [--maxfree=3] [--status=1]\n");
    exit(1);
}

$filePath = $args[0];
$procs = 8;
$limit = 60;
$maxFree = 3;
$statusEvery = 1;

foreach ($args as $a) {
    if (preg_match('/^--procs=(\d+)$/', $a, $m)) $procs = max(1, (int)$m[1]);
    if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = max(0, (int)$m[1]);
    if (preg_match('/^--maxfree=(\d+)$/', $a, $m)) $maxFree = max(0, (int)$m[1]);
    if (preg_match('/^--status=(\d+)$/', $a, $m)) $statusEvery = max(1, (int)$m[1]);
}

$solver = new HybridSolver();
[$allButtons, $allJolts] = $solver->parseFile($filePath);

$machineIds = array_keys($allButtons);
$totalMachines = count($machineIds);

if ($procs > $totalMachines) $procs = $totalMachines;

echo "Machines: {$totalMachines}, parallel procs: {$procs}, math limit: {$limit}, maxfree: {$maxFree}\n";

$pending = [];
foreach ($machineIds as $mid) {
    $pending[] = [
        'id'=>$mid,
        'forceBnb'=>false,
        'attempt'=>0,
        'forceMath'=>($mid === 167),
    ];
}

$running = [];  // pid => info
$completed = 0;
$totalSum = 0;

$startedAt = microtime(true);
$lastStatusTs = 0;

$spawnOne = function(array $job) use (&$running, $allButtons, $allJolts, $limit, $maxFree) {
    $mid = $job['id'];
    $forceBnb = (bool)$job['forceBnb'];
    $attempt = (int)$job['attempt'];
    $forceMath = (bool)($job['forceMath'] ?? false);

    $pair = [];
    if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair)) {
        throw new RuntimeException("socket_create_pair failed");
    }

    $pid = pcntl_fork();
    if ($pid === -1) throw new RuntimeException("fork failed");

    if ($pid === 0) {
        socket_close($pair[0]);

        $t0 = microtime(true);
        try {
            $solver = new HybridSolver();
            $out = $solver->solveOne($mid, $allButtons[$mid], $allJolts[$mid], $limit, $maxFree, $forceBnb);
            $payload = [
                'ok' => true,
                'id' => $mid,
                'res' => $out['res'],
                'mode' => $out['mode'],
                'sec' => microtime(true) - $t0,
                'attempt' => $attempt,
                'forceBnb' => $forceBnb,
                'forceMath' => $forceMath,
            ];
        } catch (Throwable $e) {
            $payload = [
                'ok' => false,
                'id' => $mid,
                'err' => $e->getMessage(),
                'sec' => microtime(true) - $t0,
                'attempt' => $attempt,
                'forceBnb' => $forceBnb,
                'forceMath' => $forceMath,
            ];
        }

        @socket_write($pair[1], json_encode($payload, JSON_UNESCAPED_UNICODE));
        socket_close($pair[1]);
        exit(0);
    }

    socket_close($pair[1]);
    $running[$pid] = [
        'id'=>$mid,
        'sock'=>$pair[0],
        'start'=>microtime(true),
        'forceBnb'=>$forceBnb,
        'attempt'=>$attempt,
        'forceMath'=>$forceMath,
    ];
};

while (!empty($pending) || !empty($running)) {
    while (count($running) < $procs && !empty($pending)) {
        $job = array_shift($pending);
        $spawnOne($job);
    }

    $now = nowSec();
    if ($now - $lastStatusTs >= $statusEvery) {
        $lastStatusTs = $now;
        $elapsed = microtime(true) - $startedAt;
        $line = sprintf("[%.0fs] Progress %d/%d :: ", $elapsed, $completed, $totalMachines);
        $chunks = [];
        foreach ($running as $pid => $info) {
            $age = microtime(true) - $info['start'];
            if ($info['forceBnb']) $tag = "BNB";
            elseif (!empty($info['forceMath'])) $tag = "MATH167";
            else $tag = "AUTO";
            $chunks[] = "pid{$pid}:m{$info['id']}({$tag},a{$info['attempt']},".sprintf('%.0fs',$age).")";
        }
        echo $line . implode(" | ", $chunks) . "\n";
    }

    foreach ($running as $pid => $info) {
        $res = pcntl_waitpid($pid, $status, WNOHANG);
        if ($res <= 0) continue;

        $buf = readAllFromSocket($info['sock']);
        socket_close($info['sock']);
        unset($running[$pid]);

        $decoded = json_decode($buf, true);
        $mid = $info['id'];
        $isM167 = ($mid === 167);

        if (!is_array($decoded)) {
            $attempt = $info['attempt'] + 1;
            if ($attempt <= 1) {
                if ($isM167) {
                    echo "WARN m{$mid}: invalid child payload; retry (m167 stays MATH)\n";
                    $pending[] = ['id'=>$mid,'forceBnb'=>false,'attempt'=>$attempt,'forceMath'=>true];
                } else {
                    echo "WARN m{$mid}: invalid child payload; retry force BnB\n";
                    $pending[] = ['id'=>$mid,'forceBnb'=>true,'attempt'=>$attempt,'forceMath'=>false];
                }
                continue;
            }
            throw new RuntimeException("Machine {$mid} failed: invalid child payload (after retry)");
        }

        if (empty($decoded['ok'])) {
            $err = (string)($decoded['err'] ?? 'unknown');
            $attempt = (int)($decoded['attempt'] ?? $info['attempt']);

            if ($attempt < 1) {
                if ($isM167) {
                    echo "WARN m{$mid}: worker error: {$err} -> retry (m167 stays MATH)\n";
                    $pending[] = ['id'=>$mid,'forceBnb'=>false,'attempt'=>$attempt+1,'forceMath'=>true];
                } else {
                    echo "WARN m{$mid}: worker error: {$err} -> retry force BnB\n";
                    $pending[] = ['id'=>$mid,'forceBnb'=>true,'attempt'=>$attempt+1,'forceMath'=>false];
                }
                continue;
            }

            throw new RuntimeException("Machine {$mid} failed after retry: {$err}");
        }

        $r   = (int)$decoded['res'];
        $mode= (string)$decoded['mode'];
        $sec = (float)$decoded['sec'];

        $totalSum += $r;
        $completed++;

        echo "DONE m{$mid} => {$r}  ({$mode}, ".sprintf('%.3f',$sec)."s)\n";
    }

    usleep(20000);
}

echo "TOTAL SUM Result " . json_encode($totalSum) . "\n";
