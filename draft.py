import json
import os

input_file = 'src/ptable/data/terbium_extracted.json'
output_file = 'src/ptable/data/drafts/Terbium.json'

d = json.load(open(input_file, encoding='utf-8'))

extract_html = '''<p><b><a href="/school1/ptable/element/Tb" class="wiki-link" title="Terbium (Tb)">Terbium</a></b> is a fascinating and rare silvery-white metal that belongs to a group of elements called the lanthanides. Discovered in 1843 in a Swedish quarry, it is so soft you can cut it with a knife! Even though it might not be a household name, terbium is a hidden superstar in modern technology. It gives the brilliant green colors you see in older color TVs and modern smartphone screens, and it plays a vital role in creating super-strong magnets. In India, the quest for rare earth elements like terbium is an active frontier. While the rich coastal sands of Kerala and Odisha are famous for containing light rare earths, heavy rare earths like terbium are much harder to find in large amounts. Today, leading Indian scientific institutions, such as the Bhabha Atomic Research Centre (BARC) and Indian Rare Earths Limited (IREL), are working tirelessly to develop advanced technologies to extract and purify these precious elements from domestic minerals, pushing India toward self-reliance in high-tech materials.</p>'''

sections = {
    'Characteristics': 'Terbium is a shiny, silvery-white metal that is surprisingly soft—you can actually cut it with a knife! Like its neighbors on the periodic table, it belongs to the rare earth family. It is relatively stable in the air, meaning it doesn\'t rust or tarnish as quickly as some other metals. However, it can slowly react with water and burns brightly when heated.',
    'Physical properties': 'At room temperature, terbium is a solid metal. One of its most magical physical traits is how it responds to magnets and temperatures. When it gets very cold, it acts like a strong magnet. In everyday conditions, it is weakly magnetic. It also has a special party trick: when mixed into certain materials, it glows with a brilliant, vibrant green light, making it incredibly useful for lighting and displays.',
    'Chemical properties': 'Chemically, terbium is quite friendly and likes to combine with other elements. It is mostly found in a "+3" state, which simply means it likes to share three of its electrons when bonding. It dissolves beautifully in acids to form colorful solutions that often look faintly pink or colorless.',
    'Isotopes': 'In nature, terbium is incredibly pure—it exists almost entirely as just one stable version (or "isotope") called Terbium-159. Scientists have created other radioactive versions of terbium in laboratories, but they break down quickly and aren\'t found naturally on Earth.',
    'History': 'Terbium\'s story began in 1843 when Swedish chemist Carl Gustaf Mosander was studying a strange black rock from a quarry in Ytterby, Sweden. He found that it contained not one, but three new elements! He named them yttrium, erbium, and terbium, all honoring the small town of Ytterby. For a long time, terbium was just a scientific curiosity, but as technology advanced, its glowing and magnetic secrets were unlocked.',
    'Occurrence': 'You won\'t find terbium lying around on its own in nature. It is always hidden inside complex minerals like monazite and bastnäsite. In India, the beautiful beaches of Kerala (especially the Chavara-Neendakara belt), Tamil Nadu, and Odisha hold massive reserves of monazite sand. However, these sands are mostly rich in "light" rare earths. Heavy rare earths like terbium are present in much smaller amounts. Today, Indian institutions like the Bhabha Atomic Research Centre (BARC) and Indian Rare Earths Limited (IREL) are at the forefront of researching new ways to efficiently extract these vital heavy rare earths from domestic resources, aiming to boost India\'s technological independence.',
    'Applications': 'Terbium is a modern-day technological hero. Its most famous job is creating the bright green colors in screens, from vintage color televisions to the crisp displays of modern smartphones and tablets. It is also used in energy-saving fluorescent lamps. Beyond lighting, terbium is combined with iron and its sister element, dysprosium, to create "Terfenol-D," a special alloy that physically changes its shape in a magnetic field! This shape-shifting superpower is used in advanced sonar systems, sensors, and special speakers.'
}

d['extract_html'] = extract_html
d['sections'] = sections

os.makedirs(os.path.dirname(output_file), exist_ok=True)
with open(output_file, 'w', encoding='utf-8') as f:
    json.dump(d, f, indent=4)
print('Success!')
