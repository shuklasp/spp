import json
import os

with open('c:/projects/apache/school1/pa_data.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# The data is wrapped in a {"Pa": {...}} structure.
pa = data['Pa']

extract_text = """Protactinium is a very rare, highly radioactive, and silvery-gray metal. In nature, it exists in only tiny amounts, mostly as a byproduct of uranium slowly decaying over time. It is highly toxic and very dense. Because it is so scarce and dangerous to handle, protactinium has almost no uses outside of scientific research. However, it holds a special place in nuclear physics—particularly in India. 

India is home to some of the world's largest reserves of thorium, found in the monazite sands of coastal states like Kerala. Decades ago, Dr. Homi J. Bhabha, the visionary architect of India’s nuclear program, designed a unique three-stage nuclear power strategy to harness this thorium. In this process, protactinium acts as the crucial "bridge." When thorium absorbs neutrons in a reactor, it transforms first into short-lived protactinium-233, which then decays into uranium-233—the actual fuel that can sustain a nuclear chain reaction. Thus, this rare element is an essential stepping stone in India’s quest for long-term energy independence."""

history_text = """Before it was even discovered, the famous chemist Dmitri Mendeleev knew something was missing. In 1871, he left a blank space in his periodic table between thorium and uranium, predicting that an unknown element belonged there. For decades, scientists searched in the wrong places, expecting the mystery element to behave like tantalum. 

It wasn't until 1913 that physicists Kasimir Fajans and Oswald Helmuth Göhring first spotted it while studying how uranium breaks down. Because the version (or isotope) they found vanished in just over a minute, they named it "brevium" (from the Latin word for brief). A few years later, between 1917 and 1918, two separate teams—one featuring Lise Meitner and Otto Hahn in Germany, and another in Great Britain—discovered a much longer-lasting version of the element. Meitner renamed it "protactinium," meaning the "parent of actinium," since it eventually turns into the element actinium as it releases radiation.

By 1961, scientists in the UK managed to produce just 127 grams of pure protactinium from 60 tons of nuclear waste. This tiny, half-million-dollar batch was the world's only major supply for years!"""

isotopes_text = """Atoms of the same element can have different weights, called isotopes. Protactinium has 30 known radioisotopes, meaning all of them are radioactive and unstable. The most stable one is protactinium-231, which takes about 32,760 years for half of it to decay (its "half-life"). Another important one is protactinium-233, which lasts about 27 days. Most of the other isotopes vanish in a matter of days, minutes, or even seconds!

As these isotopes break down, they release energy and particles, transforming into different elements. Lighter isotopes of protactinium mostly turn into actinium, while heavier ones turn into uranium. In India's advanced thorium-based nuclear reactors, protactinium-233 is a vital intermediate step. Engineers must carefully manage it, because waiting for it to decay into uranium-233 is the key to unlocking the energy stored in thorium."""

occurrence_text = """Protactinium is one of the rarest and most expensive naturally occurring elements on Earth. It is almost never found on its own. Instead, it is locked inside uranium ores like pitchblende. For every million parts of ore, you might find only about 3 parts of protactinium! 

You can find trace amounts of protactinium almost everywhere—in water and soil—but in unimaginably small quantities (about one part per trillion). It tends to stick to soil and clay rather than dissolving in water. Nearly all natural protactinium is the isotope protactinium-231, created when uranium-235 slowly decays deep underground."""

applications_text = """Because protactinium is so rare, radioactive, and toxic, you won't find it in everyday products. It is strictly used for scientific research. 

However, scientists have found an incredible use for it in understanding our planet's history. By measuring the tiny amounts of protactinium and thorium trapped in ancient ocean mud and sediments, researchers can date materials up to 175,000 years old. This technique has helped scientists map how ocean currents moved during the last Ice Age!

In the realm of energy, protactinium's most significant role is theoretical and practical for the future. In India's pioneering three-stage nuclear program, protactinium-233 is the indispensable middleman. By bombarding abundant thorium with neutrons, it briefly becomes protactinium before turning into valuable uranium fuel. Though not a fuel itself, protactinium is the secret ingredient making the dream of thorium-based nuclear power possible."""

pa['extract_html'] = extract_text
pa['sections'] = [
    {"title": "History", "text": history_text},
    {"title": "Isotopes", "text": isotopes_text},
    {"title": "Occurrence", "text": occurrence_text},
    {"title": "Applications", "text": applications_text}
]

out_dir = 'c:/projects/apache/school1/src/ptable/data/drafts'
os.makedirs(out_dir, exist_ok=True)
out_path = os.path.join(out_dir, 'Protactinium.json')

with open(out_path, 'w', encoding='utf-8') as f:
    json.dump({"Pa": pa}, f, indent=2)
