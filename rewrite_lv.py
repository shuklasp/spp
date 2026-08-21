import json

with open('lv.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

html = data['extract_html']
idx = html.find('<p class="mw-empty-elt">')
if idx == -1:
    idx = html.find('<p>')

infobox = html[:idx]

new_extract = """<p><b>Livermorium</b> (symbol <b>Lv</b>, atomic number 116) is a superheavy, highly radioactive element created entirely in laboratories. It does not exist in nature, and as of 2020, only about 35 atoms of livermorium had ever been produced.</p>

<p>First discovered during experiments from 2000 to 2006, livermorium was synthesized through a joint effort between the Joint Institute for Nuclear Research (JINR) in Dubna, Russia, and the Lawrence Livermore National Laboratory (LLNL) in California, United States. It was officially added to the periodic table in 2011 and named Livermorium in 2012, honoring the LLNL and the city of Livermore.</p>

<p>In the periodic table, livermorium belongs to the p-block and sits in group 16, just below polonium. Due to its incredibly short lifespan—its most stable known isotope, livermorium-293, has a half-life of just 80 milliseconds—studying its chemistry is very difficult. However, scientists predict it might share some traits with lighter elements in its group like oxygen, sulfur, and selenium, while also displaying unique properties as a heavy metal.</p>

<p>While Indian scientists were not directly involved in the synthesis of livermorium, India maintains a strong presence in global nuclear physics research. India's active participation in international collaborations—such as the ALICE experiment at CERN and partnerships with the Joint Institute for Nuclear Research—helps lay the foundational scientific groundwork for understanding the behavior of superheavy elements like livermorium.</p>"""

new_history = """<p>Livermorium's discovery was the result of intense international collaboration in the field of nuclear physics. Between 2000 and 2006, scientists from the Joint Institute for Nuclear Research (JINR) in Dubna, Russia, and the Lawrence Livermore National Laboratory in the United States conducted a series of complex experiments. By smashing a beam of calcium ions into a radioactive curium target, they successfully produced the very first atoms of element 116.</p>

<p>The International Union of Pure and Applied Chemistry (IUPAC) formally recognized the new element's discovery in 2011. The following year, it was officially named <b>livermorium</b> (Lv) in honor of the Lawrence Livermore National Laboratory and its home city of Livermore, California.</p>

<p>The creation of superheavy elements relies on decades of theoretical and experimental physics from around the world. Although the primary discovery of livermorium was an American-Russian achievement, modern nuclear research is deeply interconnected. For instance, Indian physicists and researchers frequently collaborate with institutions like JINR and CERN on large-scale nuclear experiments. These international partnerships, supported by Indian scientific institutions, contribute to the broader understanding of particle physics that makes pushing the boundaries of the periodic table possible.</p>"""

data['extract_html'] = infobox + new_extract
data['sections'] = {"History": new_history}

import os
os.makedirs('src/ptable/data/drafts', exist_ok=True)
with open('src/ptable/data/drafts/Livermorium.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2)

print("Done")
