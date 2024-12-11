import time
import logging

logging.basicConfig(level=logging.INFO)
def rslog(value, name):
    logging.info(f"{name}: {value}")

def add_timer(label):
    global timers
    timers[label] = time.time()

def show_timers():
    global timers
    for label, start_time in timers.items():
        elapsed = time.time() - start_time
        logging.info(f"{label}: {elapsed:.2f} seconds")

timers = {}

class Def:
    def __init__(self):
        self.mat = []
        self.heads = {}

    def code(self):
        self.read_array("data/11-1.inp")
        # self.read_array("data/11-1.example")
        self.print_array(self.mat, "INITIAL")

        # for i in range(75):
        #     rslog(i, '$i')
        total_sum = self.blink_one_by_one(75 - 1)
        #     self.print_array(self.mat, f"Iteration {i}:")

        rslog(total_sum, "$sum")

    def blink_one_by_one(self, count):
        total_sum = 0
        for key, val in enumerate(self.mat):
            rslog(key, "$key")
            total_sum += 1 + self.blink_one(val, count)
        return total_sum

    def blink_one(self, val, count, cur_count=0):
        total_sum = 0

        if val in self.heads and cur_count in self.heads[val]:
            return self.heads[val][cur_count]

        if cur_count > count:
            # rslog(val, "$val")
            return 0

        val = int(val)
        if val == 0:
            total_sum += self.blink_one(1, count, cur_count + 1)
        else:
            arr = list(map(int, str(val).strip()))
            if len(arr) % 2 == 0:
                mid = len(arr) // 2
                val1 = int("".join(map(str, arr[:mid])))
                val2 = int("".join(map(str, arr[mid:])))
                total_sum += 1
                total_sum += self.blink_one(val1, count, cur_count + 1)
                total_sum += self.blink_one(val2, count, cur_count + 1)
            else:
                total_sum += self.blink_one(val * 2024, count, cur_count + 1)

        if val not in self.heads:
            self.heads[val] = {}
        self.heads[val][cur_count] = total_sum

        return total_sum

    def print_array(self, mat, comment="Matrix:"):
        rslog(comment, "Comment")
        print(" ".join(map(str, mat)))

    def read_array(self, filename):
        with open(filename, "r") as f:
            content = f.read().strip()
        mat = map(int, content.split(" "))
        self.mat.extend(mat)

    def run(self):
        add_timer("TOTAL")

        self.code()

        show_timers()

if __name__ == "__main__":
    program = Def()
    program.run()
