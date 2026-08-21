import json
import os

# Create the drafts directory if it doesn't exist
os.makedirs('src/ptable/data/drafts', exist_ok=True)

# Load the original mendelevium data
with open('mendelevium.json', encoding='utf-8') as f:
    md = json.load(f)

# Rewrite extract_html
md['extract_html'] = """<p><strong>Mendelevium (symbol Md, atomic number 101)</strong> is a fascinating synthetic chemical element. It belongs to the actinide series and is a radioactive metal. It is famously named after Dmitri Mendeleev, the creator of the periodic table. Mendeleev’s systemic organization of the periodic table shares conceptual similarities with the ancient Indian <i>akṣara-mālā</i> (the systematic arrangement of Sanskrit sounds), an influence noted by Indian scholars like Professor Gautam Desiraju. Furthermore, Mendeleev famously utilized Sanskrit prefixes—such as 'eka' (one), 'dvi' (two), and 'tri' (three)—to predict undiscovered elements like eka-aluminium (gallium).</p>

<p>Mendelevium is the very first element (by atomic number) that cannot be created in large, visible quantities simply by bombarding lighter elements with neutrons. Instead, it must be carefully forged in particle accelerators by colliding charged particles with lighter elements. It was first synthesized in 1955 when scientists bombarded einsteinium with alpha particles, and this same technique is still used today. Using just a microscopic speck of einsteinium-253, scientists can create over a million mendelevium atoms per hour!</p>

<p>Chemically, mendelevium behaves much like other late actinides, typically showing a +3 oxidation state, though a +2 state is also possible in solution. Because all of its known isotopes have very short lifespans (half-lives), mendelevium is primarily created in tiny amounts for basic scientific research. Today, the intricate science of transuranic elements like mendelevium forms a prominent part of advanced chemistry curricula in premier Indian institutions, such as the IITs and IISc, inspiring new generations of researchers.</p>"""

# Rewrite Characteristics 
characteristics_html = """<p>Mendelevium is a highly radioactive, synthetic metal. Because it is produced in such minuscule amounts and decays rapidly, its physical appearance has never been seen, but scientists predict it would be a solid, silvery metal under normal conditions. Chemically, it acts as a typical late actinide.</p>"""

# Rewrite Isotopes
isotopes_html = """<p>Scientists have discovered seventeen isotopes of mendelevium, ranging in mass from 244 to 260. Every single one of them is radioactive. The most stable isotope is mendelevium-258, which has a half-life of about 51.6 days. Interestingly, mendelevium is the final element on the periodic table to have any isotope with a half-life longer than a single day.</p>

<p>Despite mendelevium-258 being the longest-lived, scientists more commonly use mendelevium-256 (which has a half-life of just 77.7 minutes) in chemistry experiments. This is because mendelevium-256 can be produced much more easily and in larger quantities from einsteinium.</p>

<p>Mendelevium-256 primarily decays through a process called electron capture (about 90% of the time) and occasionally through alpha decay (10%). Researchers usually detect it by watching for the spontaneous fission of its "daughter" element, fermium-256, though measuring its unique alpha decay energies is also a highly effective identification method.</p>"""

md['sections']['Characteristics'] = characteristics_html
md['sections']['Isotopes'] = isotopes_html

# We need to save the dictionary (with the element symbol as the key)
output = {"Md": md}

with open('src/ptable/data/drafts/Mendelevium.json', 'w', encoding='utf-8') as f:
    json.dump(output, f, indent=2, ensure_ascii=False)

print("Saved successfully to src/ptable/data/drafts/Mendelevium.json")
