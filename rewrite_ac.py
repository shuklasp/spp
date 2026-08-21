import json
import os

with open('C:/projects/apache/school1/src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

ac = data['Ac']

ac['extract_html'] = """<p><b>Actinium</b> is a chemical element with the symbol <b>Ac</b> and atomic number 89. It is a rare, silvery-white, highly radioactive metal that glows with an eerie blue light in the dark due to its intense radioactivity. Actinium is part of the actinide series, a group of elements on the periodic table that share similar properties. While it has no specific historical or ancient connection to India, the element plays an important role in modern scientific and medical research globally. Today, Indian scientific institutions, as part of their growing nuclear medicine programs, are increasingly involved in exploring the use of actinium isotopes (like Actinium-225) for targeted cancer therapies.</p>"""

ac['sections'] = [
    {
        "title": "History",
        "text": "<p>Actinium was discovered in 1899 by the French chemist André-Louis Debierne, who separated it from pitchblende (a uranium-rich mineral). It was independently discovered in 1902 by the German chemist Friedrich Oskar Giesel. The name \"actinium\" comes from the ancient Greek word <i>aktis</i> or <i>aktinos</i>, meaning \"beam\" or \"ray,\" which reflects its highly radioactive nature. Because actinium is extremely rare and difficult to extract from natural ores, it is not mined in the traditional sense and has no local geographical relevance or ancient history in India. Instead, it is typically produced in laboratories and nuclear reactors.</p>"
    },
    {
        "title": "Isotopes",
        "text": "<p>Actinium has no stable isotopes, meaning all forms of actinium are radioactive and decay over time. The most stable isotope is actinium-227, which has a half-life of about 21.77 years. This isotope occurs naturally in trace amounts as part of the decay chain of uranium-235. Another important isotope is actinium-225, which has a much shorter half-life of 10 days. Actinium-225 is of particular interest in modern medicine because it emits alpha particles, which can be harnessed to destroy cancer cells.</p>"
    },
    {
        "title": "Applications",
        "text": "<p>Due to its scarcity, high cost, and intense radioactivity, actinium does not have wide commercial applications outside of specialized scientific research and medicine. Historically, actinium-227 was studied for use in radioisotope thermoelectric generators (devices that convert heat from radioactive decay into electricity) for spacecraft, but it was largely replaced by other elements. Today, the most significant application of actinium is in medicine. Actinium-225 is used in Targeted Alpha Therapy (TAT), a cutting-edge cancer treatment. In this therapy, the radioactive actinium is attached to a molecule that specifically targets and attaches to cancer cells, delivering a lethal dose of radiation directly to the tumor while sparing surrounding healthy tissue. Modern Indian scientific contributions in the field of nuclear medicine are increasingly focusing on the clinical adoption and research of such radiopharmaceuticals to improve cancer care.</p>"
    }
]

os.makedirs('C:/projects/apache/school1/src/ptable/data/drafts', exist_ok=True)
with open('C:/projects/apache/school1/src/ptable/data/drafts/Actinium.json', 'w', encoding='utf-8') as f:
    json.dump({"Ac": ac}, f, indent=4, ensure_ascii=False)

print("Saved to Actinium.json")
