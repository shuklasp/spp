import json
import re

with open('U_temp.json', 'r', encoding='utf-8') as f:
    d = json.load(f)

with open('U_text.txt', 'w', encoding='utf-8') as out:
    out.write('--- EXTRACT HTML ---\n')
    html = d.get('extract_html', '')
    text = re.sub('<[^<]+>', '', html)
    out.write(text + '\n')
    
    out.write('--- SECTIONS ---\n')
    sections = d.get('sections', {})
    for title, content in sections.items():
        out.write(f'\nSECTION: {title}\n')
        out.write(re.sub('<[^<]+>', '', content) + '\n')
