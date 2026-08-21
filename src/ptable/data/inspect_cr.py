import json
import os

master_path = r'c:\projects\apache\school1\src\ptable\data\master_elements.json'
with open(master_path, 'r', encoding='utf-8') as f:
    master_data = json.load(f)

cr_data = master_data['Cr']
print("Cr keys:", cr_data.keys())
print("Original sections:", list(cr_data['sections'].keys()))
