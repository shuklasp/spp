import json
import os

with open('C:/projects/apache/school1/src/ptable/data/cn_extract.json', encoding='utf-8') as f:
    data = json.load(f)

cn = data['Cn']

cn['extract_html'] = '''<p><b>Copernicium (Cn)</b> is a fascinating, human-made chemical element with the atomic number 112. Because it is highly radioactive and extremely unstable, you won't find it anywhere in nature; it can only be created in highly advanced laboratories. The most stable version of copernicium (its longest-lasting isotope, copernicium-285) survives for just about 30 seconds before breaking apart into lighter elements.</p>
<p>First created in February 1996 by an international team at the GSI Helmholtz Centre for Heavy Ion Research in Germany, it was later named in honor of the famous astronomer Nicolaus Copernicus. Interestingly, modern Indian scientific institutions have also played a crucial role in understanding this elusive element. Researchers from India's Bhabha Atomic Research Centre (BARC) and the Tata Institute of Fundamental Research (TIFR) have contributed significantly to the theoretical physics and chemistry behind superheavy elements. Through international partnerships like the Facility for Antiproton and Ion Research (FAIR) in Germany, Indian scientists help model the complex nuclear reactions and electronic properties that dictate how elements like copernicium behave.</p>
<p>Sitting in the d-block of the periodic table, copernicium is part of group 12 alongside familiar metals like zinc, cadmium, and mercury. However, owing to powerful "relativistic effects" inside its massive atoms, copernicium acts quite differently from its lighter cousins. In fact, experiments suggest it is an extremely volatile substance—it might even be a gas or a highly volatile liquid at room temperature, behaving more like a noble gas (such as radon) than a typical metal. It represents one of the heaviest elements whose chemical properties scientists have actually managed to test in real-life experiments.</p>'''

cn['sections']['History'] = '''<div class="mw-heading mw-heading3"><h3>Discovery and Naming</h3></div>
<p>The story of copernicium began on February 9, 1996, at the GSI Helmholtz Centre for Heavy Ion Research in Darmstadt, Germany. A dedicated team led by Sigurd Hofmann fired a beam of zinc atoms at a target made of lead. This high-energy collision fused the two nuclei together, creating a single atom of the brand-new element 112.</p>
<p>To celebrate the great Renaissance astronomer Nicolaus Copernicus—who famously proposed that the Earth revolves around the Sun—the element was officially named <b>copernicium</b> on what would have been his 537th birthday in 2010. While the element was born in a German lab, decoding its secrets has been a global effort. Today, top Indian research centers such as the Bhabha Atomic Research Centre (BARC) and the Tata Institute of Fundamental Research (TIFR) continue this legacy. Their theoretical studies into the shell effects and nuclear fission of superheavy elements provide critical blueprints for understanding how these massive atoms are held together. Through ongoing collaborations with facilities like FAIR in Germany, India's scientific community is at the forefront of exploring the extreme limits of the periodic table.</p>'''

cn['sections']['Isotopes'] = '''<div class="mw-heading mw-heading3"><h3>Isotopes of Copernicium</h3></div>
<p>Copernicium has no stable isotopes, meaning every version of it is radioactive and fleeting. Scientists have successfully created various isotopes—atoms with the same number of protons (112) but different numbers of neutrons. So far, isotopes with mass numbers ranging from 277 up to 286 have been reported.</p>
<p>Because they are so heavy and unstable, these isotopes decay very rapidly, usually by emitting alpha particles or through spontaneous fission (splitting into smaller atoms). While earlier isotopes lived for mere milliseconds, the heaviest one, copernicium-285, manages to stick around for about 30 seconds. In the fast-paced world of superheavy elements, a 30-second lifespan is actually quite long! Studying these decays is like reading a trail of breadcrumbs; for example, observing the decay of copernicium-283 was actually key to confirming the existence of two other even heavier elements, flerovium and livermorium.</p>
<p>Mapping out these short-lived isotopes relies heavily on complex theoretical physics. Indian theoretical physicists at BARC have made vital contributions here by helping predict the nuclear stability and decay patterns of superheavy isotopes, guiding experimentalists globally on what to look for when they smash atoms together.</p>'''

# Ensure we overwrite only the sections we rewrote, but wait, the instructions said:
# "Rewrite the extract_html and all sections into simple, engaging, accessible language for non-technical readers."
# The original sections were just History and Isotopes. I overwrote both.

out_data = {'Cn': cn}

os.makedirs('C:/projects/apache/school1/src/ptable/data/drafts', exist_ok=True)
with open('C:/projects/apache/school1/src/ptable/data/drafts/Copernicium.json', 'w', encoding='utf-8') as f:
    json.dump(out_data, f, indent=2)

print('Successfully saved to drafts/Copernicium.json')
