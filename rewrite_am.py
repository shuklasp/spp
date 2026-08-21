import json
import os

input_file = 'src/ptable/data/master_elements.json'
output_dir = 'src/ptable/data/drafts'
output_file = os.path.join(output_dir, 'Americium.json')

os.makedirs(output_dir, exist_ok=True)

with open(input_file, 'r', encoding='utf-8') as f:
    data = json.load(f)

am_data = data['Am']

extract_html = '''<p><strong>Americium</strong> (symbol <strong>Am</strong>, atomic number 95) is a fascinating, human-made radioactive metal that you might actually have in your own home! Positioned in the actinide series of the periodic table, it was named after the Americas, just as its twin element europium was named after Europe. Although it is born from complex nuclear reactions, Americium is most famous for saving lives as the active ingredient in smoke detectors. It is a silvery, relatively soft metal that usually takes a +3 oxidation state in chemical compounds. Because of its radioactivity, the metal slowly damages its own crystal structure over time (a process called self-irradiation), meaning its physical properties can slowly drift as it ages.</p>
<p>In India, institutions like the <strong>Bhabha Atomic Research Centre (BARC)</strong> have played a monumental role in harnessing Americium for practical and safe use, proving its value far beyond a mere laboratory curiosity and driving self-reliance in nuclear technology.</p>'''

history = '''<p>The story of Americium begins in late 1944. It was synthesized at the University of California, Berkeley, by Glenn T. Seaborg, Leon O. Morgan, Ralph A. James, and Albert Ghiorso during the top-secret Manhattan Project. By bombarding plutonium with neutrons, they created this brand-new element. The process of separating Americium from another new element, Curium, was so painfully difficult that the scientists jokingly nicknamed them "delirium" and "pandemonium"!</p>
<p>Because the work was highly classified, the discovery was kept secret until after World War II. Amusingly, Seaborg accidentally leaked the discovery on a children's radio show called <em>Quiz Kids</em> just days before the official announcement in November 1945.</p>
<p><strong>Indian Historical Context:</strong> In the decades following its discovery, India’s nuclear program, pioneered by Dr. Homi J. Bhabha, placed a strong emphasis on mastering the entire nuclear fuel cycle. By the 1970s and 1990s, scientists at the Bhabha Atomic Research Centre (BARC) in Trombay had developed sophisticated indigenous techniques to handle, isolate, and purify Americium. Their pioneering radiochemistry work allowed India to safely manufacture its own Americium-based radiation sources, significantly reducing reliance on foreign imports and demonstrating the nation's growing scientific independence.</p>'''

occurrence = '''<p>Since the longest-lived isotopes of Americium (Am-243 and Am-241) have half-lives of thousands or hundreds of years, any primordial Americium that might have existed when Earth was formed has long since decayed away. Today, it is almost entirely synthetic.</p>
<p>In the environment, trace amounts of Americium can be found at the sites of historic atmospheric nuclear weapons tests (like the 1952 Ivy Mike test) or nuclear incidents such as the Chernobyl disaster. When it is in the environment, it tends to stick very tightly to soil particles rather than dissolving into groundwater.</p>
<p><strong>Indian Geographical & Environmental Relevance:</strong> Americium is a minor but notable byproduct of nuclear power generation. A single tonne of spent nuclear fuel can contain about 100 grams of Americium. In India, the handling, storage, and environmental monitoring of such radioactive materials are strictly overseen by the <strong>Atomic Energy Regulatory Board (AERB)</strong>. To ensure long-term environmental safety, modern Indian researchers have focused heavily on extracting and managing Americium from spent fuel, turning a challenging waste management issue into a testament to safe, sustainable nuclear practices.</p>'''

isotopes = '''<p>Americium has 19 known isotopes (variants of the element with different weights), ranging in mass from 229 to 247. The two most important and stable are <strong>Americium-241</strong> (with a half-life of about 432 years) and <strong>Americium-243</strong> (with a half-life of 7,350 years).</p>
<p>As Americium-241 decays into Neptunium, it releases a steady stream of alpha particles and some gamma rays. It is precisely this predictable, steady release of alpha particles that makes it so incredibly useful in everyday technology. Another interesting form is Americium-242m, a long-lived nuclear isomer (half-life of 141 years) that has such unique properties it has been proposed as a futuristic, highly efficient fuel for nuclear-powered space travel!</p>'''

applications = '''<p>Americium's biggest claim to fame is its use in everyday commercial ionization chamber <strong>smoke detectors</strong>. A tiny speck of Americium-241 (usually less than a microgram) emits alpha particles that ionize the air inside the detector, creating a small electric current. If smoke particles enter the chamber, they disrupt this current, which instantly triggers the alarm and saves lives. Beyond the home, it is used as a reliable neutron source in industrial gauges to precisely measure the thickness of glass, the density of soil, or even to help map oil wells.</p>
<p><strong>Modern Indian Scientific Contributions:</strong> Indian scientists have made significant strides in the practical applications and management of Americium. In the 1990s, researchers at BARC developed advanced methods to electrodeposit Americium-241 onto silver backings and encapsulate them with ultra-thin gold layers. This breakthrough led to completely indigenous, safe, and effective alpha-radiation sources for India's own smoke detectors.</p>
<p>Furthermore, in the realm of nuclear waste management, Indian scientists have recently designed cutting-edge chemical extractants (such as diglycolamide resins) and innovative nanomaterials (like nano-cerium vanadate ion exchangers) designed specifically to selectively pull Americium out of acidic nuclear waste. These modern contributions highlight India's continued leadership in clean, responsible radiochemistry.</p>'''

am_data['extract_html'] = extract_html
am_data['sections'] = {
    'History': history,
    'Occurrence': occurrence,
    'Isotopes': isotopes,
    'Applications': applications
}

with open(output_file, 'w', encoding='utf-8') as f:
    json.dump({'Am': am_data}, f, indent=4, ensure_ascii=False)

print('Success')
