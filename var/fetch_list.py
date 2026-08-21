import urllib.request
import re
import json
import os

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT_FILE = os.path.join(BASE_DIR, 'src', 'ptable', 'data', 'compound_targets.json')

targets = set()

urls = [
    'https://en.wikipedia.org/wiki/List_of_inorganic_compounds',
    'https://en.wikipedia.org/wiki/List_of_organic_compounds'
]

for url in urls:
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
    try:
        html = urllib.request.urlopen(req).read().decode('utf-8')
        # Find list items that look like links to compounds
        # Usually they are inside <ul><li><a href="/wiki/Compound_Name" title="Compound Name">
        matches = re.findall(r'<li><a href="/wiki/[^"]+" title="([^"]+)">', html)
        for m in matches:
            if not ":" in m and not "List" in m:
                targets.add(m)
        print(f"Fetched from {url}, total unique so far: {len(targets)}")
    except Exception as e:
        print(f"Error fetching {url}: {e}")

with open(OUT_FILE, 'w', encoding='utf-8') as f:
    json.dump(list(targets), f, indent=4)

print(f"Saved {len(targets)} targets to {OUT_FILE}")
