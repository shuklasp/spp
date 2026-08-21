import json
import os

with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    master_data = json.load(f)

bh_data = master_data['Bh']

new_extract = """
<p><b>Bohrium</b> (Bh), with the atomic number 107, is a fascinating synthetic element that you won't find anywhere in nature. It is highly radioactive and exists only for a fraction of a second after being created in a laboratory. It was named after the famous Danish physicist Niels Bohr.</p>

<p>While Bohrium is a modern scientific creation, its namesake Niels Bohr has a surprisingly deep connection to <b>India</b>. During his visit to India in 1960, Bohr interacted with prominent Indian scientists like P.C. Mahalanobis and explored profound ancient Indian philosophical concepts. Bohr was fascinated by the Upanishads and the ancient Indian idea of duality, which he found closely mirrored his own pioneering principle of complementarity in quantum physics!</p>
"""

new_history = """
<h3>The Story of Bohrium</h3>
<p>Bohrium was first synthesized in 1981 by a team of scientists led by Peter Armbruster and Gottfried Münzenberg at the GSI Helmholtz Centre for Heavy Ion Research in Darmstadt, Germany. By bombarding a target of Bismuth-209 with Chromium-54 nuclei, they were able to create the very first atoms of this superheavy element.</p>
<p>The naming of the element sparked some debate. The team proposed the name <i>nielsbohrium</i> in honor of Niels Bohr. IUPAC eventually decided to shorten it to simply <i>bohrium</i> in 1997.</p>
<h3>Indian Scientific Connections</h3>
<p>Interestingly, the GSI Helmholtz Centre, where Bohrium was discovered, is a major hub for international scientific collaboration, with a very strong presence of <b>Indian scientists</b>. Researchers from premier Indian institutions, such as the Bose Institute in Kolkata, actively participate in cutting-edge nuclear research at GSI, helping to uncover the secrets of superheavy elements like Bohrium.</p>
<p>Additionally, Niels Bohr himself was profoundly influenced by <b>ancient Indian texts</b>. He famously stated, "I go into the Upanishads to ask questions." The ancient Indian philosophical texts helped him frame his thoughts on quantum mechanics, particularly the idea that opposite realities can coexist—a core tenet of both Eastern philosophy and atomic physics, directly linking the intellectual heritage of India to the namesake of element 107.</p>
"""

new_isotopes = """
<h3>Isotopes</h3>
<p>Because Bohrium is a synthetic element, it has no stable isotopes. Every version of Bohrium ever created is highly radioactive and decays incredibly quickly. The most stable isotope known is Bohrium-270, which has a half-life of about 61 seconds. That might sound short, but in the world of superheavy elements, it's an eternity!</p>
<p>Due to its fleeting existence and the intense difficulty in producing it, Bohrium has no practical uses outside of scientific research. Scientists study its isotopes purely to understand the fundamental laws of physics and the outer limits of the periodic table.</p>
"""

bh_data['extract_html'] = new_extract.strip()
bh_data['sections']['History'] = new_history.strip()
bh_data['sections']['Isotopes'] = new_isotopes.strip()

os.makedirs('src/ptable/data/drafts', exist_ok=True)
with open('src/ptable/data/drafts/Bohrium.json', 'w', encoding='utf-8') as f:
    json.dump(bh_data, f, indent=2)

print('Success')
