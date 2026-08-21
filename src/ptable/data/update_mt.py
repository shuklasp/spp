import json
import os

os.makedirs('c:/projects/apache/school1/src/ptable/data/drafts', exist_ok=True)

with open('c:/projects/apache/school1/src/ptable/data/mt_data.json', encoding='utf-8') as f:
    d = json.load(f)

d['extract_html'] = """<p><strong>Meitnerium (Mt)</strong> is a highly radioactive, synthetic chemical element with the atomic number 109. You won't find it anywhere in nature; it can only be created in a highly advanced laboratory! Because of its extreme radioactivity and incredibly short lifespan—its most stable known isotope lasts just about 4.5 seconds—meitnerium has no practical or commercial applications today.</p>
<p>Instead, its value lies entirely in scientific research. By studying meitnerium, scientists can test complex quantum mechanical models and map the extreme limits of the periodic table. While the element itself doesn't have a direct Indian context, the groundbreaking techniques and high-resolution detectors developed to study such superheavy elements often find secondary applications worldwide in fields like medical imaging and materials testing.</p>"""

d['sections']['History'] = """<p>Meitnerium was first created on August 29, 1982, by a dedicated research team led by physicists Peter Armbruster and Gottfried Münzenberg at the GSI Helmholtz Centre for Heavy Ion Research in Darmstadt, Germany. To make it, they bombarded a target of bismuth-209 with accelerated iron-58 nuclei. Remarkably, this massive atomic collision produced just a <em>single</em> atom of meitnerium-266!</p>
<p>In 1997, the International Union of Pure and Applied Chemistry (IUPAC) officially named the element in honor of <strong>Lise Meitner</strong>, a brilliant Austrian-Swedish physicist who played a pivotal role in the discovery of nuclear fission. Naming element 109 after her ensured her monumental contributions to science were forever immortalized on the periodic table.</p>"""

d['sections']['Isotopes'] = """<p>Because meitnerium is synthetic and highly unstable, it has no stable or naturally occurring isotopes. Scientists have managed to create a few radioactive isotopes in the lab, either by fusing lighter atoms together or by observing the radioactive decay of even heavier elements.</p>
<p>Currently, there are eight confirmed isotopes of meitnerium, with mass numbers ranging between 266 and 278. <strong>Meitnerium-278</strong> is the most stable of the bunch, but even it only sticks around for a fleeting 4.5 seconds before it breaks down, mostly through a process called alpha decay. There are also hints of a heavier isotope, meitnerium-282, which might survive for about 67 seconds—an eternity in the world of superheavy elements—but its existence remains unconfirmed.</p>"""

with open('c:/projects/apache/school1/src/ptable/data/drafts/Meitnerium.json', 'w', encoding='utf-8') as f:
    json.dump(d, f, indent=2)
