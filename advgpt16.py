import heapq

def read_input(file_path):
    with open(file_path, 'r') as f:
        grid = [list(line.strip()) for line in f]
    return grid

def find_positions(grid):
    start, end = None, None
    for i, row in enumerate(grid):
        for j, cell in enumerate(row):
            if cell == 'S':
                start = (i, j)
            elif cell == 'E':
                end = (i, j)
    return start, end

def get_neighbors(pos, direction, grid):
    directions = [(0, 1), (1, 0), (0, -1), (-1, 0)]  # Right, Down, Left, Up
    neighbors = []
    rows, cols = len(grid), len(grid[0])
    for i, (dx, dy) in enumerate(directions):
        new_direction = i
        new_pos = (pos[0] + dx, pos[1] + dy)
        if 0 <= new_pos[0] < rows and 0 <= new_pos[1] < cols and grid[new_pos[0]][new_pos[1]] != '#':
            neighbors.append((new_pos, new_direction))
    return neighbors

def dijkstra(grid, start, end):
    directions = [(0, 1), (1, 0), (0, -1), (-1, 0)]  # Right, Down, Left, Up
    queue = []
    heapq.heappush(queue, (0, start, -1))  # Cost, position, direction
    costs = {(start, -1): 0}

    while queue:
        cost, pos, direction = heapq.heappop(queue)

        if pos == end:
            return cost

        for neighbor, new_direction in get_neighbors(pos, direction, grid):
            turn_cost = 1000 if direction != -1 and direction != new_direction else 0
            new_cost = cost + 1 + turn_cost

            if (neighbor, new_direction) not in costs or new_cost < costs[(neighbor, new_direction)]:
                costs[(neighbor, new_direction)] = new_cost
                heapq.heappush(queue, (new_cost, neighbor, new_direction))

    return float('inf')  # If no path is found

if __name__ == "__main__":
    file_path = "data/16-1.inp"
    grid = read_input(file_path)
    start, end = find_positions(grid)
    result = dijkstra(grid, start, end)
    print(result)