import json
import os

with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

fl = data['Fl']

fl['extract_html'] = """
<p><b>Flerovium</b> (symbol <b>Fl</b>) is an extremely rare and highly radioactive synthetic element with the atomic number 114. Because it does not exist in nature, scientists can only create it in advanced laboratories by crashing lighter atoms together at high speeds. It is a "superheavy" element, and any flerovium atom created only lasts for a few seconds—at most—before breaking apart into lighter elements.</p>

<p>Originally discovered in 1999 by a joint team of Russian and American scientists at the Joint Institute for Nuclear Research in Dubna, Russia, it was named after the Flerov Laboratory of Nuclear Reactions. While India did not play a role in its initial discovery, modern Indian scientists have made crucial contributions to the ongoing study of flerovium. Researchers from esteemed Indian institutions like the Saha Institute of Nuclear Physics (SINP) and IIT Roorkee have joined international teams at global facilities like the GSI Helmholtz Centre in Germany. Notably, Indian scientists were part of the pioneering chemical characterization experiments that determined whether flerovium acts like a noble gas or a volatile metal, ultimately providing strong evidence that it has metallic characteristics.</p>

<p>Even though we can only study a few atoms of flerovium at a time, it is incredibly important to science. It helps researchers understand the extreme limits of chemistry and physics, and brings them one step closer to the theorized "island of stability"—a group of yet-to-be-discovered superheavy elements that might last much longer than those currently known.</p>
"""

fl['sections']['History'] = """
<h2>History and Discovery</h2>
<p>The journey to create element 114 began in the late 1990s. In 1999, scientists at the Joint Institute for Nuclear Research (JINR) in Dubna, Russia, working alongside colleagues from the Lawrence Livermore National Laboratory in the United States, successfully smashed calcium atoms into a plutonium target to create the very first atoms of what is now called flerovium.</p>
<p>The name "flerovium" officially honors the Flerov Laboratory of Nuclear Reactions in Russia, where it was discovered, which itself was named after the prominent Russian physicist Georgy Flyorov. In 2012, the International Union of Pure and Applied Chemistry (IUPAC) officially adopted the name and the symbol Fl.</p>
<p>While the initial discovery took place in Russia, understanding the true nature of flerovium has required a global effort. Notably, scientists from India have actively contributed to expanding our knowledge of this fleeting element. Experts from India's Saha Institute of Nuclear Physics and the Indian Institute of Technology (IIT) Roorkee have traveled to leading European laboratories to help study how flerovium chemically reacts, cementing India's footprint in the ongoing global quest to map the superheavy elements.</p>
"""

fl['sections']['Isotopes'] = """
<h2>Isotopes of Flerovium</h2>
<p>Like many elements, flerovium can exist in slightly different forms called <b>isotopes</b>. An isotope has the same number of protons (114 for flerovium) but a different number of neutrons. All isotopes of flerovium are highly radioactive and unstable, meaning they decay (break apart) very rapidly.</p>
<p>There are currently six known isotopes of flerovium, ranging in mass from 284 to 289. The most stable one discovered so far is Flerovium-289, which has a half-life of about 2.1 seconds—a blink of an eye for us, but a relatively long time in the world of superheavy elements! There is also a possibility of a seventh isotope, Flerovium-290, which some early experiments suggest could last for up to 19 seconds.</p>
<p>Scientists, including those from India participating in international research groups, study these fleeting isotopes very closely. They hope that by adding even more neutrons, they might eventually create heavier isotopes of flerovium that belong to the "island of stability." If successful, these future isotopes might last for hours, days, or even years, fundamentally changing our understanding of atomic physics.</p>
"""

os.makedirs('src/ptable/data/drafts', exist_ok=True)
with open('src/ptable/data/drafts/Flerovium.json', 'w', encoding='utf-8') as f:
    json.dump(fl, f, indent=4, ensure_ascii=False)

print('Saved to src/ptable/data/drafts/Flerovium.json')
