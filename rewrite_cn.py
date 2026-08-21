import json
import os

input_file = r'c:\projects\apache\school1\src\ptable\data\scratch_cn.json'
output_dir = r'c:\projects\apache\school1\src\ptable\data\drafts'
output_file = os.path.join(output_dir, 'Copernicium.json')

with open(input_file, 'r', encoding='utf-8') as f:
    data = json.load(f)

extract_html = """<p><b>Copernicium</b> (symbol <b>Cn</b>, atomic number 112) is a fascinating, superheavy synthetic element that you won't find anywhere in nature. Because it is highly radioactive, it can only be created in specialized laboratories by smashing lighter elements together. To date, scientists have only ever created a few dozen atoms of Copernicium! It sits in group 12 of the periodic table, right under zinc, cadmium, and mercury. Interestingly, because of relativistic effects where its electrons move at near light-speed, Copernicium behaves a lot like a noble gas or a volatile liquid, rather than a typical metal. The most stable known isotope, Copernicium-285, lasts for only about 30 seconds before breaking apart into lighter elements. Because of its extreme rarity and fleeting existence, Copernicium is used entirely for scientific research to help us understand the limits of nuclear physics and the structure of the universe.</p>"""

history_text = """<p>Copernicium was first discovered on February 9, 1996, at the GSI Helmholtz Centre for Heavy Ion Research in Darmstadt, Germany. A team of scientists led by Sigurd Hofmann achieved this by firing a beam of zinc-70 atoms at a lead-208 target inside a massive heavy-ion accelerator. For over a decade, it was temporarily called 'ununbium' (symbol Uub) as scientists worked to officially confirm its existence. Finally, in February 2010—fittingly on the 537th anniversary of his birth—the element was officially named 'Copernicium' in honor of the legendary Renaissance astronomer Nicolaus Copernicus, who revolutionized our understanding of the universe with his heliocentric (sun-centered) model.</p>"""

isotopes_text = """<p>Copernicium has no stable isotopes; all of them are highly radioactive and quickly decay. Scientists have discovered a handful of different isotopes ranging in mass numbers from 277 to 286. Most of these vanish in mere fractions of a second! The 'longest-living' isotope is Copernicium-285, which has a half-life of just about 30 seconds. Because these atoms fall apart so rapidly (usually through a process called alpha decay or spontaneous fission), researchers have to use highly sensitive equipment to detect their fleeting presence. In fact, studying the decay of Copernicium isotopes has helped scientists discover and confirm the existence of even heavier elements on the periodic table, like flerovium and livermorium.</p>"""

data['extract_html'] = extract_html
data['sections'] = {
    'History': history_text,
    'Isotopes': isotopes_text
}

os.makedirs(output_dir, exist_ok=True)
with open(output_file, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print("Saved to", output_file)
