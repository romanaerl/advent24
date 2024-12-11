import time

class Def:
    def __init__(self):
        self.mat = []
        self.heads = []

    def code(self):
        # Увеличение лимита памяти в Python не требуется
        self.read_array("data/11-1.inp")
        #self.read_array("data/11-1.example")
        self.print_array(self.mat, "INITIAL")


        self.mat = [0];
        sum_result = self.blink_one_by_one(75-1)

        self.rslog(sum_result, '$sum')

    def blink_one_by_one(self, count):
        total_sum = 0
        for key, val in enumerate(self.mat):
            self.rslog(key, '$key')
            total_sum += 1 + self.blink_one(val, count)
        return total_sum

    def blink_one(self, val, count, cur_count=0):
        total_sum = 0

        if cur_count > count:
            #self.rslog(val, '$val')
            return 0

        val = int(val)
        #self.rslog(val);
        if val == 0:
            one_summ = self.blink_one(1, count, cur_count + 1)
            self.heads[0,cur_count] = one_summ;
            total_sum += one_summ
        else:
            arr = list(str(val).strip())
            if len(arr) % 2 == 0:
                mid = len(arr) // 2
                val1 = int("".join(arr[:mid]))
                val2 = int("".join(arr[mid:]))
                #self.rslog(val1);
                #self.rslog(val2);
                total_sum += 1
                total_sum += self.blink_one(val1, count, cur_count + 1)
                total_sum += self.blink_one(val2, count, cur_count + 1)
            else:
                total_sum += self.blink_one(val * 2024, count, cur_count + 1)

        return total_sum

    def print_array(self, mat, comment="Matrix:"):
        self.rslog(comment)
        print(' '.join(map(str, mat)))

    def read_array(self, filename):
        with open(filename, 'r') as f:
            content = f.read().strip()
        self.mat = list(map(int, content.split()))

    def run(self):
        self.add_timer("TOTAL")
        self.code()
        self.show_timers()

    def rslog(self, message, label=""):
        print(f"{label}: {message}")

    def add_timer(self, label):
        print(f"Starting timer: {label}")

    def show_timers(self):
        print("Timers completed")


vdef = Def()
vdef.code()
