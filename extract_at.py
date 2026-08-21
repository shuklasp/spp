import json
import re
import sys
sys.stdout.reconfigure(encoding='utf-8')

def clean_html(raw_html):
    cleanr = re.compile('<.*?>')
    return re.sub(cleanr, '', raw_html)

with open(r'c:\projects\apache\school1\src\ptable\data\master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

at = data['At']
print('--- EXTRACT ---')
print(clean_html(at.get('extract_html', '')))
print('\n--- SECTIONS ---')
sections = at.get('sections', [])
print(f"Sections type: {type(sections)}")
if isinstance(sections, list):
    for sec in sections:
        if isinstance(sec, dict):
            print(f"\n[{sec.get('title', 'NO_TITLE')}]")
            print(sec.get('text', 'NO_TEXT') or sec.get('content', 'NO_CONTENT'))
        else:
            print("\n[SECTION]")
            print(str(sec))
elif isinstance(sections, dict):
    for k, v in sections.items():
        print(f"\n[{k}]")
        print(str(v))
