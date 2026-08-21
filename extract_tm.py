import json
with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)
tm = data['Tm']
with open('tm_dump.json', 'w', encoding='utf-8') as out:
    json.dump({'extract_html': tm['extract_html'], 'sections': tm.get('sections', [])}, out, indent=2)
