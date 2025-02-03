import os
from PIL import Image, ImageCms

def create_gif_from_jpegs(folder_path, output_gif_path, duration=200):
    """
    Создает GIF из всех JPEG-файлов в указанной папке, уменьшая их размеры в три раза.

    :param folder_path: Путь к папке с JPEG-файлами.
    :param output_gif_path: Путь для сохранения итогового GIF-файла.
    :param duration: Продолжительность показа каждого кадра в миллисекундах (по умолчанию 200 мс).
    """
    # Список файлов в папке
    file_list = [f for f in os.listdir(folder_path) if f.lower().endswith('.jpeg') or f.lower().endswith('.jpg') or f.lower().endswith('.png')]

    if not file_list:
        print("В указанной папке нет JPEG-файлов.")
        return

    # Сортируем файлы для последовательности
    file_list.sort()

    # Загружаем и уменьшаем изображения
    images = []
    for file in file_list:
        img = Image.open(os.path.join(folder_path, file))

        # Проверка и обработка цветового профиля
        try:
            if "icc_profile" in img.info:
                icc_profile = img.info["icc_profile"]
                img = ImageCms.profileToProfile(img, icc_profile, ImageCms.createProfile("sRGB"))
            else:
                # Принудительное задание профиля sRGB, если профиль отсутствует
                img = ImageCms.profileToProfile(img, ImageCms.createProfile("sRGB"), ImageCms.createProfile("sRGB"))
        except Exception as e:
            print(f"Ошибка при обработке цветового профиля для {file}: {e}")

        resized_img = img.resize((img.width // 2, img.height // 2), Image.LANCZOS)
        if resized_img.mode != 'RGB':
            resized_img = resized_img.convert('RGB')
#         images.append(resized_img)
        images.append(img)

    # Сохраняем изображения в GIF
    images[0].save(
        output_gif_path,
        save_all=True,
        append_images=images[1:],
        duration=duration,
        loop=1
    )

    print(f"GIF сохранен по пути: {output_gif_path}")

# Пример использования
if __name__ == "__main__":
    folder = "/Users/rsimonov/Downloads/PhotosForVideos/Swap3"
    output_path = folder + "/out.gif"
    create_gif_from_jpegs(folder, output_path, 100)
