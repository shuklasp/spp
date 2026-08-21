import json
import re
from bs4 import BeautifulSoup

with open('c:/projects/apache/school1/samarium_temp.json', 'r', encoding='utf-8') as f:
    d = json.load(f)

with open('c:/projects/apache/school1/samarium_apps.txt', 'w', encoding='utf-8') as out:
    html = d['sections'].get('Applications', '')
    soup = BeautifulSoup(html, 'html.parser')
    out.write(soup.get_text(separator=' ', strip=True))
    
