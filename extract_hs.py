import json
import re

def strip_tags(html):
    # a basic regex to strip HTML tags
    text = re.sub('<style.*?</style>', '', html, flags=re.DOTALL)
    text = re.sub('<table class=\"infobox.*?</table>', '', text, flags=re.DOTALL)
    text = re.sub('<[^<]+>', ' ', text)
    text = re.sub('\s+', ' ', text)
    return text.strip()

try:
    d = json.load(open('hs_temp.json', encoding='utf-8'))
    with open('hs_text.txt', 'w', encoding='utf-8') as f:
        f.write('=== EXTRACT ===\n')
        f.write(strip_tags(d['extract_html']))
        f.write('\n\n=== SECTIONS ===\n')
        for k, v in d.get('sections', {}).items():
            f.write(f'--- {k} ---\n')
            f.write(strip_tags(v))
            f.write('\n')
except Exception as e:
    print(e)
