#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import re
import json
import time
import socket
from typing import Any, Dict, List, Optional, Tuple


class Def:
    def __init__(self):
        self.indicators: Dict[int, Dict[int, bool]] = {}
        self.indicatorSize: Dict[int, int] = {}
        self.buttons: Dict[int, List[List[str]]] = {}
        self.joltage: Dict[int, List[str]] = {}

        self.isDebug: bool = True
        self.isMatrixPrint: bool = True

        self.lastTS: int = 0
        self.startTs: int = 0

        self.significantDigitChanged: bool = False

        self.cache: Dict[str, str] = {}
        self.cacheFileName: str = "data/ad25-10-1-cachev4-py.txt"

    # ----------------------------
    # Cache
    # ----------------------------
    def writeCache(self, _id: Any, summ: Any) -> None:
        cache = self.readCache()
        cache[str(_id)] = str(summ)

        data: List[str] = []
        for cid, csum in cache.items():
            data.append(f"{cid}==>{csum}")

        os.makedirs(os.path.dirname(self.cacheFileName) or ".", exist_ok=True)
        with open(self.cacheFileName, "w", encoding="utf-8") as f:
            f.write("\n".join(data))

    def readCache(self) -> Dict[str, str]:
        cache: Dict[str, str] = {}
        if os.path.exists(self.cacheFileName):
            with open(self.cacheFileName, "r", encoding="utf-8") as f:
                for line in f:
                    line = line.rstrip("\n")
                    parts = line.split("==>")
                    if len(parts) < 2:
                        continue
                    cache[parts[0]] = parts[1]
        return cache

    # ----------------------------
    # Input
    # ----------------------------
    def readInput(self, filePath: str) -> None:
        self.cache = self.readCache()

        with open(filePath, "r", encoding="utf-8") as f:
            lines = f.readlines()

        for _id, line in enumerate(lines):
            # indicators
            matches = re.findall(r"[.#]+", line)
            if not matches:
                raise RuntimeError("cant read")
            res = matches[0]
            self.indicatorSize[_id] = len(res)
            self.indicators[_id] = {}
            for k, ch in enumerate(res):
                self.indicators[_id][k] = (ch == "#")

            # buttons + joltage
            matches = re.findall(r"[{\d,}]+", line)
            if not matches:
                raise RuntimeError("cant read")

            res_list = matches[:]  # all matches
            jolts = res_list.pop()  # last is {..}
            jolts = jolts.replace("{", "").replace("}", "")
            jolts_arr = jolts.split(",") if jolts != "" else []
            self.joltage[_id] = jolts_arr

            self.buttons[_id] = []
            for match in res_list:
                button = match.split(",") if match != "" else []
                self.buttons[_id].append(button)

    # ----------------------------
    # Debug
    # ----------------------------
    def debug(self, val: Any, desc: str = "", isSimple: bool = False, force: bool = False) -> None:
        if (not force) and (not self.isDebug):
            return

        if not self.startTs:
            self.startTs = int(time.time())
        ts = int(time.time()) - self.startTs
        ts_str = str(ts).rjust(9, "0")
        ts_str = f"[{ts_str}]"

        if isSimple:
            print(f"{ts_str} {desc}: {val}\r")
        else:
            print(f"{ts_str} {desc}:\n{repr(val)}\n")

    # ----------------------------
    # Helpers
    # ----------------------------
    def getStringFromArray(self, arr: List[Any]) -> str:
        return "'" + "-".join(str(x) for x in arr) + "'"

    def getStringFromArrayWithKeys(self, arr: List[Any]) -> str:
        a: List[str] = []
        for key, val in enumerate(arr):
            a.append(f"btn#{key} => {val}")
        return "|" + "  ".join(a) + "|"

    # ----------------------------
    # Core result per machine
    # ----------------------------
    def getResult(self, machineId: int) -> int:
        res = True

        linearInt = self.solveLinearSystemNonNegativeIntForMachine(machineId)
        if linearInt is None:
            res = False
        else:
            for one in linearInt:
                if one < 0:
                    res = False

        if res:
            return int(sum(linearInt))

        # fallback
        if str(machineId) in self.cache:
            return int(float(self.cache[str(machineId)]))
        else:
            bnb_res = self.findMinForMachineBranchAndBound(machineId, 2)
            self.writeCache(machineId, bnb_res)
            return int(bnb_res)

    # ----------------------------
    # Parallel "code()" (fork + socketpair) — exact behavior
    # ----------------------------
    def code(self) -> int:
        self.startTs = int(time.time())

        tasks = list(self.buttons.keys())  # machineIds
        results: Dict[int, int] = {}
        children: Dict[int, socket.socket] = {}
        pid2id: Dict[int, int] = {}

        for idx, payload in enumerate(tasks):
            parent_sock, child_sock = socket.socketpair(socket.AF_UNIX, socket.SOCK_STREAM)

            pid = os.fork()
            if pid < 0:
                raise RuntimeError("fork failed")

            pid2id[pid] = idx

            if pid == 0:
                # ---- CHILD ----
                try:
                    parent_sock.close()
                    result = self.getResult(payload)
                    data = json.dumps({"id": idx, "result": result}).encode("utf-8")
                    child_sock.sendall(data)
                finally:
                    try:
                        child_sock.shutdown(socket.SHUT_RDWR)
                    except Exception:
                        pass
                    child_sock.close()
                os._exit(0)

            # ---- PARENT ----
            child_sock.close()
            children[pid] = parent_sock

        while children:
            inProg = [pid2id.get(pid, -1) for pid in list(children.keys())]
            self.debug("Still waiting for children: " + ", ".join(map(str, inProg)), "", True)

            for pid, sock in list(children.items()):
                try:
                    done_pid, _status = os.waitpid(pid, os.WNOHANG)
                except ChildProcessError:
                    done_pid = pid

                if done_pid > 0:
                    buffer = b""
                    sock.settimeout(0.0)
                    while True:
                        try:
                            chunk = sock.recv(2048)
                            if not chunk:
                                break
                            buffer += chunk
                        except (BlockingIOError, InterruptedError):
                            break
                        except Exception:
                            break

                    sock.close()

                    try:
                        decoded = json.loads(buffer.decode("utf-8")) if buffer else None
                    except Exception:
                        decoded = None

                    if decoded:
                        results[int(decoded["id"])] = int(decoded["result"])

                    children.pop(pid, None)

            time.sleep(1)

        return int(sum(results.values()))

    # ----------------------------
    # Branch & Bound (parallel) — exact logic
    # ----------------------------
    def findMinForMachineBranchAndBound(self, _id: int, workers: int = 4) -> int:
        jolt = [int(x) for x in self.joltage[_id]]
        joltCnt = len(jolt)

        allZero = True
        for v in jolt:
            if v != 0:
                allZero = False
                break
        if allZero:
            self.debug(f"Machine {_id} already zero", "BB", True)
            return 0

        buttonsInfo: List[Dict[str, Any]] = []
        for buttonId, button in enumerate(self.buttons[_id]):
            switches: List[int] = []
            for idx in button:
                switches.append(int(idx))
            ln = len(switches)
            if not ln:
                continue

            maxPress = (1 << 62)
            for idx in switches:
                if jolt[idx] < maxPress:
                    maxPress = jolt[idx]
            if maxPress <= 0:
                maxPress = 0

            buttonsInfo.append(
                {
                    "origId": buttonId,
                    "switches": switches,
                    "len": ln,
                    "maxPress": maxPress,
                }
            )

        btnCnt = len(buttonsInfo)
        if not btnCnt:
            raise RuntimeError(f"BB: machine {_id} has no effective buttons but joltage is not zero")

        # sort by len desc
        buttonsInfo.sort(key=lambda x: x["len"], reverse=True)

        globalMaxLen = 0
        for info in buttonsInfo:
            if info["len"] > globalMaxLen:
                globalMaxLen = info["len"]

        remainingCapacity: Dict[int, List[int]] = {}
        zeroCap = [0] * joltCnt
        remainingCapacity[btnCnt] = zeroCap
        for pos in range(btnCnt - 1, -1, -1):
            cap = remainingCapacity[pos + 1][:]
            maxPress = buttonsInfo[pos]["maxPress"]
            if maxPress > 0:
                for idx in buttonsInfo[pos]["switches"]:
                    cap[idx] += maxPress
            remainingCapacity[pos] = cap

        self.debug(f"Machine {_id}:BnB search starting (parallel)", "BB", True)

        firstInfo = buttonsInfo[0]
        switches0 = firstInfo["switches"]
        maxPress0 = firstInfo["maxPress"]
        if maxPress0 < 0:
            maxPress0 = 0

        maxFeasible0 = maxPress0
        if switches0:
            localMin = (1 << 62)
            for idx in switches0:
                if jolt[idx] < localMin:
                    localMin = jolt[idx]
            if localMin < maxFeasible0:
                maxFeasible0 = localMin
            if maxFeasible0 < 0:
                maxFeasible0 = 0

        jobs: List[Dict[str, Any]] = []
        for p0 in range(0, maxFeasible0 + 1):
            remaining = jolt[:]
            if p0 > 0:
                for idx in switches0:
                    remaining[idx] -= p0

            pressesVec = [0] * btnCnt
            pressesVec[0] = p0
            stepsSoFar = p0

            jobs.append(
                {
                    "remaining": remaining,
                    "stepsSoFar": stepsSoFar,
                    "pressesVec": pressesVec,
                    "startPos": 1,
                }
            )

        jobCount = len(jobs)
        if jobCount < workers:
            workers = jobCount
        if workers < 1:
            workers = 1

        self.debug(f"Machine {_id}: BnB parallel, jobs={jobCount}, workers={workers}", "BB", True)

        globalBestSteps = (1 << 62)
        globalBestPresses = None

        children: Dict[int, socket.socket] = {}

        for w in range(workers):
            parent_sock, child_sock = socket.socketpair(socket.AF_UNIX, socket.SOCK_STREAM)

            pid = os.fork()
            if pid < 0:
                raise RuntimeError("fork failed (BB)")

            if pid == 0:
                # ---- CHILD ----
                try:
                    parent_sock.close()

                    localBestSteps = (1 << 62)
                    localBestPresses = None

                    for j in range(w, jobCount, workers):
                        job = jobs[j]
                        remaining = job["remaining"]
                        stepsSoFar = job["stepsSoFar"]
                        pressesVec = job["pressesVec"]
                        startPos = job["startPos"]

                        bestSteps = localBestSteps
                        bestPresses = localBestPresses

                        self.bnbDfsForMachine(
                            _id,
                            buttonsInfo,
                            joltCnt,
                            remainingCapacity,
                            globalMaxLen,
                            startPos,
                            remaining,
                            stepsSoFar,
                            pressesVec,
                            bestSteps_ref=[bestSteps],
                            bestPresses_ref=[bestPresses],
                        )

                        bestSteps = self._ref_last_int
                        bestPresses = self._ref_last_vec

                        if bestSteps < localBestSteps:
                            localBestSteps = bestSteps
                            localBestPresses = bestPresses

                    payload = json.dumps(
                        {"bestSteps": localBestSteps, "bestPresses": localBestPresses}
                    ).encode("utf-8")
                    child_sock.sendall(payload)
                finally:
                    try:
                        child_sock.shutdown(socket.SHUT_RDWR)
                    except Exception:
                        pass
                    child_sock.close()
                os._exit(0)

            # ---- PARENT ----
            child_sock.close()
            children[pid] = parent_sock

        for pid, sock in children.items():
            os.waitpid(pid, 0)
            buffer = b""
            while True:
                chunk = sock.recv(2048)
                if not chunk:
                    break
                buffer += chunk
            sock.close()

            if buffer:
                decoded = json.loads(buffer.decode("utf-8"))
                if (
                    decoded
                    and "bestSteps" in decoded
                    and int(decoded["bestSteps"]) < globalBestSteps
                ):
                    globalBestSteps = int(decoded["bestSteps"])
                    globalBestPresses = decoded.get("bestPresses")

        if globalBestSteps >= (1 << 61):
            raise RuntimeError(f"BB: no solution found for machine {_id} (parallel)")

        dbg: List[str] = []
        if globalBestPresses is not None:
            for pos, cnt in enumerate(globalBestPresses):
                if cnt and int(cnt) > 0:
                    origId = buttonsInfo[pos]["origId"]
                    infoStr = self.getStringFromArray(self.buttons[_id][origId])
                    dbg.append(f"btn#{origId} x {cnt} {infoStr}")
        if dbg:
            self.debug(" | ".join(dbg), f"BB best combination for machine {_id} (parallel)", True)

        self.debug(f"Machine {_id} minimal presses (BB parallel) = {globalBestSteps}", "BB RESULT", True)
        return int(globalBestSteps)

    # ----------------------------
    # Linear solvers (ported 1:1)
    # ----------------------------
    def solveLinearSystem(self, A: List[List[float]], b: List[float]) -> Optional[List[float]]:
        m = len(A)
        if not m:
            return []
        n = len(A[0])
        if not n:
            return []

        M = [row[:] + [b[i]] for i, row in enumerate(A)]

        EPS = 1e-9
        row = 0

        for col in range(n):
            if row >= m:
                break
            sel = row
            for i in range(row + 1, m):
                if abs(M[i][col]) > abs(M[sel][col]):
                    sel = i
            if abs(M[sel][col]) < EPS:
                continue

            if sel != row:
                M[sel], M[row] = M[row], M[sel]

            div = M[row][col]
            for j in range(col, n + 1):
                M[row][j] /= div

            for i in range(m):
                if i == row:
                    continue
                factor = M[i][col]
                if abs(factor) < EPS:
                    continue
                for j in range(col, n + 1):
                    M[i][j] -= factor * M[row][j]
            row += 1

        for i in range(m):
            allZero = True
            for j in range(n):
                if abs(M[i][j]) > EPS:
                    allZero = False
                    break
            if allZero and abs(M[i][n]) > EPS:
                return None

        x = [0.0] * n
        for i in range(m):
            pivotCol = -1
            for j in range(n):
                if abs(M[i][j] - 1.0) < EPS:
                    pivotCol = j
                    break
            if pivotCol >= 0:
                x[pivotCol] = M[i][n]
        return x

    def solveLinearSystemNonNegativeInt(
        self, A: List[List[float]], b: List[int], searchLimit: int = 30
    ) -> Optional[List[int]]:
        m = len(A)
        if not m:
            return []
        n = len(A[0])
        if not n:
            return []

        A_copy = [row[:] for row in A]
        x0 = self.gaussianParticularSolution(A_copy, b)
        if x0 is None:
            return None

        pivotCols: List[int] = []
        R = self.rrefMatrix([row[:] for row in A], pivotCols)
        basis = self.buildNullspaceBasis(R, pivotCols, n)
        numFree = len(basis)

        bestX: Optional[List[int]] = None
        bestSum = (1 << 62)

        A_local = A
        b_local = b

        def checkSolution(xCandidate: List[float]) -> None:
            nonlocal bestX, bestSum
            xInt: List[int] = []
            for i in range(n):
                xi = int(round(xCandidate[i]))
                if xi < 0:
                    return
                xInt.append(xi)

            for r in range(m):
                s = 0
                for c in range(n):
                    s += int(A_local[r][c]) * xInt[c]
                if s != b_local[r]:
                    return

            ssum = sum(xInt)
            if ssum < bestSum:
                bestSum = ssum
                bestX = xInt

        if numFree == 0:
            self.debug("No free vars, check unique solution", "INT solver", True)
            checkSolution(x0)
            return bestX

        if numFree == 1:
            for alpha in range(-searchLimit, searchLimit + 1):
                xCand = x0[:]
                for i in range(n):
                    xCand[i] += alpha * basis[0][i]
                checkSolution(xCand)
        elif numFree == 2:
            for alpha in range(-searchLimit, searchLimit + 1):
                for beta in range(-searchLimit, searchLimit + 1):
                    xCand = x0[:]
                    for i in range(n):
                        xCand[i] += alpha * basis[0][i] + beta * basis[1][i]
                    checkSolution(xCand)
        else:
            self.debug(f"Too many free vars ({numFree}), INT solver skipped", "INT solver", True)
            return None

        return bestX

    def rrefMatrix(self, A: List[List[float]], pivotCols: List[int]) -> List[List[float]]:
        m = len(A)
        if not m:
            pivotCols.clear()
            return A
        n = len(A[0])

        EPS = 1e-9
        row = 0
        pivotCols.clear()

        for col in range(n):
            if row >= m:
                break
            sel = -1
            for i in range(row, m):
                if abs(A[i][col]) > EPS:
                    if sel == -1 or abs(A[i][col]) > abs(A[sel][col]):
                        sel = i
            if sel == -1:
                continue

            if sel != row:
                A[sel], A[row] = A[row], A[sel]

            div = A[row][col]
            for j in range(col, n):
                A[row][j] /= div

            for i in range(m):
                if i == row:
                    continue
                factor = A[i][col]
                if abs(factor) < EPS:
                    continue
                for j in range(col, n):
                    A[i][j] -= factor * A[row][j]

            pivotCols.append(col)
            row += 1

        return A

    def buildNullspaceBasis(self, R: List[List[float]], pivotCols: List[int], n: int) -> List[List[float]]:
        basis: List[List[float]] = []
        EPS = 1e-8

        pivotByCol = [-1] * n
        for rowIndex, col in enumerate(pivotCols):
            pivotByCol[col] = rowIndex

        freeCols = [j for j in range(n) if pivotByCol[j] == -1]

        for freeCol in freeCols:
            v = [0.0] * n
            v[freeCol] = 1.0

            for k in range(len(pivotCols) - 1, -1, -1):
                col = pivotCols[k]
                s = 0.0
                for j in range(col + 1, n):
                    if abs(R[k][j]) > EPS:
                        s += R[k][j] * v[j]
                v[col] = -s
            basis.append(v)

        return basis

    def gaussianParticularSolution(self, A: List[List[float]], b: List[int]) -> Optional[List[float]]:
        m = len(A)
        if not m:
            return []
        n = len(A[0])
        if not n:
            return []

        M = [row[:] + [float(b[i])] for i, row in enumerate(A)]

        EPS = 1e-9
        row = 0

        for col in range(n):
            if row >= m:
                break
            sel = row
            for i in range(row + 1, m):
                if abs(M[i][col]) > abs(M[sel][col]):
                    sel = i

            if abs(M[sel][col]) < EPS:
                continue

            if sel != row:
                M[sel], M[row] = M[row], M[sel]

            div = M[row][col]
            for j in range(col, n + 1):
                M[row][j] /= div

            for i in range(m):
                if i == row:
                    continue
                factor = M[i][col]
                if abs(factor) < EPS:
                    continue
                for j in range(col, n + 1):
                    M[i][j] -= factor * M[row][j]

            row += 1

        for i in range(m):
            allZero = True
            for j in range(n):
                if abs(M[i][j]) > EPS:
                    allZero = False
                    break
            if allZero and abs(M[i][n]) > EPS:
                return None

        x = [0.0] * n
        for i in range(m):
            pivotCol = -1
            for j in range(n):
                if abs(M[i][j] - 1.0) < 1e-6:
                    pivotCol = j
                    break
            if pivotCol >= 0:
                x[pivotCol] = M[i][n]
        return x

    def solveLinearSystemNonNegativeIntForMachine(self, _id: int) -> Optional[List[int]]:
        jolt = [int(x) for x in self.joltage[_id]]
        size = len(jolt)

        K = len(self.buttons[_id])

        matrixA: List[List[float]] = [[0.0] * K for _ in range(size)]
        for buttonId, button in enumerate(self.buttons[_id]):
            for idx_s in button:
                idx = int(idx_s)
                if idx < 0 or idx >= size:
                    raise RuntimeError(f"button {buttonId} bad index {idx} on machine {_id}")
                matrixA[idx][buttonId] = 1.0

        res = self.solveLinearSystemNonNegativeInt(matrixA, jolt, 30)
        if res is None:
            self.debug("No non-negative integer solution by linear method", f"Machine: {_id}", True)
            return None

        self.debug(self.getStringFromArrayWithKeys(res), f"Linear INT solution for machine {_id}", True)
        return res

    # ----------------------------
    # DFS for BnB (ported; uses a small ref hack to mimic PHP "&")
    # ----------------------------
    def bnbDfsForMachine(
        self,
        _id: int,
        buttonsInfo: List[Dict[str, Any]],
        joltCnt: int,
        remainingCapacity: Dict[int, List[int]],
        globalMaxLen: int,
        pos: int,
        remaining: List[int],
        stepsSoFar: int,
        pressesVec: List[int],
        bestSteps_ref: List[int],
        bestPresses_ref: List[Optional[List[int]]],
    ) -> None:
        # store latest refs in self to allow caller to read back like PHP ref behavior
        def ceilDiv(a: int, b: int) -> int:
            if b <= 0:
                return (1 << 62)
            if a <= 0:
                return 0
            return (a + b - 1) // b

        bestSteps = bestSteps_ref[0]
        bestPressesVec = bestPresses_ref[0]

        if stepsSoFar >= bestSteps:
            self._ref_last_int = bestSteps
            self._ref_last_vec = bestPressesVec
            return

        for v in remaining:
            if v < 0:
                self._ref_last_int = bestSteps
                self._ref_last_vec = bestPressesVec
                return

        btnCnt = len(buttonsInfo)

        if pos == btnCnt:
            for v in remaining:
                if v != 0:
                    self._ref_last_int = bestSteps
                    self._ref_last_vec = bestPressesVec
                    return
            if stepsSoFar < bestSteps:
                bestSteps = stepsSoFar
                bestPressesVec = pressesVec[:]
            self._ref_last_int = bestSteps
            self._ref_last_vec = bestPressesVec
            return

        cap = remainingCapacity[pos]
        for i in range(joltCnt):
            if remaining[i] > cap[i]:
                self._ref_last_int = bestSteps
                self._ref_last_vec = bestPressesVec
                return

        sumRem = sum(remaining)
        if sumRem == 0:
            if stepsSoFar < bestSteps:
                bestSteps = stepsSoFar
                bestPressesVec = pressesVec[:]
            self._ref_last_int = bestSteps
            self._ref_last_vec = bestPressesVec
            return

        theoreticalMin = ceilDiv(sumRem, globalMaxLen)
        if stepsSoFar + theoreticalMin >= bestSteps:
            self._ref_last_int = bestSteps
            self._ref_last_vec = bestPressesVec
            return

        info = buttonsInfo[pos]
        switches = info["switches"]
        maxPress = info["maxPress"]
        if maxPress < 0:
            maxPress = 0

        maxFeasible = maxPress
        if switches:
            localMin = (1 << 62)
            for idx in switches:
                if remaining[idx] < localMin:
                    localMin = remaining[idx]
            if localMin < maxFeasible:
                maxFeasible = localMin
            if maxFeasible < 0:
                maxFeasible = 0

        for presses in range(0, maxFeasible + 1):
            newSteps = stepsSoFar + presses
            if newSteps >= bestSteps:
                break

            if presses > 0:
                newRemaining = remaining[:]
                ok = True
                for idx in switches:
                    newRemaining[idx] -= presses
                    if newRemaining[idx] < 0:
                        ok = False
                        break
                if not ok:
                    continue
            else:
                newRemaining = remaining

            newPressesVec = pressesVec[:]
            newPressesVec[pos] = presses

            self.bnbDfsForMachine(
                _id,
                buttonsInfo,
                joltCnt,
                remainingCapacity,
                globalMaxLen,
                pos + 1,
                newRemaining,
                newSteps,
                newPressesVec,
                bestSteps_ref=[bestSteps],
                bestPresses_ref=[bestPressesVec],
            )
            bestSteps = self._ref_last_int
            bestPressesVec = self._ref_last_vec

        self._ref_last_int = bestSteps
        self._ref_last_vec = bestPressesVec

    # ----------------------------
    # run()
    # ----------------------------
    def run(self) -> None:
        filePath = "data/25-10-1.example"
        self.isMatrixPrint = True
        self.isDebug = True

        filePath = "data/25-10-1.inp"
        self.isMatrixPrint = False
        self.isDebug = True

        # filePath = "data/25-10-2.inp"; self.isMatrixPrint = True; self.isDebug = True
        # filePath = "data/25-10-3.inp"; self.isMatrixPrint = True; self.isDebug = True

        self.readInput(filePath)
        print("TOTAL SUM Result " + json.dumps(self.code()) + "\r\n")


if __name__ == "__main__":
    d = Def()
    d.run()
