#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Solve ONE given machine instance exactly (min total presses), using CP-SAT (OR-Tools).
This is the most reliable/fast way for your “stuck” machine.

Install:
  pip install ortools

Run:
  python3 solve_one_machine.py
"""

from __future__ import annotations

from typing import List
import sys


def solve_one(btns: List[List[int]], jolts: List[int], time_limit_sec: float = 30.0, workers: int = 8) -> int:
    try:
        from ortools.sat.python import cp_model
    except Exception:
        print("ERROR: OR-Tools not installed. Install with: pip install ortools", file=sys.stderr)
        raise

    n = len(jolts)
    m = len(btns)

    model = cp_model.CpModel()

    # Upper bound for x_j: cannot exceed max joltage (safe)
    ub = max(jolts) if jolts else 0
    x = [model.NewIntVar(0, ub, f"x{j}") for j in range(m)]

    # Constraints: for each index i, sum x_j over buttons that contain i equals joltage[i]
    for i in range(n):
        terms = []
        for j in range(m):
            if i in btns[j]:
                terms.append(x[j])
        model.Add(sum(terms) == jolts[i])

    # Objective: minimize total number of presses
    model.Minimize(sum(x))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = float(time_limit_sec)
    solver.parameters.num_search_workers = int(workers)

    status = solver.Solve(model)
    if status not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        raise RuntimeError(f"No solution found within time limit ({time_limit_sec}s). Status={status}")

    # Print solution vector (optional, useful for debugging)
    sol = [solver.Value(v) for v in x]
    obj = int(solver.ObjectiveValue())

    print("Objective (min total presses):", obj)
    print("Presses per button (in given order):")
    for j, v in enumerate(sol):
        if v:
            print(f"  button#{j}: {v}")

    # Verify (optional)
    # for i in range(n):
    #     s = sum(sol[j] for j in range(m) if i in btns[j])
    #     assert s == jolts[i], (i, s, jolts[i])

    return obj


def main():
    # Your instance (buttons are listed in the same order as in the prompt)
    buttons = [
        [1, 4, 5, 6, 7, 8],
        [0, 7],
        [0, 1, 2, 3, 5, 6, 7],
        [0, 1, 2, 3, 4, 6, 7],
        [1, 2, 4, 7, 8],
        [0, 1, 5, 6],
        [6, 8, 9],
        [4, 6, 9],
        [1, 2, 3, 4, 5, 6, 7, 8],
        [0, 4, 7, 8],
        [0, 3, 5, 7, 8, 9],
        [1, 2, 3, 4, 9],
        [0, 1, 2, 3, 5, 7, 8],
    ]

    joltage = [115, 123, 88, 96, 101, 102, 112, 144, 110, 54]

    solve_one(buttons, joltage, time_limit_sec=60.0, workers=8)


if __name__ == "__main__":
    main()
