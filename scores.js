let br = document.querySelectorAll('br');
br.values().forEach((it) => it.remove());//it.replaceWith(document.createElement('section')));

function createGlobalTable(text) {
    // Регулярное выражение для парсинга строк
    const regex = /#(?<num>\d+)\s+\((?<score>\d+)\)\s+=====>\s+(?<name>[\w\s_@]+?)\s+\((?<gold>\d+)\*\)/g;
    
    // Создаем таблицу
    let table = `
    <h3>Global Rating</h3>
    
    <table>
        <thead>
            <tr>
                <th class="num">#</th>
                <th>Name</th>
                <th class="score">Score</th>
                <th class="gold">Gold *</th>
            </tr>
        </thead>
        <tbody>
    `;
    
    // Находим все совпадения
    const matches = text.matchAll(regex);
    
    // Добавляем каждую строку в таблицу
    for (const match of matches) {
        const {num, score, name, gold} = match.groups;
        table += `
            <tr>
                <td class="num">${num}</td>
                <td>${name.trim()}</td>
                <td class="score">${score}</td>
                <td class="gold">${gold}</td>
            </tr>
        `;
    }
    
    table += `
        </tbody>
    </table>
    `;
    
    return table;
}

const lastUpdated = document.querySelector('body > i')
const lastUpdatedHTML = lastUpdated?.outerHTML;
lastUpdated.remove();

const prevEl = document.querySelector('b').previousSibling;
const b = document.querySelectorAll('b');
let rows = '';
b.forEach(el => {
    rows += el.textContent;
    el.remove();
});
const table = createGlobalTable(rows);
prevEl.outerHTML = prevEl.outerHTML + table + lastUpdatedHTML;

function createTable(text) {
    // Регулярное выражение для парсинга строк
//    const regex = /#(?<num>\d+)\s+(?<name>[\w\s@]+?)\s+\+(?<diff>\d+)\s+\(t2 solved (?<t2_solved_time>\d{2}:\d{2}:\d{2}) after t1 on (?<t1_solved_time>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\)/g;
    const regex = /#(?<num>\d+)\s+(?<name>[\p{L}\p{N}\s@]+?)\s+\+(?<diff>\d+)\s+\(t2 solved (?<t2_solved_time>\d{2}:\d{2}:\d{2}) after t1 on (?<t1_solved_time>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\)/gu;

    // Создаем таблицу
    let table = `
    <table border="1">
        <thead>
            <tr>
                <th class="num">#</th>
                <th>Name</th>
                <th class="score">Score</th>
                <th class="time">time span between t1 and t2</th>
                <th class="time">t2 timestamp</th>
            </tr>
        </thead>
        <tbody>
    `;
    
    // Находим все совпадения
    const matches = text.matchAll(regex);
    
    // Добавляем каждую строку в таблицу
    for (const match of matches) {
        const {num, name, diff, t2_solved_time, t1_solved_time} = match.groups;
        table += `
            <tr>
                <td class="num">${num}</td>
                <td>${name.trim()}</td>
                <td class="score">+${diff}</td>
                <td class="time">${t2_solved_time}</td>
                <td class="time">${t1_solved_time}</td>
            </tr>
        `;
    }
    
    table += `
        </tbody>
    </table>
    `;
    
    return table;
}

// Пример использования:
// const inputText = `<h4>Day 10:</h4>#1 yabalaban +18  (t2 solved 00:00:54 after t1 on 2024-12-10 06:09:42)#2 Alexxz +17  (t2 solved 00:01:31 after t1 on 2024-12-10 13:51:25)#3 Aleksandr Klestov +16  (t2 solved 00:02:37 after t1 on 2024-12-10 09:46:34)#4 @_klin__ +15  (t2 solved 00:02:44 after t1 on 2024-12-10 08:58:34)#5 Stanislav Yaglo +14  (t2 solved 00:02:53 after t1 on 2024-12-10 09:14:17)#6 Roman Simonov +13  (t2 solved 00:03:08 after t1 on 2024-12-10 13:04:41)#7 Valeriy Kobzar +12  (t2 solved 00:03:19 after t1 on 2024-12-10 09:57:37)#8 Vladimir Kazanov +11  (t2 solved 00:06:43 after t1 on 2024-12-10 07:32:55)#9 anight +10  (t2 solved 00:09:08 after t1 on 2024-12-10 05:26:43)`;

// document.body.innerHTML = createTable(inputText);

const h4 = document.querySelectorAll('h4');
h4.forEach(h => {
    let guard = 100;
    let rows = '';
    let tmp = h;
    while(guard > 0) {
        const el = tmp.nextSibling; 
        if (el?.nodeName !== '#text') {
            break;
        }
        rows += el.textContent;
        el.remove();
        guard--;
        
    }
    const table = createTable(rows);
    // console.log(h, table);
    h.outerHTML = h.outerHTML + table;
});


console.log('%cRestyling complete!', 'color:blue');

