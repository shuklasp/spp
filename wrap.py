import json
import textwrap
import re

with open(r'c:\projects\apache\school1\src\ptable\data\master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)
cm = data['Cm']

def clean(html):
    if not isinstance(html, str): return str(html)
    text = re.sub(r'<[^>]+>', ' ', html)
    text = re.sub(r'\s+', ' ', text)
    return text.strip()

with open(r'C:\Users\Satya Prakash Shukla\.gemini\antigravity\brain\fc7f1d1b-afdd-43d9-93f3-47e70d7f0e3f\scratch\cm_wrap.txt', 'w', encoding='utf-8') as f:
    f.write('=== EXTRACT ===\n')
    f.write(textwrap.fill(clean(cm.get('extract_html', '')), width=80) + '\n\n')
    
    sections = cm.get('sections', [])
    for sec in sections:
        f.write(f"=== SECTION: {sec.get('title', '')} ===\n")
        f.write(textwrap.fill(clean(sec.get('content', '')), width=80) + '\n\n')
