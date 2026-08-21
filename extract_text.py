import json
from html.parser import HTMLParser

class MLStripper(HTMLParser):
    def __init__(self):
        super().__init__()
        self.reset()
        self.strict = False
        self.convert_charrefs = True
        self.text = []
    def handle_data(self, d):
        self.text.append(d)
    def get_data(self):
        return ''.join(self.text)

def strip_tags(html):
    s = MLStripper()
    s.feed(html)
    return s.get_data()

d = json.load(open('src/ptable/data/master_elements.json', encoding='utf-8'))
te = d['Te']
with open('tellurium_text.txt', 'w', encoding='utf-8') as f:
    f.write('---EXTRACT---\n')
    f.write(strip_tags(te.get('extract_html', '')) + '\n')
    for k, v in te.get('sections', {}).items():
        f.write(f'---{k}---\n')
        f.write(strip_tags(v) + '\n')
