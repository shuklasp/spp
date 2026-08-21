import json

with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

np_data = data['Np']
html = np_data['extract_html']
idx = html.find('<b>Neptunium</b>')
infobox = html[:idx-3]

new_extract = infobox + """
<p><b>Neptunium</b> is a fascinating, silvery metal and a synthetic chemical element with the symbol <b>Np</b> and atomic number 93. It belongs to the actinide series on the periodic table and holds the special title of being the very first "transuranic" element—meaning it is the first element that comes after uranium, which is the heaviest naturally occurring element in large amounts. Named after the planet Neptune (just as uranium was named after Uranus), neptunium is highly radioactive and must be handled with great care.</p>
<p>While ancient Indian philosophers and alchemists (Rasashastra) long dreamed of transmuting one material into another, it wasn't until the modern atomic age that true elemental transmutation became a reality. Today, modern Indian scientists at leading institutions like the <b>Bhabha Atomic Research Centre (BARC)</b> and the <b>Indira Gandhi Centre for Atomic Research (IGCAR)</b> actively study neptunium. Because it is created inside nuclear reactors as a byproduct of generating energy, understanding and managing neptunium is a key part of India's advanced closed nuclear fuel cycle program.</p>
"""

sections = {}

sections['Characteristics'] = """<p>Neptunium is a hard, silvery metal that slowly tarnishes when exposed to air. If you could safely hold it, it would feel quite heavy, similar to gold or uranium. However, because it is radioactive and toxic, it can only be handled using specialized safety equipment.</p>
<p>Chemically, neptunium is incredibly versatile. It can change its internal structure into three different forms (allotropes) depending on the temperature, and it can combine with other elements in five different "oxidation states," giving its chemical compounds a variety of vibrant colors. Like its neighbors on the periodic table, neptunium is pyrophoric, meaning that in fine powder form, it can spontaneously catch fire at room temperature.</p>"""

sections['Isotopes'] = """<p>In chemistry, isotopes are versions of the same element that weigh slightly different amounts. Neptunium has 24 known radioisotopes, meaning all of its versions are radioactive and will eventually break down (decay) into lighter elements. </p>
<p>The most stable of these is <b>neptunium-237</b>, which takes about 2.14 million years for half of it to decay. While this sounds like a very long time, it is quite short compared to the 4.5-billion-year age of the Earth! This means that any neptunium present when the Earth was born has long since decayed away. Another notable isotope is neptunium-236, with a half-life of 153,000 years. The rest of its isotopes break down very quickly, mostly in a matter of days or even minutes.</p>"""

sections['Occurrence'] = """<p>Because its longest-lived isotope (neptunium-237) doesn't last nearly as long as the Earth has been around, you won't find significant amounts of neptunium in nature. It is almost entirely a man-made element.</p>
<p>However, nature does make incredibly tiny, microscopic trace amounts of it deep underground in uranium-rich ores. When uranium atoms naturally decay, they sometimes release stray particles that bump into other uranium atoms, transforming them briefly into neptunium. But these natural occurrences are so minuscule that almost all the neptunium in existence today is produced in nuclear reactors by humans. Some traces also exist in the environment as leftover fallout from atmospheric nuclear weapons testing conducted during the mid-20th century.</p>"""

sections['History'] = """<p>The story of neptunium is the story of pushing the boundaries of the periodic table. For years, scientists searched for "Element 93" but repeatedly came up empty-handed, leading to several false announcements. The breakthrough finally came in 1940, when American physicists <b>Edwin McMillan</b> and <b>Philip H. Abelson</b> successfully created it at the Berkeley Radiation Laboratory by bombarding uranium with neutrons.</p>
<p>This achievement was the modern realization of an ancient dream. For centuries, practitioners of ancient Indian alchemy (Rasashastra) and metallurgists worldwide theorized about changing base metals into extraordinary new substances. McMillan and Abelson achieved a modern version of this transmutation, proving that humans could create entirely new, heavier elements that nature had long ago lost.</p>"""

sections['Applications'] = """<p>While neptunium doesn't have many everyday commercial uses, it is incredibly important for space exploration and nuclear energy research.</p>
<p>Its primary role is as a stepping stone to create <b>plutonium-238</b>. By irradiating neptunium-237 in a reactor, scientists produce plutonium-238, which is the vital power source used in Radioisotope Thermoelectric Generators (RTGs). These RTGs are essentially nuclear batteries that provide long-lasting electricity for deep-space missions, such as the Voyager probes and Mars rovers.</p>
<p>In India, institutions like BARC and IGCAR are heavily focused on the chemistry of neptunium. As India pursues a "closed nuclear fuel cycle" to maximize energy from its resources, managing the neptunium byproduct is crucial. Indian scientists are developing advanced chemical processes to separate neptunium from nuclear waste. This not only makes the long-term storage of nuclear waste much safer but also helps recover valuable materials that can be reused to power the future.</p>"""

np_data['extract_html'] = new_extract
np_data['sections'] = sections

out_data = {'Np': np_data}

import os
os.makedirs('src/ptable/data/drafts', exist_ok=True)

with open('src/ptable/data/drafts/Neptunium.json', 'w', encoding='utf-8') as f:
    json.dump(out_data, f, indent=2)

print('Done writing to src/ptable/data/drafts/Neptunium.json')
