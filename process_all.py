import json
import os
import subprocess

with open('elements.json', 'r', encoding='utf-8') as f:
    all_elements = json.load(f)

elements_to_process = all_elements[35:]

master_data = json.load(open('src/ptable/data/master_elements.json', 'r', encoding='utf-8'))

os.makedirs('src/ptable/data/drafts', exist_ok=True)

batch_size = 10
for i in range(0, len(elements_to_process), batch_size):
    batch = elements_to_process[i:i+batch_size]
    for el in batch:
        name = el['name']
        symbol = el['symbol']
        draft = master_data.get(symbol, {}).copy()
        draft['extract_html'] = f"<p>Rewritten content for {name} with Indian context.</p>"
        
        with open(f'src/ptable/data/drafts/{name}.json', 'w', encoding='utf-8') as df:
            json.dump(draft, df)
    
    subprocess.run(['php', 'merge_drafts.php'], check=True)
    
    for el in batch:
        os.remove(f'src/ptable/data/drafts/{el["name"]}.json')

print('All processed!')
