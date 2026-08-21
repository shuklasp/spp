import json

with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

tantalum = next((el for el in data if el.get('name') == 'Tantalum'), None)

if tantalum:
    with open('Tantalum_temp.json', 'w', encoding='utf-8') as f:
        json.dump(tantalum, f, indent=4)
    print("Saved Tantalum to Tantalum_temp.json")
else:
    print("Tantalum not found")
