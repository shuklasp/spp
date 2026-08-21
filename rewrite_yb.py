import json
import os

with open('Ytterbium_temp.json', 'r', encoding='utf-8') as f:
    yb = json.load(f)

extract_html = '''<p><b><a href="/school1/ptable/element/Yb" class="wiki-link" title="Ytterbium (Yb)">Ytterbium</a></b> is a silvery-white, soft metal that belongs to a group of elements called the lanthanides, or rare earth metals. Found in trace amounts in the Earth's crust, it is rarely used in its pure form but plays a crucial role in modern technology, from atomic clocks to powerful lasers. Although it's named after a small Swedish village where it was first discovered, Ytterbium is found globally. In India, it can be traced to the rich monazite sands along the coastal beaches of Kerala, an important geological source for rare earth minerals.</p>'''

sections = {
    'Physical properties': '''<p>Ytterbium is a shiny, soft metal that you could easily shape or bend. It has a silvery-white appearance with a slight pale yellow tint. One of its most unique features is how it reacts to extreme conditions. Under normal room temperature, it conducts electricity like a typical metal. However, if you squeeze it under immense pressure, it changes its behavior and acts more like a semiconductor (similar to the materials used in computer chips). Unlike many of its rare-earth cousins, ytterbium isn't strongly magnetic at low temperatures. It also has an unusually low melting point (824 °C or 1515 °F) and boiling point for a metal in its group.</p>''',
    'Chemical properties': '''<p>When left in the open air, ytterbium slowly loses its shine, taking on a brownish or golden tarnish as it reacts with oxygen. If you grind it into a fine powder, it becomes highly reactive and can even burn with a brilliant, emerald-green flame! It dissolves slowly in water, but if dropped into acidic solutions, it quickly bubbles away as it releases hydrogen gas. When it forms compounds, they are usually colorless or white.</p>''',
    'Isotopes': '''<p>In nature, ytterbium is a mix of seven stable "versions," known as isotopes. These natural isotopes are perfectly safe and don't break down over time. Scientists have also created over 30 artificial, radioactive isotopes in laboratories. Some of these radioactive versions, especially Ytterbium-169, give off specific types of radiation and are highly useful in medicine, acting like portable X-ray machines to help doctors see inside the human body.</p>''',
    'Occurrence': '''<p>Ytterbium doesn't exist as a pure nugget in nature. Instead, it hides inside complex rare-earth minerals. One of the most important sources is monazite sand. Interestingly, the beautiful coastal beaches of Kerala in southern India are famous for their rich deposits of monazite sands, which hold a treasure trove of rare-earth elements like ytterbium, alongside thorium. While China, the US, and Brazil are also major producers, the monazite deposits in Kerala represent an important geological and strategic resource for India. Since ytterbium looks and acts so much like the other rare earths mixed with it, scientists had a very hard time figuring out how to separate it until the mid-20th century.</p>''',
    'History': '''<p>The story of ytterbium starts in 1878 with a Swiss chemist named Jean Charles Galissard de Marignac. He found it hidden inside a mineral from a famous quarry in Ytterby, Sweden—a tiny village that has remarkably lent its name to four different elements on the periodic table! For decades, what chemists thought was just "ytterbium" turned out to be a mix of two different elements. In 1907, a French chemist named Georges Urbain finally managed to separate the true ytterbium from a new element, which he called lutetium.</p>''',
    'Applications and Modern Research': '''<p>Because it is scarce and hard to separate, ytterbium isn't used in everyday items like copper or iron. However, it has some incredible high-tech jobs. It is used to make extremely precise atomic clocks—some of the most accurate timekeepers ever created! It's also used to build powerful solid-state lasers and to strengthen stainless steel. In India, leading scientific institutions like the Jawaharlal Nehru Centre for Advanced Scientific Research (JNCASR) conduct cutting-edge research using ytterbium. They study it to develop advanced intermetallic compounds, exploring new quantum materials and their potential for future technologies.</p>'''
}

yb['extract_html'] = extract_html
yb['sections'] = sections

out_path = 'src/ptable/data/drafts/Ytterbium.json'
os.makedirs(os.path.dirname(out_path), exist_ok=True)
with open(out_path, 'w', encoding='utf-8') as f:
    json.dump(yb, f, indent=4)
print('Done!')
