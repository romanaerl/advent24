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
            if (
                (a[0] >= b[0]) & (a[0] <= b[1]) & (a[1] >= b[0]) & (a[1] <= b[1])) | ((b[0] >= a[0]) & (b[0] <= a[1]) & (b[1] >= a[0]) & (b[1] <= a[1])):
                total += 1



    return total


init()
print(code())

