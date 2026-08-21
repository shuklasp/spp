import json
import urllib.request
import urllib.parse
import time
import os
import re

# Paths
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
JSON_FILE = os.path.join(BASE_DIR, 'src', 'ptable', 'data', 'compounds.json')
TARGETS_FILE = os.path.join(BASE_DIR, 'src', 'ptable', 'data', 'compound_targets.json')
IMAGES_DIR = os.path.join(BASE_DIR, 'src', 'ptable', 'assets', 'compounds')
SDF_DIR = os.path.join(BASE_DIR, 'src', 'ptable', 'assets', 'compounds', '3d')

os.makedirs(IMAGES_DIR, exist_ok=True)
os.makedirs(SDF_DIR, exist_ok=True)

# Load existing to preserve
if os.path.exists(JSON_FILE):
    with open(JSON_FILE, 'r', encoding='utf-8') as f:
        compounds = json.load(f)
else:
    compounds = []

existing_cids = {c.get('pubchem_cid') for c in compounds if c.get('pubchem_cid')}
existing_ids = {c.get('id') for c in compounds}

# Load Targets
if os.path.exists(TARGETS_FILE):
    with open(TARGETS_FILE, 'r', encoding='utf-8') as f:
        targets_dict = json.load(f)
else:
    print("Error: Target file not found!")
    exit(1)

# Flatten targets from {"Fe": ["Iron(III) oxide", ...]} to a single list of unique names
flat_targets = set()
for element, names in targets_dict.items():
    for name in names:
        flat_targets.add(name)

flat_targets = list(flat_targets)
print(f"Loaded {len(flat_targets)} unique compound targets to process.")

def fetch_json(url):
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (ptable diverse script)'})
    try:
        with urllib.request.urlopen(req, timeout=10) as response:
            return json.loads(response.read().decode())
    except Exception as e:
        return None

def download_file(url, path):
    if os.path.exists(path): return True
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    try:
        with urllib.request.urlopen(req, timeout=10) as response:
            with open(path, 'wb') as f:
                f.write(response.read())
            return True
    except Exception:
        return False

def parse_elements(formula):
    matches = re.findall(r'([A-Z][a-z]*)', formula)
    return list(set(matches))

def is_organic(formula):
    elements = parse_elements(formula)
    return 'C' in elements and 'H' in elements

def fetch_wikipedia_details(title):
    formatted_title = title.replace(' ', '_')
    url = f"https://en.wikipedia.org/w/api.php?action=query&prop=extracts&explaintext=1&titles={urllib.parse.quote(formatted_title)}&format=json"
    data = fetch_json(url)
    if not data or 'query' not in data or 'pages' not in data['query']:
        return None, None, None, None
        
    pages = data['query']['pages']
    extract = ""
    for page_id, page_data in pages.items():
        if 'extract' in page_data:
            extract = page_data['extract']
            break
            
    if not extract:
        return None, None, None, None
        
    # Parse the extract
    # The intro is everything before the first == Heading ==
    intro_match = re.split(r'\n== .*? ==\n', extract)
    intro = intro_match[0] if intro_match else ""
    
    # Extract uses
    uses = "Various industrial and research applications."
    uses_match = re.search(r'\n== Uses ==\n(.*?)(?=\n== |\Z)', extract, re.DOTALL | re.IGNORECASE)
    if uses_match:
        uses = uses_match.group(1).strip()[:1000] # Limit to 1000 chars
        
    # Extract safety
    safety = "Check MSDS for safety info."
    safety_match = re.search(r'\n== Safety ==\n(.*?)(?=\n== |\Z)', extract, re.DOTALL | re.IGNORECASE)
    if not safety_match:
        safety_match = re.search(r'\n== Hazards ==\n(.*?)(?=\n== |\Z)', extract, re.DOTALL | re.IGNORECASE)
    if safety_match:
        safety = safety_match.group(1).strip()[:1000]
        
    # Extract history
    history = ""
    history_match = re.search(r'\n== History ==\n(.*?)(?=\n== |\Z)', extract, re.DOTALL | re.IGNORECASE)
    if history_match:
        history = history_match.group(1).strip()[:1000]
        
    return intro[:2000], uses, safety, history

added_count = 0

for target in flat_targets:
    # 1. Search PubChem by Name
    print(f"Processing {target}...")
    prop_url = f"https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/{urllib.parse.quote(target)}/property/MolecularFormula,MolecularWeight,Title/JSON"
    prop_data = fetch_json(prop_url)
    time.sleep(0.3) # Rate limit
    
    if not prop_data or 'PropertyTable' not in prop_data:
        print(f"  -> Not found in PubChem by name")
        continue
        
    props = prop_data['PropertyTable']['Properties'][0]
    cid = props.get('CID')
    title = props.get('Title') or target
    formula = props.get('MolecularFormula')
    weight = props.get('MolecularWeight')
    
    if not cid or not formula:
        continue
        
    if cid in existing_cids:
        continue
        
    comp_id = title.lower().replace(' ', '-').replace('(', '').replace(')', '').replace(',', '')
    if comp_id in existing_ids:
        continue

    # 2. Get Wikipedia Details
    intro, uses, safety, history = fetch_wikipedia_details(title)
    if not intro:
        intro, uses, safety, history = fetch_wikipedia_details(target)
        
    if not intro:
        intro = f"{title} is a chemical compound with the formula {formula}."
        uses = "Various industrial and research applications."
        safety = "Check MSDS for safety info."
    time.sleep(0.1)
    
    # 3. Download PNG
    png_path = os.path.join(IMAGES_DIR, f"{comp_id}.png")
    png_url = f"https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{cid}/PNG?image_size=300x300"
    if not download_file(png_url, png_path):
        print(f"  -> Failed to download PNG for CID {cid}")
        continue # Skip if no image
    time.sleep(0.3)
        
    # 4. Download SDF
    sdf_path = os.path.join(SDF_DIR, f"{comp_id}.sdf")
    sdf_3d_url = f"https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{cid}/record/SDF/?record_type=3d"
    sdf_2d_url = f"https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{cid}/record/SDF/?record_type=2d"
    
    if not download_file(sdf_3d_url, sdf_path):
        if not download_file(sdf_2d_url, sdf_path):
            pass 
    time.sleep(0.3)

    # 5. Format HTML formula
    html_formula = re.sub(r'(\d+)', r'<sub>\1</sub>', formula)
    
    # Add to list
    new_comp = {
        "id": comp_id,
        "name": title,
        "formula": formula,
        "html_formula": html_formula,
        "elements": parse_elements(formula),
        "organic": is_organic(formula),
        "state": "Solid", # Default fallback
        "pubchem_cid": cid,
        "description": intro,
        "uses": uses,
        "properties": {
            "physical": {
                "density": "N/A",
                "melting_point": "N/A",
                "boiling_point": "N/A",
                "molar_mass": f"{weight} g/mol",
                "appearance": "N/A",
                "solubility": "N/A"
            },
            "chemical": {
                "ph": "N/A",
                "hazards": safety,
                "reactivity": "N/A"
            }
        },
        "facts": [
            f"{title} is a chemical compound with the formula {formula}.",
            f"Its molecular weight is {weight} g/mol."
        ]
    }
    
    if history:
        new_comp['facts'].append(f"History: {history[:200]}...")
    
    compounds.append(new_comp)
    existing_cids.add(cid)
    existing_ids.add(comp_id)
    added_count += 1
    print(f"  -> Successfully added {title} (Total Added: {added_count})")
    
    # Save every 5 compounds so we don't lose progress
    if added_count % 5 == 0:
        with open(JSON_FILE, 'w', encoding='utf-8') as f:
            json.dump(compounds, f, indent=4)
        print(f"[*] Saved {len(compounds)} total compounds to database.")

# Final save
with open(JSON_FILE, 'w', encoding='utf-8') as f:
    json.dump(compounds, f, indent=4)

print(f"Finished! Added {added_count} new diverse compounds.")
