import json

with open('C:/projects/apache/school1/src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    ac = json.load(f)['Ac']

with open('C:/projects/apache/school1/ac_text.txt', 'w', encoding='utf-8') as f:
    f.write("EXTRACT HTML:\n")
    f.write(ac['extract_html'])
    f.write("\n\nHISTORY:\n")
    f.write(ac['sections'].get('History', ''))
    f.write("\n\nISOTOPES:\n")
    f.write(ac['sections'].get('Isotopes', ''))
    f.write("\n\nAPPLICATIONS:\n")
    f.write(ac['sections'].get('Applications', ''))
