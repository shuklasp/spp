import json
import sys
sys.stdout.reconfigure(encoding='utf-8')

with open(r'c:\projects\apache\school1\src\ptable\data\master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

at = data['At']
sections = at.get('sections', [])
for i, sec in enumerate(sections):
    print(f"[{i}] {sec.get('title', 'No Title')}")
