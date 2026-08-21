import json

with open('src/ptable/data/drafts/Lead.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

data['extract_html'] = '<p><b><a href=\"/school1/ptable/element/Pb\" class=\"wiki-link\" title=\"Lead (Pb)\">Lead</a></b> is a heavy, soft, and easily shaped metal with a dull gray appearance when exposed to air. Sitting at atomic number 82, it has the highest atomic number of any stable element. Although it is relatively unreactive, lead is highly valued for its high density, low melting point, and resistance to corrosion. Because it is so easy to extract from its ores, humans have used it for thousands of years. In ancient India, lead was known during the Vedic period and later held a special place in Ayurvedic medicine and alchemy (Rasa Shastra). Through a rigorous purification process, raw toxic lead was transformed into <i>Naga Bhasma</i>, an ash-like preparation used in traditional remedies. Today, while we still use lead in batteries and radiation shielding, we are much more aware of its toxicity, as it can be harmful to the human nervous system and organs.</p>'

sections = data.get('sections', {})

sections['Physical properties'] = '<p>Lead is a heavy, dense metal that feels surprisingly soft—you can even scratch it with your fingernail! When freshly cut, it shines with a bluish-silver gleam, but it quickly turns a dull gray as it reacts with the air. It has a relatively low melting point, which made it incredibly easy for ancient metallurgists to work with and mold into various shapes. It does not conduct electricity as well as metals like copper or silver, but it is highly resistant to corrosion. Because of its density, it acts as an excellent shield against sound and radiation.</p>'

sections['Isotopes'] = '<p>In nature, lead exists as a mix of four stable isotopes (different versions of the same element with varying atomic weights). Interestingly, three of these natural isotopes are the final, stable endpoints of the radioactive decay chains of heavier elements like uranium and thorium. This means that over billions of years, much of the universe’s radioactive elements slowly decay and eventually turn into lead! Because of this unique property, scientists often use the ratio of different lead isotopes in rocks to determine the age of the Earth and even date ancient artifacts.</p>'

sections['History'] = '<p>Lead’s story spans millennia. Its principal ore, galena, usually contains silver, which sparked widespread mining in ancient times, particularly during the Roman Empire where it was used for plumbing (giving us the word \"plumber\" from the Latin <i>plumbum</i>). However, India also shares a rich history with lead. Texts from the Vedic period mention the metal, and it was deeply integrated into ancient Indian alchemy and Ayurvedic medicine. Through a complex, fiery purification process known as <i>Rasa Shastra</i>, Indian healers neutralized the metal’s toxicity to create <i>Naga Bhasma</i>, an herbo-metallic powder historically used to treat ailments like diabetes and digestive issues. During the Industrial Revolution, lead production soared globally, eventually leading to its use in paints and gasoline—before its severe health hazards were widely recognized.</p>'

sections['Applications'] = '<p>Today, the world produces millions of tons of lead each year, heavily relying on recycling. By far, its most common use is in lead-acid batteries, which are essential for starting everyday cars and providing backup power for computer networks. Because it blocks radiation so effectively, lead is also used to line the protective aprons you wear during dental X-rays and to shield nuclear reactors. It has historical uses in construction, bullets, weights, and solders. However, due to its well-documented toxicity—causing neurological and developmental harm—many everyday uses of lead, such as in paint, pipes, and gasoline, have been strictly phased out to protect public health.</p>'

data['sections'] = sections

with open('src/ptable/data/drafts/Lead.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=4)
