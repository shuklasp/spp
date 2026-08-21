import json
import urllib.request
import urllib.error
import urllib.parse
import time
import os
import re

# Paths
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
JSON_FILE = os.path.join(BASE_DIR, 'src', 'ptable', 'data', 'compounds.json')
IMAGES_DIR = os.path.join(BASE_DIR, 'src', 'ptable', 'assets', 'compounds')
SDF_DIR = os.path.join(BASE_DIR, 'src', 'ptable', 'assets', 'compounds', '3d')

os.makedirs(IMAGES_DIR, exist_ok=True)
os.makedirs(SDF_DIR, exist_ok=True)

# Load existing to preserve our curated ones
if os.path.exists(JSON_FILE):
    with open(JSON_FILE, 'r', encoding='utf-8') as f:
        compounds = json.load(f)
else:
    compounds = []

existing_cids = {c.get('pubchem_cid') for c in compounds if c.get('pubchem_cid')}

def fetch_json(url):
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (ptable build script)'})
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
    # Basic regex to extract elements like C, H, Na, Cl
    matches = re.findall(r'([A-Z][a-z]*)', formula)
    return list(set(matches))

def is_organic(formula):
    elements = parse_elements(formula)
    # Very basic heuristic: contains C and H, and not a simple carbonate
    return 'C' in elements and 'H' in elements

def fetch_wikipedia_summary(title):
    # Format title for Wikipedia URL
    formatted_title = title.replace(' ', '_')
    url = f"https://en.wikipedia.org/api/rest_v1/page/summary/{urllib.parse.quote(formatted_title)}"
    data = fetch_json(url)
    if data and 'extract' in data:
        # Get first two sentences
        sentences = data['extract'].split('. ')
        return '. '.join(sentences[:2]) + ('.' if len(sentences) > 0 and not sentences[0].endswith('.') else '')
    return "A chemical compound."

TARGET_COUNT = 1000
current_count = len(compounds)
cid = 1

print(f"Starting expansion. Current count: {current_count}. Target: {TARGET_COUNT}")

while current_count < TARGET_COUNT and cid < 15000:
    if cid in existing_cids:
        cid += 1
        continue
        
    print(f"Processing CID {cid}...")
    
    # 1. Get Basic Info
    prop_url = f"https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{cid}/property/MolecularFormula,MolecularWeight,Title/JSON"
    prop_data = fetch_json(prop_url)
    time.sleep(0.2) # Rate limit
    
    if not prop_data or 'PropertyTable' not in prop_data:
        cid += 1
        continue
        
    props = prop_data['PropertyTable']['Properties'][0]
    title = props.get('Title')
    formula = props.get('MolecularFormula')
    weight = props.get('MolecularWeight')
    
    if not title or not formula:
        cid += 1
        continue
        
    # Standardize ID
    comp_id = title.lower().replace(' ', '-').replace('(', '').replace(')', '').replace(',', '')
    
    # Check if we already have this ID
    if any(c['id'] == comp_id for c in compounds):
        cid += 1
        continue

    # 2. Get Wikipedia summary
    description = fetch_wikipedia_summary(title)
    time.sleep(0.1)
    
    # 3. Download PNG
    png_path = os.path.join(IMAGES_DIR, f"{comp_id}.png")
    png_url = f"https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{cid}/PNG?image_size=300x300"
    if not download_file(png_url, png_path):
        cid += 1
        continue # Skip if no image
    time.sleep(0.2)
        
    # 4. Download SDF
    sdf_path = os.path.join(SDF_DIR, f"{comp_id}.sdf")
    sdf_3d_url = f"https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{cid}/record/SDF/?record_type=3d"
    sdf_2d_url = f"https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{cid}/record/SDF/?record_type=2d"
    
    if not download_file(sdf_3d_url, sdf_path):
        if not download_file(sdf_2d_url, sdf_path):
            pass # We'll just show 2D image in UI if SDF fails
    time.sleep(0.2)

    # 5. Format HTML formula (basic naive implementation)
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
        "description": description,
        "uses": "Various industrial and research applications.",
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
                "hazards": "Check MSDS for safety info",
                "reactivity": "N/A"
            }
        },
        "facts": [
            f"{title} is a chemical compound with the formula {formula}.",
            f"Its molecular weight is {weight} g/mol."
        ]
    }
    
    compounds.append(new_comp)
    current_count += 1
    
    # Save every 10 compounds so we don't lose progress
    if current_count % 10 == 0:
        with open(JSON_FILE, 'w', encoding='utf-8') as f:
            json.dump(compounds, f, indent=4)
        print(f"Saved {current_count} compounds...")
        
    cid += 1

# Final save
with open(JSON_FILE, 'w', encoding='utf-8') as f:
    json.dump(compounds, f, indent=4)

print("Finished fetching 1000 compounds!")
