fileName = "data/22-03-01.example"
# fileName = "data/22-03-01.inp"



DICT = []


def initDict():
    lower = [chr(x) for x in range(ord("a"), ord("z")+1)]
    upper = [chr(x) for x in range(ord("A"), ord("Z")+1)]
    DICT = lower+upper
    return DICT

def code():
    total = 0
    with open(fileName) as file:
        for line in file:
            line = line.strip()
            sublen = int(len(line)/2)
            a = line[0:sublen]
            b = line[-sublen:]
            symbList = list(set(a) & set(b))
            total += DICT.index(symbList[0]) + 1
    return total


DICT = initDict()
print(code())

