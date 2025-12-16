fileName = "data/22-03-01.example"
fileName = "data/22-03-01.inp"

def initDict():
    global DICT
    lower = [chr(x) for x in range(ord("a"), ord("z")+1)]
    upper = [chr(x) for x in range(ord("A"), ord("Z")+1)]
    DICT = lower+upper

def code():
    total = 0
    groupIdx = 0
    collections = []
    with open(fileName) as file:
        for line in file:
            groupIdx += 1
            line = line.strip()
            collections.append(list(line))
            if (groupIdx == 3):
                symbList = list(set(collections[0]) & set(collections[1]) & set(collections[2]))
                total += DICT.index(symbList[0]) + 1
                collections = []
                groupIdx = 0

    return total


initDict()
print(code())

