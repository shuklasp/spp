import json
import os

os.makedirs('src/ptable/data/drafts', exist_ok=True)
with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)['Ds']

data['extract_html'] = "Darmstadtium (symbol Ds, atomic number 110) is a highly radioactive, synthetic chemical element. It does not exist in nature and is created only in specialized laboratories. Its most stable known isotope, darmstadtium-281, has a very brief half-life of just about 14 seconds. It was first synthesized in November 1994 at the GSI Helmholtz Centre for Heavy Ion Research in the city of Darmstadt, Germany, which gave the element its name.\n\nOn the periodic table, darmstadtium sits in period 7 and group 10. Scientists predict that it belongs to the transition metals and should share chemical properties with elements like nickel, palladium, and platinum. Because it decays so quickly into lighter elements, researchers haven't been able to conduct traditional chemical experiments on it. Currently, darmstadtium has no practical or commercial uses, but studying it helps scientists push the boundaries of particle physics and understand the fundamental building blocks of our universe."

if 'sections' not in data:
    data['sections'] = {}

data['sections']['History'] = "The discovery of darmstadtium is a testament to modern nuclear physics. It was first successfully created on November 9, 1994, by a team of scientists led by Peter Armbruster and Gottfried Muenzenberg at the GSI Helmholtz Centre for Heavy Ion Research in Darmstadt, Germany. \n\nThe team produced a single atom of the isotope darmstadtium-269 by firing high-energy nickel-62 ions at a target made of lead-208 in a heavy-ion accelerator. In honor of the city where the breakthrough occurred, the element was named 'darmstadtium'. The International Union of Pure and Applied Chemistry (IUPAC) officially recognized this name in 2003. \n\nWhile this element has no specific 'Indian context' in its origin, Indian physicists and chemists eagerly study it within global academic curricula to understand the behavior of superheavy elements and the absolute limits of the periodic table."

data['sections']['Isotopes'] = "Darmstadtium does not have any stable or naturally occurring isotopes. Every single version (isotope) of this element is highly radioactive and must be artificially created in a laboratory. This is typically done either by fusing two lighter atoms together in a particle accelerator or by observing the radioactive decay of even heavier elements.\n\nScientists have reported eleven different isotopes of darmstadtium, with atomic masses ranging between 267 and 281. The most stable of these is darmstadtium-281, which lasts for about 14 seconds before falling apart. When these isotopes decay, they generally emit alpha particles or undergo spontaneous fission, breaking apart into smaller, more stable elements almost as quickly as they are created."

with open('src/ptable/data/drafts/Darmstadtium.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)
