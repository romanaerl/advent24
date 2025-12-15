
f = "data/22-02-01.example"
f = "data/22-02-01.inp"

WEIGHT = {'X': 1, 'Y': 2, 'Z': 3}

PRIZE = {0: 0, 1: 3, 2: 6} # lost, draw, win
DEPEND = {"A": "ZXY", "B": "XYZ", "C": "YZX"}

def roundResult(a, b):
    dep = DEPEND[a]
    return int(PRIZE[dep.index(b)])

def calcRound(a, b):
    total = WEIGHT[b]
    total += roundResult(a, b)
    return total

def chooseB(a, b):
    return DEPEND[a][list("XYZ").index(b)]

total = 0
with open(f, encoding="utf-8") as file:
    for line in file:
        line = line.strip().split(' ')
        line = [str(x) for x in line]
        newb = chooseB(line[0], line[1])

        total += calcRound(line[0], newb)

print(total)
