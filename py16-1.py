import time

class Def:
    UP = '^'
    RIGHT = '>'
    LEFT = '<'
    DOWN = 'v'

    DIRS = {
        UP: [0, -1, UP],
        RIGHT: [1, 0, RIGHT],
        LEFT: [-1, 0, LEFT],
        DOWN: [0, 1, DOWN],
    }

    OPPOSITE_DIRS = {
        UP: DOWN,
        DOWN: UP,
        LEFT: RIGHT,
        RIGHT: LEFT,
    }

    def __init__(self):
        self.mat = []
        self.minimal_summ = None
        self.start_x = None
        self.start_y = None
        self.end_x = None
        self.end_y = None
        self.visited = {}
        self.operations_cnt = 0
        self.last_time_printed = 0
        self.path = []
        self.diff_mat = []
        self.keypoints = {}
        self.dead_ends = {}
        self.queue = []
        self.last_summ = None

    def code(self):
        for _ in range(5):
            print("============================BEGIN========================")

        # Adjust memory limit, if needed
        self.read_array("data/16-1.inp")
        print("INITIAL")
        self.print_mat([])

        self.operations_cnt = 1
        while self.operations_cnt:
            self.operations_cnt = 0
            self.close_dead_ends()
            print("\n\n\n\n")
            self.print_mat([], False)

        print("AFTER DEADENDS MARK")
        self.print_mat([], True)

        self.mark_keypoints()
        print("AFTER markdown")
        self.print_mat([], True)

        self.find_and_weight_links()
        print(f"AFTER WEIGHT, count(keypoints): {len(self.keypoints)}")
        self.print_mat([], True)

        self.queue.append([self.start_x, self.start_y, self.LEFT, 0, {}])
        while self.queue:
            self.operations_cnt = 1
            self.process_record_from_queue()
            if len(self.queue) % 10000 == 0:
                print(f"Minimal Sum: {self.minimal_summ}, Queue Length: {len(self.queue)}, Last Sum: {self.last_summ}")

        print(f"OPTIMAL ({self.operations_cnt})")
        self.print_mat(self.path, True)

    def process_record_from_queue(self):
        rec = self.queue.pop(0)
        x, y, direction, summ, visited = rec

        if (x, y) not in self.keypoints:
            raise ValueError(f"We should not be here {x}, {y}")

        visited[(y, x)] = 1

        if self.end_x == x and self.end_y == y:
            self.add_result(summ, visited)
            return

        links = self.keypoints[y][x]['links']

        for link_dir, link in links.items():
            if not link:
                continue
            link_x, link_y, link_come_dir, link_sum = link

            if link_dir == self.get_oppose_dir(direction):
                continue

            if (link_y, link_x) in visited:
                continue

            sum_rec = summ + self.get_turn_price(direction, link_dir) + link_sum
            record = [link_x, link_y, link_come_dir, sum_rec, visited.copy()]
            self.last_summ = sum_rec
            self.queue.append(record)

    def get_oppose_dir(self, direction):
        return self.OPPOSITE_DIRS[direction]

    def close_dead_ends(self):
        for y, row in enumerate(self.mat):
            for x, symb in enumerate(row):
                if symb == ".":
                    coords = self.get_square_around(x, y)
                    exits = sum(1 for coord in coords if self.mat[coord[1]][coord[0]] in [".", "S", "E"])
                    if exits == 1:
                        self.mat[y][x] = '#'
                        self.operations_cnt += 1

    def mark_keypoints(self):
        for y, row in enumerate(self.mat):
            for x, symb in enumerate(row):
                if symb in [".", "E", "S"]:
                    coords = self.get_square_around(x, y)
                    exits = 0
                    keypoint = {}
                    for coord in coords:
                        cx, cy, cd = coord
                        if self.mat[cy][cx] in [".", "S", "E"]:
                            keypoint.setdefault("links", {})[cd] = None
                            exits += 1
                    if exits > 2 or symb in ["E", "S"]:
                        self.keypoints.setdefault(y, {})[x] = keypoint

    def find_and_weight_links(self):
        for y, row in self.keypoints.items():
            for x, keypoint in row.items():
                for dir, link in keypoint['links'].items():
                    if link is not None:
                        continue
                    next_keypoint = self.walk_to_next_keypoint(x, y, dir, 0)
                    keypoint['links'][dir] = next_keypoint

    def walk_to_next_keypoint(self, x, y, direction, summ):
        dx, dy, _ = self.DIRS[direction]
        new_x = x + dx
        new_y = y + dy

        summ += 1

        if (new_y in self.keypoints and new_x in self.keypoints[new_y]):
            return [new_x, new_y, direction, summ]
        else:
            coords = self.get_square_around(new_x, new_y)
            coords = self.filter_coords(coords, x, y, [])
            coord = coords[0] if coords else None
            if not coord or self.mat[y][x] not in [".", "E", "S"]:
                raise ValueError("WE SHOULD NOT BE HERE - not the dot, while walking")

            summ += self.get_turn_price(direction, coord[2])
            return self.walk_to_next_keypoint(new_x, new_y, coord[2], summ)

    def add_result(self, summ, visited):
        if self.minimal_summ is None or self.minimal_summ > summ:
            self.minimal_summ = summ
            self.operations_cnt += 1
            self.path = visited
            return True
        return False

    def get_square_around(self, x, y):
        return [[x + dx, y + dy, dir] for dir, (dx, dy, _) in self.DIRS.items()]

    def filter_coords(self, coords, exclude_x, exclude_y, visited):
        filtered = []
        for coord in coords:
            cx, cy, _ = coord
            if cx == exclude_x and cy == exclude_y:
                continue
            if (cy, cx) in visited:
                continue
            if self.mat[cy][cx] in [".", "E", "S"]:
                filtered.append(coord)
        return filtered

    def get_turn_price(self, dir1, dir2):
        if dir1 is None:
            return 0
        if dir1 == dir2:
            return 0
        if {dir1, dir2} in [{self.UP, self.DOWN}, {self.LEFT, self.RIGHT}]:
            return 2000
        return 1000

    def print_mat(self, visited, ignore_time=False):
        if not ignore_time and time.time() - self.last_time_printed < 2:
            return False

        self.last_time_printed = time.time()
        for y, row in enumerate(self.mat):
            line = ""
            for x, symb in enumerate(row):
                if (y, x) in visited:
                    symb = "8"
                elif symb == ".":
                    symb = " "
                line += symb
            print(line)

    def read_array(self, filename):
        with open(filename) as file:
            for y, line in enumerate(file):
                row = list(line.strip())
                self.mat.append(row)
                for x, symb in enumerate(row):
                    if symb == 'S':
                        self.start_x, self.start_y = x, y
                    elif symb == 'E':
                        self.end_x, self.end_y = x, y


path_finder = Def()
path_finder.code()
