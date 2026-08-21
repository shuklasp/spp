import json
import os

source_file = 'src/ptable/data/master_elements.json'
draft_dir = 'src/ptable/data/drafts'
draft_file = os.path.join(draft_dir, 'Xenon.json')

with open(source_file, 'r', encoding='utf-8') as f:
    data = json.load(f)

xe = data['Xe']

xe['extract_html'] = "<p><b>Xenon</b> is a chemical element with the symbol <b>Xe</b> and atomic number 54. It is a colorless, dense, odorless noble gas found in Earth's atmosphere in trace amounts. Although generally unreactive, xenon can undergo a few chemical reactions such as the formation of xenon hexafluoroplatinate, the first noble gas compound to be synthesized. Known for its beautiful blue glow in electrical discharge tubes, xenon has many modern applications, from lighting to space exploration.</p>"

xe['sections']['History'] = "<p>Xenon was discovered in 1898 by Scottish chemist Sir William Ramsay and his assistant, Morris Travers. They found it in the leftover residue after evaporating liquid air. Its name comes from the Greek word <i>xénos</i>, which means 'stranger' or 'foreigner'—a fitting name for an element that was hiding as a rare guest in the air!</p><p>Interestingly, there is a strong historical connection between the discovery of xenon and Indian science. Morris Travers, the co-discoverer of xenon, later traveled to India and became the founding Director of the prestigious <b>Indian Institute of Science (IISc)</b> in Bangalore in 1909. His foundational work helped establish one of India's most important scientific research institutions.</p>"

xe['sections']['Characteristics'] = "<p>Xenon is a noble gas, meaning it sits in the far-right column of the periodic table. Because its outer electron shell is completely full, xenon is generally very stable and prefers to keep to itself rather than react with other elements. It is colorless, odorless, and heavy. In fact, if you could fill a balloon with xenon, it would drop to the floor like a rock because it is about 4.5 times heavier than Earth's air!</p><p>When an electrical current is passed through xenon gas, it emits a brilliant, glowing blue light. For a long time, scientists thought xenon couldn't form chemical compounds at all. However, in 1962, chemists proved that xenon could indeed react with highly reactive elements like fluorine and oxygen, breaking the long-held belief that noble gases were completely inert.</p>"

xe['sections']['Isotopes'] = "<p>Xenon exists in nature as a mix of several different forms, called isotopes. There are seven stable isotopes of xenon, and a couple of others that are very slightly radioactive but have incredibly long lifespans.</p><p>Scientists use xenon isotopes like atomic clocks to study the history of our solar system. By looking at the types of xenon found in meteorites, researchers can learn about the formation of the Earth and the early solar system. In modern research, Indian scientists have also tracked radioactive xenon and krypton isotopes in atmospheric monitoring—for instance, measuring traces of these gases in Jodhpur, India, following global nuclear events to study atmospheric transport.</p>"

xe['sections']['Applications'] = "<p>Because of its unique properties, xenon is used in many incredible ways! Its brilliant light is used in high-intensity photographic flashes, IMAX movie projectors, and even high-end car headlights.</p><p>In medicine, xenon is a highly effective, though expensive, anesthetic that is safe for the human body and helps protect the brain and heart during surgeries.</p><p>One of the most futuristic uses of xenon is in space exploration. Xenon is the preferred fuel for 'ion thrusters'—advanced engines that push spacecraft through the vacuum of space. The <b>Indian Space Research Organisation (ISRO)</b> actively uses xenon-based stationary plasma thrusters. For example, ISRO's GSAT-9 (South Asia Satellite) utilized a xenon electric propulsion system for maintaining its orbit, making satellites much lighter and more efficient by reducing the need for heavy chemical fuels. Additionally, Indian researchers have utilized xenon in specialized balloon-borne hard X-ray astronomy projects, continuing to prove that this rare 'stranger' gas is essential for exploring the universe!</p>"

os.makedirs(draft_dir, exist_ok=True)
with open(draft_file, 'w', encoding='utf-8') as f:
    json.dump(xe, f, indent=2)

print('Draft saved successfully to', draft_file)
