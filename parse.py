import json
from html.parser import HTMLParser

class MLStripper(HTMLParser):
    def __init__(self):
        super().__init__()
        self.reset()
        self.strict = False
        self.convert_charrefs= True
        self.text = []
    def handle_data(self, d):
        self.text.append(d)
    def get_data(self):
        return ''.join(self.text)

def strip_tags(html):
    s = MLStripper()
    try:
        s.feed(html)
        return s.get_data()
    except Exception:
        return html # fallback

with open(r'c:\projects\apache\school1\src\ptable\data\scratch_cn.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

with open(r'c:\projects\apache\school1\extracted_text.txt', 'w', encoding='utf-8') as out:
    out.write('---EXTRACT---\n' + strip_tags(data.get('extract_html','')) + '\n')
    for k, v in data.get('sections', {}).items():
        out.write('\n---' + k + '---\n' + strip_tags(v) + '\n')
