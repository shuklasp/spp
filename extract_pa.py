import json
import re

with open('c:/projects/apache/school1/pa_data.json', 'r') as f:
    data = json.load(f)['Pa']

def clean(text):
    if not text: return ""
    return re.sub(r'<[^>]+>', '', text)

with open('c:/projects/apache/school1/pa_text.txt', 'w', encoding='utf-8') as f:
    f.write('EXTRACT:\n')
    f.write(clean(data.get('extract_html', '')))
    f.write('\n\nSECTIONS:\n')
    sections = data.get('sections', {})
    for title, html in sections.items():
        f.write(title + ':\n')
        f.write(clean(html))
        f.write('\n\n')
