import heapq
from collections import Counter

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
    predecessors = {}  # To track the path

    while queue:
        cost, pos, direction = heapq.heappop(queue)

        if pos == end:
            # Reconstruct the path
            path = []
            while pos != start:
                path.append(pos)
                if (pos, direction) not in predecessors:
                    raise ValueError(f"Missing predecessor for position {pos} with direction {direction}")
                pos, direction = predecessors[(pos, direction)]
            path.append(start)
            return cost, path[::-1]

        for neighbor, new_direction in get_neighbors(pos, direction, grid):
            turn_cost = 1000 if direction != -1 and direction != new_direction else 0
            new_cost = cost + 1 + turn_cost

            if (neighbor, new_direction) not in costs or new_cost < costs[(neighbor, new_direction)]:
                costs[(neighbor, new_direction)] = new_cost
                predecessors[(neighbor, new_direction)] = (pos, direction)  # Record the predecessor
                heapq.heappush(queue, (new_cost, neighbor, new_direction))

    return float('inf'), []  # If no path is found

def find_all_paths(grid, start, end, base_cost):
    all_paths = []
    original_grid = [row[:] for row in grid]

    _, primary_path = dijkstra(grid, start, end)
    if not primary_path:
        return []

    for block_pos in primary_path:
        grid = [row[:] for row in original_grid]  # Reset grid
        grid[block_pos[0]][block_pos[1]] = '#'  # Block the current position

        cost, path = dijkstra(grid, start, end)
        if cost == base_cost and path:
            all_paths.append(path)

    return all_paths

def collect_unique_points(paths):
    points = set()
    for path in paths:
        points.update(path)
    return points, len(points)

if __name__ == "__main__":
    file_path = "data/16-1.inp"
    grid = read_input(file_path)
    start, end = find_positions(grid)
    base_cost, primary_path = dijkstra(grid, start, end)

    if primary_path:
        print("Primary Path Cost:", base_cost)
        print("Primary Path:", primary_path)

        all_paths = find_all_paths(grid, start, end, base_cost)
        print("All Paths with Same Cost:")
        for path in all_paths:
            print(path)

        unique_points, count = collect_unique_points(all_paths)
        print("Unique Points:", unique_points)
        print("Total Unique Points:", count)
    else:
        print("No path found.")
