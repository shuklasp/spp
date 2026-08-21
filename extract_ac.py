import json

with open('C:/projects/apache/school1/src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

ac = data['Ac']

print("Keys:", ac.keys())

# Let's save the sections to a text file for easy reading
with open('C:/projects/apache/school1/Ac_sections.txt', 'w', encoding='utf-8') as f:
    f.write("EXTRACT HTML:\n")
    f.write(ac.get('extract_html', ''))
    f.write("\n\nSECTIONS:\n")
    for sec in ac.get('sections', []):
        f.write(f"--- {sec['title']} ---\n")
        f.write(sec.get('text', ''))
        f.write("\n\n")

print("Saved sections to Ac_sections.txt")
