import heapq

def parse_map(matrix):
    start = end = None
    grid = []
    for y, row in enumerate(matrix):
        grid.append([])
        for x, cell in enumerate(row):
            if cell == 'S':
                start = (x, y)
                grid[y].append('.')
            elif cell == 'E':
                end = (x, y)
                grid[y].append('.')
            else:
                grid[y].append(cell)
    return grid, start, end

def find_path(matrix):
    grid, start, end = parse_map(matrix)
    directions = [(0, 1), (1, 0), (0, -1), (-1, 0)]
    queue = []
    heapq.heappush(queue, (0, start, None))  # (cost, position, direction)
    visited = set()

    while queue:
        cost, current, direction = heapq.heappop(queue)
        if current in visited:
            continue
        visited.add(current)

        if current == end:
            return cost

        x, y = current
        for i, (dx, dy) in enumerate(directions):
            nx, ny = x + dx, y + dy
            if 0 <= ny < len(grid) and 0 <= nx < len(grid[0]) and grid[ny][nx] == '.':
                new_cost = cost + 1
                if direction is not None and direction != i:
                    new_cost += 1000
                heapq.heappush(queue, (new_cost, (nx, ny), i))
    return -1

# Матрица в формате строки
filename = "data/16-1.inp"
with open(filename, 'r') as file:
    lines = file.readlines()

matrix = [list(row) for row in lines]
result = find_path(matrix)
print(f"Минимальная стоимость пути: {result}")
