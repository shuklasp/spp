import json

with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)
    sg = data['Sg']

with open('sg_content.txt', 'w', encoding='utf-8') as f:
    f.write('---EXTRACT---\n')
    f.write(sg.get('extract_html', '') + '\n')
    f.write('---SECTIONS---\n')
    f.write(json.dumps(sg.get('sections', []), indent=2))
