import json
import os

with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    master = json.load(f)

yttrium_data = master.get('Y', {})

yttrium_data['extract_html'] = '<p><b><a href="/school1/ptable/element/Y" class="wiki-link" title="Yttrium (Y)">Yttrium</a></b> is a silvery-white transition metal that is often grouped with rare-earth elements. It was first discovered in a quarry in Ytterby, Sweden, in 1787. Even though it is called a "rare earth" element, it is actually quite abundant in the Earth\'s crust! In modern India, yttrium plays a crucial role in advancing clean energy and new technologies. Scientists at Indian institutions like the Centre for Nano and Soft Matter Sciences (CeNS) are exploring yttrium compounds to build high-performance supercapacitors and advanced energy storage systems. India also holds significant deposits of rare-earth minerals like monazite and xenotime, found in coastal beach sands across Kerala, Tamil Nadu, and Odisha, making it an important player in the future of critical minerals.</p>'

sections = yttrium_data.get('sections', {})

sections['Characteristics'] = '<p>Yttrium is a soft, silver-metallic element that is highly crystalline. In its pure form, it is relatively stable in air because it forms a protective oxide layer on its surface, much like aluminum. If you turn it into fine turnings or powder, however, it can easily ignite! It is chemically very similar to the lanthanides (the top row of elements at the bottom of the periodic table) and is almost always found alongside them in nature. One of its most famous characteristics is its use in creating high-temperature superconductors, which can carry electricity with zero resistance at temperatures above the boiling point of liquid nitrogen.</p>'

sections['History'] = '<p>The story of yttrium begins in 1787, when a part-time chemist named Carl Axel Arrhenius found an unusual black rock in a quarry near the Swedish village of Ytterby. He sent it to various chemists, and in 1789, Johan Gadolin identified a new "earth" (oxide) within it, which was later named yttria. This rock eventually led to the discovery of several new elements! The pure metal was finally isolated by Friedrich Wöhler in 1828.</p>\n<p>While yttrium doesn\'t have an ancient history in India, it is becoming a vital part of the nation\'s modern scientific journey. With the launch of initiatives like the National Critical Mineral Mission, India is increasingly focusing on elements like yttrium to build a self-reliant supply chain for aerospace, defense, and renewable energy sectors. Today, researchers in India are using green chemistry to synthesize yttrium nanoparticles for antibacterial applications and quantum research.</p>'

sections['Occurrence'] = '<p>Yttrium is found in most rare-earth minerals and uranium ores, but it is never found in nature as a free element. Interestingly, lunar rocks brought back by the Apollo missions contain high levels of yttrium! On Earth, it is commercially extracted from minerals like monazite and xenotime.</p>\n<p>In India, yttrium is geologically significant. The country has vast reserves of monazite-bearing beach sands along the coasts of Kerala, Tamil Nadu, Odisha, Andhra Pradesh, Maharashtra, and Gujarat. Additionally, xenotime, a phosphate mineral rich in yttrium, is found in the riverine placer deposits of Chhattisgarh and Jharkhand. Through exploratory projects like SHORE (Shallow Subsurface Imaging of India for Resource Exploration), scientists at the CSIR-National Geophysical Research Institute continue to map and discover these vital rare-earth deposits across the Indian subcontinent.</p>'

sections['Applications'] = '<p>Yttrium has many fascinating applications! Its most important use is in making phosphors, which are materials that emit light. These are used in LEDs and were crucial for creating the red color in older cathode-ray tube (CRT) televisions. Yttrium is also a key ingredient in YAG (yttrium aluminum garnet) lasers, which are used in everything from medical surgeries to metal cutting.</p>\n<p>It is added to alloys to increase their strength and is used in spark plugs and microwave filters. Recently, Indian scientists have been harnessing yttrium\'s unique properties to develop advanced energy storage solutions, such as supercapacitors, and exploring its potential in next-generation quantum and spintronic devices. Because of its strategic importance, yttrium is a critical building block for the technologies of tomorrow.</p>'

yttrium_data['sections'] = sections

os.makedirs('src/ptable/data/drafts', exist_ok=True)
with open('src/ptable/data/drafts/Yttrium.json', 'w', encoding='utf-8') as f:
    json.dump(yttrium_data, f, indent=4)

print("Rewrite completed successfully!")
