import json
import os

# Ensure the output directory exists
os.makedirs('src/ptable/data/drafts', exist_ok=True)

# Read the full original data just to have the base (or we can just write the fields directly)
with open('holmium_temp.json', 'r', encoding='utf-8') as f:
    ho_data = json.load(f)

# Rewrite extract_html
ho_data['extract_html'] = """<p><strong>Holmium (Ho)</strong> is a silvery-white, relatively soft, and malleable rare earth metal with the atomic number 67. To the naked eye, it might just look like another shiny piece of metal, but holmium has a secret power: it possesses the highest magnetic strength of any naturally occurring element! This extraordinary magnetism makes it incredibly valuable for specialized high-tech applications, from generating the strongest artificial magnetic fields to powering advanced medical lasers.</p>
<p>In the context of India, holmium plays a fascinating role. While India boasts some of the world's largest rare earth reserves—particularly in the monazite sands along the coasts of Kerala, Tamil Nadu, and Odisha—these sands are mostly rich in "light" rare earths. Because holmium is a "heavy" rare earth, extracting it from domestic sands is highly challenging. To secure a steady supply of crucial elements like holmium, India launched the National Critical Mineral Mission in 2025 and established Dedicated Rare Earth Corridors. Meanwhile, premier Indian institutions like the IITs and CSIR are spearheading research into advanced extraction technologies, ensuring that the nation's high-tech and medical sectors continue to thrive.</p>"""

# Rewrite sections
new_sections = {
    "Physical properties": "<p>Holmium is a shiny, silvery-white metal that is relatively soft and easy to shape. What truly sets holmium apart is its magnetic properties. It has the highest magnetic strength of any element found in nature! If you cool holmium down to very low temperatures, it becomes powerfully magnetic. Because of this, it is often combined with other metals, like yttrium, to create specialized, highly magnetic compounds used in advanced technology.</p>",
    "Chemical properties": "<p>In dry air, holmium remains shiny and relatively stable. However, when exposed to moisture or higher temperatures, it starts to react. It slowly tarnishes in the air, developing a yellowish crust that looks a bit like iron rust. It also dissolves easily in acids, bubbling up as it releases hydrogen gas and forming bright yellow solutions. Like many rare earth elements, it happily combines with elements like oxygen and halogens (such as fluorine and chlorine) to form beautiful, colorful salts.</p>",
    "Isotopes": "<p>In nature, holmium exists almost entirely as a single stable version, or \"isotope,\" called holmium-165. Scientists have also created several artificial, radioactive versions of holmium in laboratories. One of these, holmium-166, has found an important calling in the medical field. It releases a specific type of energy that makes it incredibly useful for targeted cancer therapies and for calibrating highly sensitive radiation-detecting instruments.</p>",
    "History": "<p>Holmium's story begins in 1878 when Swiss chemists Jacques-Louis Soret and Marc Delafontaine noticed a mysterious, previously unseen light pattern while examining rare earth minerals. Around the same time, Swedish chemist Per Teodor Cleve independently discovered the element while studying erbium. Cleve managed to separate out a brown substance he called <em>holmia</em>, after the Latin name for his hometown of Stockholm. It took several more decades before scientists could completely isolate pure holmium metal, but the name stuck, honoring the Swedish capital where part of its discovery took place.</p>",
    "Applications": "<p>Holmium is a superstar in both the medical and technological worlds. Its incredible magnetic properties make it ideal for creating the strongest artificial magnetic fields, which are used in scientific research and specialized equipment. One of its most life-changing uses is in medical lasers. Holmium lasers (often called Ho:YAG lasers) emit a very precise type of light that is perfect for delicate surgeries. In India, for example, these lasers have revolutionized urology, offering a highly effective, minimally invasive way to treat kidney stones and prostate conditions, dramatically improving patient recovery times.</p>\n<p>Beyond medicine, holmium is used to color glass and cubic zirconia, giving them a beautiful yellow or pink tint. Because it can absorb neutrons, it is also used to safely regulate nuclear reactors. Recently, researchers have even stored data on single atoms of holmium, pointing toward a future where this remarkable element could help build ultra-powerful quantum computers!</p>"
}

ho_data['sections'] = new_sections

with open('src/ptable/data/drafts/Holmium.json', 'w', encoding='utf-8') as f:
    json.dump(ho_data, f, indent=2)

print("Holmium draft written successfully.")
