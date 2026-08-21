import json
import os

draft_extract = '''<p><b>Francium (Fr)</b> is the rebel of the periodic table! It is the rarest naturally occurring element on Earth and is incredibly radioactive. With an atomic number of 87, it sits at the very bottom of the alkali metals group, right below <a href="/school1/ptable/element/Cs" class="wiki-link" title="Caesium (Cs)">caesium</a>. Because it decays so quickly, no one has ever seen a chunk of francium, but scientists believe it would be a highly reactive, silvery-gray metal. It's so elusive and fragile that a visible piece would instantly vaporize itself from its own intense radioactive heat!</p>'''

draft_sections = {
    'Characteristics': '''<p>If you could somehow gather enough <a href="/school1/ptable/element/Fr" class="wiki-link" title="Francium (Fr)">francium</a> to see it, the sample would not last long! It is one of the most unstable natural elements known to science. Its most stable form, francium-223, lasts for only 22 minutes before breaking apart into other elements like <a href="/school1/ptable/element/Ra" class="wiki-link" title="Radium (Ra)">radium</a> or <a href="/school1/ptable/element/Rn" class="wiki-link" title="Radon (Rn)">radon</a>. Chemically, francium is a heavy alkali metal, meaning it should react explosively with water, much like its cousin caesium. While no one has ever made enough of it to test its melting point directly, scientists estimate that francium would melt at around 27 °C (81 °F)—meaning it could potentially melt in the palm of your hand, if it weren't so dangerously radioactive!</p>''',
    
    'Isotopes': '''<p>Francium has 37 known forms, called isotopes, but almost all of them exist for only a fraction of a second before vanishing. The two "longest-lasting" isotopes are francium-223 (with a 22-minute lifespan) and francium-221 (lasting just under 5 minutes). Francium-223 is naturally born from the radioactive decay of <a href="/school1/ptable/element/U" class="wiki-link" title="Uranium (U)">uranium</a> and <a href="/school1/ptable/element/Ac" class="wiki-link" title="Actinium (Ac)">actinium</a>. Its incredibly short life makes studying it a race against the clock! Indian scientists at institutions like the <b>Institute of Physics (Bhubaneswar)</b> and <b>Ravenshaw University</b> have made vital theoretical contributions to our understanding of these fleeting isotopes. Using advanced mathematical models, such as the Relativistic Mean Field (RMF) theory, Indian researchers help calculate the hidden properties of francium isotopes—like their binding energy and decay rates—without needing to physically hold the dangerous element.</p>''',
    
    'Applications': '''<p>Because francium is so rare and disappears so quickly, it has no everyday uses—you won't find it in batteries, medicines, or factories! However, it is a fascinating subject for fundamental physics research. Scientists can artificially create and trap a few francium atoms at a time using lasers and magnetic fields. Because its atomic structure is relatively simple yet heavy, studying francium helps physicists test the most fundamental laws of the universe. In India, experts associated with the <b>Raman Research Institute</b> have been deeply engaged in studying the spectroscopic properties of heavy atoms like francium, helping the global scientific community unravel the mysteries of atomic behavior and quantum mechanics.</p>''',
    
    'History': '''<p>For decades, chemists knew there was a missing puzzle piece at the bottom of the periodic table's first column. Russian chemist Dmitri Mendeleev, who designed the periodic table, predicted its existence in the 1870s and called it <i>eka-caesium</i>. After many false alarms and incorrect claims by various scientists trying to find element 87, it was finally discovered in 1939 by a brilliant French physicist named Marguerite Perey. She was studying the decay of <a href="/school1/ptable/element/Ac" class="wiki-link" title="Actinium (Ac)">actinium</a> at the Curie Institute in Paris when she noticed a strange new radiation signature. She eventually named her new discovery "Francium" in honor of her home country, making it the last naturally occurring element to be discovered!</p>''',
    
    'Occurrence': '''<p>Francium is the ultimate needle in a haystack. It is the rarest naturally occurring element on our planet. It is constantly being created and destroyed deep underground in <a href="/school1/ptable/element/U" class="wiki-link" title="Uranium (U)">uranium</a>-rich minerals. At any given moment, scientists estimate there is less than 30 grams (about one ounce) of francium in the entire crust of the Earth! If you were to examine a billion billion atoms of natural uranium, you would be lucky to find just one single atom of francium. Because it is practically impossible to mine or extract, researchers who want to study francium have to create it artificially in particle accelerators.</p>'''
}

with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

fr_data = data['Fr']
fr_data['extract_html'] = draft_extract
fr_data['sections'] = draft_sections

os.makedirs('src/ptable/data/drafts', exist_ok=True)
with open('src/ptable/data/drafts/Francium.json', 'w', encoding='utf-8') as f:
    json.dump({'Fr': fr_data}, f, indent=4)

print('Done writing Francium.json')
