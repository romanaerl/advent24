def simulate_warehouse(warehouse, moves):
    # Parse the initial state into a 2D grid
    grid = [list(row) for row in warehouse.strip().split("\n")]
    height = len(grid)
    width = len(grid[0])

    # Find the initial position of the robot
    robot_pos = None
    for r in range(height):
        for c in range(width):
            if grid[r][c] == "@":
                robot_pos = (r, c)
                break
        if robot_pos:
            break

    def display_grid():
        for row in grid:
            print("".join(row))
        print()

    def move_robot(robot_pos, direction):
        dr, dc = 0, 0
        if direction == "^":
            dr, dc = -1, 0
        elif direction == "v":
            dr, dc = 1, 0
        elif direction == "<":
            dr, dc = 0, -1
        elif direction == ">":
            dr, dc = 0, 1

        nr, nc = robot_pos[0] + dr, robot_pos[1] + dc

        # Check bounds
        if nr < 0 or nr >= height or nc < 0 or nc >= width:
            return robot_pos  # Robot doesn't move

        # Check if moving into a wall
        if grid[nr][nc] == "#":
            return robot_pos  # Robot doesn't move

        # Check if moving into a box
        if grid[nr][nc] == "O":
            box_r, box_c = nr + dr, nc + dc
            # Check if the box can be pushed
            if 0 <= box_r < height and 0 <= box_c < width and grid[box_r][box_c] == ".":
                grid[box_r][box_c] = "O"  # Move the box
                grid[nr][nc] = "@"  # Move the robot
                grid[robot_pos[0]][robot_pos[1]] = "."  # Leave an empty space
                return (nr, nc)
            else:
                return robot_pos  # Robot doesn't move

        # Otherwise, move the robot
        grid[nr][nc] = "@"
        grid[robot_pos[0]][robot_pos[1]] = "."
        return (nr, nc)

    # Display initial state
    print("Initial state:")
    display_grid()

    # Simulate all moves
    for move in moves:
        print(f"Move: {move}")
        robot_pos = move_robot(robot_pos, move)
        display_grid()

    # Display final state
    print("Final state:")
    display_grid()

    # Calculate the sum of all GPS coordinates of the boxes
    gps_sum = 0
    for r in range(height):
        for c in range(width):
            if grid[r][c] == "O":
                gps_sum += 100 * r + c

    return gps_sum

# Read input from file
def read_input(file_path):
    with open(file_path, 'r') as f:
        lines = f.readlines()

    # Separate warehouse and moves
    warehouse_lines = []
    moves = ""
    reading_moves = False

    for line in lines:
        line = line.strip()
        if not line:
            reading_moves = True
            continue
        if reading_moves:
            moves += line
        else:
            warehouse_lines.append(line)

    warehouse = "\n".join(warehouse_lines)
    return warehouse, moves

# File input
file_path = "data/15-1.example"
warehouse, moves = read_input(file_path)

# Run simulation
result = simulate_warehouse(warehouse, moves)
print("Sum of GPS coordinates:", result)