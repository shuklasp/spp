import json
import re

with open('c:/projects/apache/school1/src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# If data is list, find Hf. If dict, get Hf.
if isinstance(data, list):
    hf = next(e for e in data if e.get('symbol', e.get('name')) == 'Hf' or e.get('name') == 'Hafnium')
else:
    # it's a dict
    hf = data.get('Hf')
    if isinstance(hf, str):
        hf = json.loads(hf)

def clean_html(raw_html):
    cleanr = re.compile('<.*?>')
    cleantext = re.sub(cleanr, '', raw_html)
    return cleantext

with open('c:/projects/apache/school1/src/ptable/data/hf_clean.txt', 'w', encoding='utf-8') as out:
    out.write("EXTRACT:\n")
    if 'extract_html' in hf:
        out.write(clean_html(hf['extract_html']) + "\n\n")
    else:
        out.write("No extract_html\n\n")
        
    out.write("SECTIONS:\n")
    for s in hf.get('sections', []):
        out.write("TITLE: " + s.get('title', '') + "\n")
        out.write(clean_html(s.get('content', '')) + "\n\n")

print("Done parsing Hafnium.")
