import json
import os

with open(r'c:\projects\apache\school1\src\ptable\data\drafts\os_temp.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

data['extract_html'] = '''<p><b>Osmium (Os)</b> is a fascinating and incredibly dense metal, known for being the heaviest naturally occurring element on Earth. Imagine a block of osmium the size of a milk jug—it would weigh about as much as an adult human! First discovered in 1803 by English chemist Smithson Tennant, it has a beautiful bluish-silver color and is highly prized for its extreme hardness.</p><p>While ancient Indian texts like the Vedas and traditional Ayurvedic treatises like the <em>Charaka Samhita</em> demonstrate a rich history of metallurgy (focusing on gold, silver, copper, and iron), osmium is notably absent. It requires modern, advanced chemical techniques to extract. Today, osmium plays a role in modern Indian scientific research, such as studying river sediments in the Ganga to understand ancient geological changes, and it is catching the eye of the growing Indian luxury market as a rare, investment-grade precious metal.</p>'''

data['sections']['Characteristics'] = "Osmium is part of the platinum group of metals. It is incredibly hard, brittle, and remains shiny even at very high temperatures. Because it's so dense and tough, it's very difficult to shape or mold. However, these same properties make it an excellent addition to alloys when you need something that won't wear down easily."

data['sections']['Physical properties'] = "Osmium holds the title for the densest naturally occurring element—it's about twice as heavy as lead! It has a striking bluish-silver appearance and an incredibly high melting point of over 3,000°C (5,400°F). Because it is so hard and brittle, it can easily shatter if struck hard enough, making it challenging to work with in its pure form."

data['sections']['Chemical properties'] = "When osmium is exposed to the air, especially in a powdered form, it slowly reacts with oxygen to form osmium tetroxide. This compound has a very strong, sharp smell—in fact, the name 'osmium' comes from the Greek word 'osme', meaning 'odor'. Osmium tetroxide is highly toxic and can cause damage to the eyes, skin, and lungs, which is why scientists handle this element with extreme care."

data['sections']['Isotopes'] = "Osmium exists in nature as a mix of seven different versions, called isotopes. Six of these are completely stable, while one is very slightly radioactive, though it breaks down so slowly that it takes billions of years. In India, geologists have analyzed osmium isotopes in the sediments of the mighty Ganga river. By studying these tiny traces, Indian scientists can learn about how the Earth's continents have weathered and changed over millions of years."

data['sections']['History'] = "Osmium was discovered in 1803 by Smithson Tennant in London. He found it alongside another metal, iridium, in the leftover residue when platinum was dissolved in powerful acids. Although ancient Indian metallurgy (Rasashastra) was highly advanced, extracting osmium required chemical techniques that were not yet invented. Thus, there are no mentions of osmium in ancient Indian texts. Today, however, osmium is gaining recognition in India's modern technological and luxury sectors."

data['sections']['Occurrence'] = "Osmium is one of the rarest elements in the Earth's crust. It is typically found mixed with other precious metals like platinum and iridium in natural river sands and specific rock formations. While India does not have major primary mines for osmium, the metal is sourced globally and brought into the country for specialized scientific and industrial uses. Most of the world's osmium comes from large mining operations in South Africa, Russia, and North America."

data['sections']['Applications'] = "Because pure osmium is so hard and brittle, it is usually mixed with other metals to create super-durable alloys. In the past, it was famous for being used in the tough tips of fountain pens and phonograph needles. Today, osmium is used in high-wear electrical contacts and specialized scientific equipment. In modern Indian research labs, osmium compounds like osmium tetroxide are crucial for preparing biological samples for electron microscopes, allowing scientists to see the tiny structures of cells with incredible contrast."

os.makedirs(r'c:\projects\apache\school1\src\ptable\data\drafts', exist_ok=True)
with open(r'c:\projects\apache\school1\src\ptable\data\drafts\Osmium.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=4)

print('Done')
