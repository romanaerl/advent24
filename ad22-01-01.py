
f = "data/22-01-01.example"
f = "data/22-01-01.inp"

bestSum = 0
sumNow = 0
allSums = []
with open(f, encoding="utf-8") as file:
    for line in file:
        line = line.strip()
        if line == "":
            allSums.append(sumNow)
            sumNow = 0
        else:
            sumNow += int(line)
            if sumNow > bestSum:
                bestSum = sumNow

    allSums.append(sumNow)

print(allSums)
print(sorted(allSums))

result = sum(sorted(allSums)[-3:])
print(result)
