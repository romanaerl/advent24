#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import re
import sys
import json
import time
import argparse
import multiprocessing as mp
from typing import Dict, List, Tuple, Optional


# ----------------------------
# Input parser
# ----------------------------
def read_input(file_path: str) -> Tuple[Dict[int, List[List[int]]], Dict[int, List[int]]]:
    buttons: Dict[int, List[List[int]]] = {}
    joltage: Dict[int, List[int]] = {}

    pat = re.compile(r"[{\d,}]+")
    with open(file_path, "r", encoding="utf-8") as f:
        lines = f.readlines()

    for mid, line in enumerate(lines):
        chunks = pat.findall(line)
        if not chunks:
            raise RuntimeError(f"cant read: no numeric chunks on line {mid}")

        j = chunks.pop()
        j = j.replace("{", "").replace("}", "")
        jolts = [int(x) for x in j.split(",") if x != ""]
        joltage[mid] = jolts

        btns: List[List[int]] = []
        for ch in chunks:
            if not ch:
                btns.append([])
            else:
                btns.append([int(x) for x in ch.split(",") if x != ""])
        buttons[mid] = btns

    return buttons, joltage


def machine_stats(btns: List[List[int]]) -> Tuple[int, float, int]:
    lens = [len(b) for b in btns if b]
    if not lens:
        return 0, 0.0, 0
    return len(lens), (sum(lens) / len(lens)), max(lens)


# ----------------------------
# BnB core
# ----------------------------
def _bnb_core(mid: int, infos: List[Tuple[int, Tuple[int, ...], int, int]], jolts: List[int]) -> int:
    if not jolts or all(v == 0 for v in jolts):
        return 0

    n = len(jolts)
    btnCnt = len(infos)

    maxLenFromPos = [0] * (btnCnt + 1)
    cur = 0
    for pos in range(btnCnt - 1, -1, -1):
        ln = infos[pos][2]
        if ln > cur:
            cur = ln
        maxLenFromPos[pos] = cur

    remainingCapacity = [[0] * n for _ in range(btnCnt + 1)]
    for pos in range(btnCnt - 1, -1, -1):
        cap_next = remainingCapacity[pos + 1]
        cap_cur = remainingCapacity[pos]
        cap_cur[:] = cap_next
        mp0 = infos[pos][3]
        if mp0 > 0:
            for idx in infos[pos][1]:
                cap_cur[idx] += mp0

    INF = 10**18
    best = INF

    remaining = jolts[:]
    pressesVec = [0] * btnCnt
    stack: List[List] = []  # [fpos, nextPress, maxFeasible, switches]

    pos = 0
    stepsSoFar = 0

    while True:
        ok = True

        for v in remaining:
            if v < 0:
                ok = False
                break

        if ok:
            cap = remainingCapacity[pos]
            for i in range(n):
                if remaining[i] > cap[i]:
                    ok = False
                    break

        sumRem = 0
        if ok:
            for v in remaining:
                sumRem += v
            if sumRem == 0:
                if stepsSoFar < best:
                    best = stepsSoFar
                ok = False
            else:
                ml = maxLenFromPos[pos] if pos < btnCnt else 0
                if ml <= 0:
                    ok = False
                else:
                    lb = (sumRem + ml - 1) // ml
                    if stepsSoFar + lb >= best:
                        ok = False

        if ok and pos < btnCnt:
            _bid, sw, _ln, mp0 = infos[pos]
            if mp0 > 0:
                localMin = min(remaining[i] for i in sw)
                mf = mp0 if mp0 < localMin else localMin
                if mf < 0:
                    mf = 0
            else:
                mf = 0

            stack.append([pos, 0, mf, sw])
            pos += 1
            continue

        while stack:
            fpos, nextPress, mf, sw = stack[-1]

            applied = pressesVec[fpos]
            if applied:
                for idx in sw:
                    remaining[idx] += applied
                stepsSoFar -= applied
                pressesVec[fpos] = 0

            if nextPress <= mf:
                p = nextPress
                stack[-1][1] += 1

                if p:
                    feasible = True
                    for idx in sw:
                        if remaining[idx] - p < 0:
                            feasible = False
                            break
                    if not feasible:
                        continue
                    for idx in sw:
                        remaining[idx] -= p
                    stepsSoFar += p
                    pressesVec[fpos] = p

                pos = fpos + 1
                break
            else:
                stack.pop()
                pos = fpos
        else:
            break

    if best >= INF:
        raise RuntimeError(f"BB: no solution found for machine {mid}")
    return int(best)


def solve_machine_bnb_fast(mid: int, btns: List[List[int]], jolts: List[int]) -> int:
    if not jolts or all(v == 0 for v in jolts):
        return 0

    infos: List[Tuple[int, Tuple[int, ...], int, int]] = []
    for bid, sw in enumerate(btns):
        if not sw:
            continue
        mp0 = min(jolts[i] for i in sw)
        mp0 = mp0 if mp0 > 0 else 0
        infos.append((bid, tuple(sw), len(sw), mp0))

    if not infos:
        raise RuntimeError(f"BB: machine {mid} has no effective buttons but joltage is not zero")

    infos.sort(key=lambda x: (x[2], x[3]), reverse=True)
    return _bnb_core(mid, infos, jolts)


def solve_machine_bnb_altorder(mid: int, btns: List[List[int]], jolts: List[int]) -> int:
    if not jolts or all(v == 0 for v in jolts):
        return 0

    tmp: List[Tuple[int, Tuple[int, ...], int, int, int]] = []
    for bid, sw in enumerate(btns):
        if not sw:
            continue
        mp0 = min(jolts[i] for i in sw)
        mp0 = mp0 if mp0 > 0 else 0
        ln = len(sw)
        tmp.append((bid, tuple(sw), ln, mp0, bid))

    if not tmp:
        raise RuntimeError(f"BB: machine {mid} has no effective buttons but joltage is not zero")

    tmp.sort(key=lambda x: (x[3], -x[2], x[4]))
    infos = [(bid, sw, ln, mp0) for (bid, sw, ln, mp0, _bid2) in tmp]
    return _bnb_core(mid, infos, jolts)


def solve_machine_cpsat(btns: List[List[int]], jolts: List[int], time_limit_sec: float) -> Optional[int]:
    try:
        from ortools.sat.python import cp_model
    except Exception:
        return None

    if not jolts or all(v == 0 for v in jolts):
        return 0

    n = len(jolts)
    m = len(btns)

    model = cp_model.CpModel()
    ub = max(jolts) if jolts else 0
    x = [model.NewIntVar(0, ub, f"x{j}") for j in range(m)]

    for i in range(n):
        cols = []
        for j in range(m):
            if i in btns[j]:
                cols.append(x[j])
        model.Add(sum(cols) == int(jolts[i]))

    model.Minimize(sum(x))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = float(time_limit_sec)
    solver.parameters.num_search_workers = 8

    status = solver.Solve(model)
    if status in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        return int(solver.ObjectiveValue())
    return None


# ----------------------------
# Race strategies
# ----------------------------
def _strategy_entry(q: mp.Queue, mid: int, btns: List[List[int]], jolts: List[int], name: str, timeout: float):
    t0 = time.time()
    try:
        if name == "S1_BNB":
            res = solve_machine_bnb_fast(mid, btns, jolts)
        elif name == "S2_ALT":
            res = solve_machine_bnb_altorder(mid, btns, jolts)
        elif name == "S3_CPSAT":
            r = solve_machine_cpsat(btns, jolts, time_limit_sec=timeout)
            if r is None:
                raise RuntimeError("CP-SAT unavailable or no solution within time")
            res = r
        else:
            raise RuntimeError(f"Unknown strategy {name}")
        q.put(("ok", name, int(res), time.time() - t0))
    except Exception as e:
        q.put(("err", name, str(e), time.time() - t0))


def solve_machine_race(ctx: mp.context.BaseContext,
                       mid: int,
                       btns: List[List[int]],
                       jolts: List[int],
                       race_timeout: float,
                       enable_cpsat: bool) -> Tuple[Optional[int], str]:
    """
    Returns (result_or_None, winner_name_or_reason).
    Never raises on timeout.
    """
    q: mp.Queue = ctx.Queue()
    strategies = ["S1_BNB", "S2_ALT"]
    if enable_cpsat:
        strategies.append("S3_CPSAT")

    procs: List[mp.Process] = []
    for name in strategies:
        p = ctx.Process(target=_strategy_entry, args=(q, mid, btns, jolts, name, race_timeout))
        p.daemon = False
        procs.append(p)
        p.start()

    deadline = time.time() + race_timeout
    errors = []

    try:
        while time.time() < deadline:
            try:
                kind, name, payload, _elapsed = q.get(timeout=0.2)
            except Exception:
                continue

            if kind == "ok":
                for p in procs:
                    if p.is_alive():
                        p.terminate()
                for p in procs:
                    p.join(timeout=0.5)
                return int(payload), name
            else:
                errors.append((name, payload))

        # timeout: kill all, return None (no error)
        for p in procs:
            if p.is_alive():
                p.terminate()
        for p in procs:
            p.join(timeout=0.5)
        return None, f"timeout({race_timeout}s)"
    finally:
        for p in procs:
            if p.is_alive():
                p.terminate()
            p.join(timeout=0.2)


# ----------------------------
# Worker
# ----------------------------
def worker_main(worker_idx: int,
                mids: List[int],
                buttons: Dict[int, List[List[int]]],
                joltage: Dict[int, List[int]],
                out_q: mp.Queue,
                race_cfg: Tuple[int, float, int, float, float, str]):
    race_buttons, race_avglen, race_maxlen, race_timeout, hard_timeout, on_timeout = race_cfg

    # detect OR-Tools
    cpsat_ok = False
    try:
        import ortools  # type: ignore
        cpsat_ok = True
    except Exception:
        cpsat_ok = False

    ctx = mp.get_context()

    out_q.put(("start", worker_idx, len(mids)))
    partial_sum = 0
    done = 0

    for mid in mids:
        out_q.put(("doing", worker_idx, mid, done))

        btns = buttons[mid]
        jolts = joltage[mid]

        bcnt, avglen, maxlen = machine_stats(btns)
        is_race = (bcnt >= race_buttons) and (avglen >= race_avglen or maxlen >= race_maxlen)

        mode = "SINGLE"
        winner = "S1_BNB"

        try:
            if is_race:
                mode = "RACE"
                res, win = solve_machine_race(ctx, mid, btns, jolts, race_timeout, cpsat_ok)
                if res is not None:
                    winner = win
                else:
                    # Timeout handling (no crash)
                    winner = win
                    if on_timeout == "skip":
                        # skip machine => treat as 0 but report
                        res = 0
                        mode = "SKIPPED"
                    else:
                        # fallback path
                        # 1) CP-SAT with hard timeout (if available)
                        if cpsat_ok:
                            r2 = solve_machine_cpsat(btns, jolts, time_limit_sec=hard_timeout)
                            if r2 is not None:
                                res = r2
                                mode = "FALLBACK"
                                winner = "S3_CPSAT"
                            else:
                                # 2) long BnB (no race) - may still be heavy
                                res = solve_machine_bnb_fast(mid, btns, jolts)
                                mode = "FALLBACK"
                                winner = "S1_BNB_LONG"
                        else:
                            # no ortools => just long BnB
                            res = solve_machine_bnb_fast(mid, btns, jolts)
                            mode = "FALLBACK"
                            winner = "S1_BNB_LONG"
            else:
                res = solve_machine_bnb_fast(mid, btns, jolts)

        except Exception as e:
            out_q.put(("error", worker_idx, mid, str(e)))
            return

        partial_sum += int(res)
        done += 1
        out_q.put(("done", worker_idx, mid, int(res), done, mode, winner))

    out_q.put(("finish", worker_idx, int(partial_sum), done))


# ----------------------------
# Orchestrator
# ----------------------------
def split_round_robin(items: List[int], k: int) -> List[List[int]]:
    buckets = [[] for _ in range(k)]
    for i, x in enumerate(items):
        buckets[i % k].append(x)
    return buckets


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("input", nargs="?", default="data/25-10-1.inp")
    ap.add_argument("--workers", type=int, default=0)
    ap.add_argument("--debug", action="store_true")
    ap.add_argument("--status-interval", type=float, default=1.0)

    ap.add_argument("--race-buttons", type=int, default=12)
    ap.add_argument("--race-avglen", type=float, default=5.0)
    ap.add_argument("--race-maxlen", type=int, default=7)
    ap.add_argument("--race-timeout", type=float, default=30.0)
    ap.add_argument("--hard-timeout", type=float, default=120.0, help="fallback CP-SAT time budget per hard machine")
    ap.add_argument("--on-timeout", choices=["fallback", "skip"], default="fallback")

    args = ap.parse_args()
    t0 = time.time()

    buttons, joltage = read_input(args.input)
    mids = sorted(buttons.keys())
    total_jobs = len(mids)
    if total_jobs == 0:
        print("TOTAL SUM Result " + json.dumps(0))
        return

    w = args.workers if args.workers and args.workers > 0 else min(mp.cpu_count(), 8)
    w = max(1, min(w, total_jobs))

    per_worker_mids = split_round_robin(mids, w)

    worker_total = [len(per_worker_mids[i]) for i in range(w)]
    worker_done = [0] * w
    worker_current: List[Optional[int]] = [None] * w
    worker_mode: List[str] = ["-"] * w

    results: Dict[int, int] = {}

    ctx = mp.get_context()
    q: mp.Queue = ctx.Queue()
    procs: List[mp.Process] = []

    race_cfg = (args.race_buttons, args.race_avglen, args.race_maxlen, args.race_timeout, args.hard_timeout, args.on_timeout)

    for i in range(w):
        p = ctx.Process(target=worker_main, args=(i, per_worker_mids[i], buttons, joltage, q, race_cfg))
        p.daemon = False
        procs.append(p)
        p.start()

    last_status = 0.0
    finished_workers = 0

    def print_status(force: bool = False):
        nonlocal last_status
        now = time.time()
        if not force and (now - last_status) < args.status_interval:
            return
        last_status = now

        done_all = sum(worker_done)
        parts = []
        for i in range(w):
            cur = worker_current[i]
            cur_s = f"m{cur}" if cur is not None else "-"
            parts.append(f"W{i}:{worker_done[i]}/{worker_total[i]} cur={cur_s} mode={worker_mode[i]}")
        print(f"[{int(now - t0):06d}] Progress {done_all}/{total_jobs} :: " + " | ".join(parts))

    while finished_workers < w:
        try:
            msg = q.get(timeout=0.2)
        except Exception:
            print_status(False)
            finished_workers = sum(1 for p in procs if not p.is_alive())
            continue

        kind = msg[0]

        if kind == "start":
            print_status(True)

        elif kind == "doing":
            _, wi, mid, done = msg
            worker_current[wi] = mid
            worker_done[wi] = done
            worker_mode[wi] = "..."
            print_status(False)

        elif kind == "done":
            _, wi, mid, res, done, mode, winner = msg
            results[int(mid)] = int(res)
            worker_done[wi] = int(done)
            worker_mode[wi] = f"{mode}/{winner}"
            print_status(False)

        elif kind == "finish":
            _, wi, _psum, done = msg
            worker_current[wi] = None
            worker_done[wi] = int(done)
            worker_mode[wi] = "DONE"
            print_status(True)
            finished_workers = sum(1 for p in procs if not p.is_alive())

        elif kind == "error":
            _, wi, mid, err = msg
            print_status(True)
            for p in procs:
                if p.is_alive():
                    p.terminate()
            for p in procs:
                p.join(timeout=0.5)
            raise RuntimeError(f"Worker {wi} error (mid={mid}): {err}")

    for p in procs:
        p.join()

    total = sum(results.get(mid, 0) for mid in mids)
    print("TOTAL SUM Result " + json.dumps(int(total)))

    if args.debug:
        dt = time.time() - t0
        print(f"Done in {dt:.3f}s")
        try:
            import ortools  # type: ignore
            print("OR-Tools: available")
        except Exception:
            print("OR-Tools: NOT available (install: pip install ortools)")


if __name__ == "__main__":
    main()
