import json
import re

def clean_html(html):
    # extremely simple strip html
    text = re.sub(r'<style.*?</style>', '', html, flags=re.DOTALL)
    text = re.sub(r'<[^>]+>', ' ', text)
    text = re.sub(r'\s+', ' ', text).strip()
    return text

with open('c:/projects/apache/school1/samarium_temp.json', 'r', encoding='utf-8') as f:
    d = json.load(f)

with open('c:/projects/apache/school1/samarium_text.md', 'w', encoding='utf-8') as out:
    out.write("# Extract HTML\n")
    out.write(clean_html(d.get('extract_html', '')) + "\n\n")
    
    sections = d.get('sections', {})
    for k, v in sections.items():
        out.write(f"# {k}\n")
        out.write(clean_html(v) + "\n\n")

print("Done")
