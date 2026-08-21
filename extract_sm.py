import json

with open('c:/projects/apache/school1/src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

for el in data:
    if el.get('name') == 'Samarium':
        with open('c:/projects/apache/school1/samarium_temp.json', 'w', encoding='utf-8') as out:
            json.dump(el, out, indent=2)
        print("Samarium saved.")
        break
