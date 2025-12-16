fileName = "data/22-04-01.example"
fileName = "data/22-04-01.inp"

def init():
    global DICT

def code():
    total = 0
    with open(fileName) as lines:
        for line in lines:
            pairs = line.strip().split(',')
            a = pairs[0].split('-')
            b = pairs[1].split('-')
            a = list(map(int, a))
            b = list(map(int, b))
            aa = set( range(a[0],a[1]+1 ))
            bb = set(range(b[0],b[1]+1))
            cc = aa & bb
            if len(cc) > 0:
                total += 1



    return total


init()
print(code())

