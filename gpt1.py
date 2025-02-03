# Читаем данные из файла
def read_data(file_path):
    left_list = []
    right_list = []

    with open(file_path, "r") as file:
        for line in file:
            left, right = map(int, line.split())
            left_list.append(left)
            right_list.append(right)

    return left_list, right_list

# Основная функция для подсчета similarity score между двумя списками
def calculate_similarity_score(file_path):
    from collections import Counter

    left_list, right_list = read_data(file_path)

    # Подсчитываем количество вхождений каждого элемента в правом списке
    right_count = Counter(right_list)

    # Считаем similarity score
    similarity_score = sum(num * right_count[num] for num in left_list)

    return similarity_score

# Указываем путь к файлу
file_path = "data/1.inp"

# Вычисляем и выводим результат
similarity_score = calculate_similarity_score(file_path)
print("Similarity score between the lists:", similarity_score)
