from main import rslog
from itertools import permutations
import sys

def codeMain():
    readMatrix()
    operMutesVars = operMutes()

def operMutes():
    var = list(permutations('*+', 2))
    rslog(var)
    return var


def readMatrix():
    filename = "data/71.example"
    answersAll = []
    numbersAll = []
    with open("data/71.example") as f:
        for line in f:
            line = line.strip()
            answersStr, numbersStr = line.split(':')
            numbersAll.append(numbersStr.strip().split(' '))
            answersAll.append(int(answersStr.strip()))
    rslog(answersAll)
    rslog(numbersAll)



def runMain():
    codeMain()

runMain()
