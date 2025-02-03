def parse_input(file_path):
    """
    Читает данные из файла и преобразует их в структуру для обработки.
    """
    machines = []
    with open(file_path, "r") as file:
        raw_data = file.read().strip()
    for section in raw_data.split("\n\n"):
        lines = section.splitlines()
        button_a = tuple(map(int, lines[0].split(":")[1].replace("X+", "").replace("Y+", "").split(", ")))
        button_b = tuple(map(int, lines[1].split(":")[1].replace("X+", "").replace("Y+", "").split(", ")))
        prize = tuple(map(int, lines[2].split(":")[1].replace("X=", "").replace("Y=", "").split(", ")))
        prize2 = (prize[0] + 10000000000000, prize[1] + 10000000000000)
        machines.append((button_a, button_b, prize2))
    return machines

def solve_system_gauss_exact(a_x, a_y, b_x, b_y, x_p, y_p):
    """
    Решает систему линейных уравнений методом Гаусса для целых чисел.
    Возвращает минимальную стоимость или None, если решения нет.
    """
    # Определяем детерминант
    det = a_x * b_y - b_x * a_y
    if det == 0:
        return None  # Система вырожденная, решений нет или их бесконечно много.

    # Используем обратную матрицу для вычисления базового решения
    n_a = (x_p * b_y - y_p * b_x) / det
    n_b = (y_p * a_x - x_p * a_y) / det

    # Проверяем, что n_a и n_b целые
    if n_a.is_integer() and n_b.is_integer():
        n_a, n_b = int(n_a), int(n_b)
        if n_a >= 0 and n_b >= 0:  # Проверяем неотрицательность
            cost = 3 * n_a + 1 * n_b
            return cost, n_a, n_b
    return None

def solve_puzzle(file_path):
    """
    Решает задачу для всех машин через метод Гаусса.
    """
    machines = parse_input(file_path)
    total_cost = 0
    prizes_won = 0

    for button_a, button_b, prize in machines:
        result = solve_system_gauss_exact(*button_a, *button_b, *prize)
        if result is not None:
            cost, n_a, n_b = result
            prizes_won += 1
            total_cost += cost

    return prizes_won, total_cost

if __name__ == "__main__":
    # Путь к файлу с входными данными
    file_path = "data/13-1.inp"

    # Решение задачи
    prizes_won, total_cost = solve_puzzle(file_path)
    
    # Вывод результатов
    print(f"Prizes won: {prizes_won}, Total cost: {total_cost}")
