import json
import os

with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    master = json.load(f)
    
sg = master['Sg']

new_p_extract = """
<p><b>Seaborgium</b> (symbol <b>Sg</b>) is a fascinating, human-made chemical element with the atomic number 106. Unlike gold or oxygen, it doesn't exist anywhere in nature. Instead, it can only be created in highly advanced laboratories by smashing smaller atoms together at incredible speeds. Seaborgium is highly radioactive and unstable—its most robust forms exist for only a few minutes before breaking apart into lighter elements!</p>

<p>Named in honor of the renowned American nuclear chemist Glenn T. Seaborg, it's one of the very few elements ever named after a living person. In the periodic table, Seaborgium belongs to the transition metals group. Because it vanishes so quickly, scientists have only been able to study a few atoms of it at a time. However, tests show it behaves much like its heavier "chemical cousin," tungsten.</p>

<p>Indian scientists have made important contributions to the ongoing exploration of such superheavy elements. For instance, researchers like Prof. M. Maiti from IIT Roorkee collaborated at the GSI Helmholtz Centre in Germany in 2025 to help synthesize a brand-new form of the element: the Seaborgium-257 isotope! Additionally, India’s premier institutes like the Bhabha Atomic Research Centre (BARC) and the Variable Energy Cyclotron Centre (VECC) are continually pushing the boundaries of radiochemistry, mapping out the complex chemical behavior of these fleeting elements.</p>
"""

new_history = """
<p>In 1974, the race to discover element 106 was intensely competitive! Two brilliant teams of scientists on opposite sides of the Cold War—one at the Lawrence Berkeley National Laboratory in the United States and the other at the Joint Institute for Nuclear Research in the Soviet Union—almost simultaneously created a few fleeting atoms of Seaborgium.</p>

<p>Because both teams claimed they got there first, a massive naming dispute erupted that lasted for over two decades! The Americans wanted to name it after Glenn T. Seaborg, a legendary chemist who helped discover many heavy elements. The Soviets, however, had their own ideas. It wasn't until 1997 that the international scientific community finally agreed on the name "Seaborgium." It was a historic moment, making Seaborg the first person to have an element named after him while he was still alive to see it!</p>

<p>Today, the quest to understand superheavy elements continues globally, and Indian scientists are writing the newest chapters of this history. Experts from institutions like the Indian Institute of Technology (IIT) Roorkee and the Bhabha Atomic Research Centre (BARC) collaborate with international mega-labs to uncover more secrets of these mysterious elements. Their work keeps the spirit of global scientific discovery alive.</p>
"""

new_isotopes = """
<p>In the world of chemistry, an "isotope" is simply a different version of an element. All Seaborgium atoms have exactly 106 protons, but they can have different numbers of neutrons. Currently, we know of about a dozen isotopes of Seaborgium, ranging from Seaborgium-257 to Seaborgium-271.</p>

<p>None of these isotopes are stable. They are completely radioactive, meaning they burst apart rapidly to form lighter, more stable elements. The longest-lasting isotope, Seaborgium-271, survives for about 2.4 minutes before vanishing.</p>

<p>Interestingly, the search for new isotopes is one of the most exciting fields in modern physics. Scientists are hunting for an "island of stability"—a theoretical sweet spot where superheavy elements might last for years instead of seconds! Indian researchers recently made headlines in this pursuit. In 2025, a global team featuring Prof. M. Maiti from IIT Roorkee successfully synthesized a brand-new isotope, Seaborgium-257, at a facility in Germany. Discoveries like this help scientists understand the incredible forces that hold atoms together at the very edge of the periodic table.</p>
"""

text = sg.get('extract_html', '')
start = text.rfind('</table>') + 8
end = text.rfind('<meta')
if start > 7 and end > 0:
    sg['extract_html'] = text[:start] + '\\n' + new_p_extract.strip() + '\\n' + text[end:]
else:
    sg['extract_html'] = new_p_extract

sg['sections']['History'] = new_history.strip()
sg['sections']['Isotopes'] = new_isotopes.strip()

out_dir = 'src/ptable/data/drafts'
os.makedirs(out_dir, exist_ok=True)
with open(os.path.join(out_dir, 'Seaborgium.json'), 'w', encoding='utf-8') as f:
    json.dump({'Sg': sg}, f, indent=2, ensure_ascii=False)

print("Done writing Seaborgium.json")
