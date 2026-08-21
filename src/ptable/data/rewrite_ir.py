import json
import os

data = {
    "extract_html": "<p><strong>Iridium</strong> (symbol <strong>Ir</strong>, atomic number 77) is a remarkable transition metal belonging to the platinum group. Known for its extreme durability and silvery-white appearance, it is the most corrosion-resistant metal on Earth. Iridium is so resilient that it remains completely unaffected by air, water, and even highly corrosive acids like aqua regia. Because of its incredible hardness and high melting point, it is challenging to shape but incredibly valuable in high-tech industries.</p><p>Interestingly, iridium is exceptionally rare in the Earth's crust but is found in higher concentrations in meteorites. The discovery of an iridium-rich layer of clay across the globe provided key evidence for the asteroid impact that wiped out the dinosaurs 66 million years ago!</p>",
    "sections": {
        "Characteristics": "<p>Iridium is a silvery-white, hard, and extremely brittle metal. It belongs to the platinum group and shares a visual resemblance to platinum, though it has a slight yellowish tint. Because it is so stiff and has a very high melting point, working with solid iridium is quite difficult. Instead of traditional metalworking, it is usually processed using powder metallurgy. Amazingly, iridium is the only metal that can maintain excellent mechanical properties in the air at scorching temperatures above 1,600 &deg;C (2,910 &deg;F).</p>",
        "Physical properties": "<p>Iridium is famous for being incredibly dense. In fact, it is the second-densest naturally occurring element, just slightly behind osmium, with a density of 22.56 g/cm&sup3;. It melts at a staggering 2,446 &deg;C (4,435 &deg;F) and boils at 4,130 &deg;C (7,466 &deg;F). It is also highly resilient to deformation. The stiffness of iridium, measured by its modulus of elasticity, is the second highest of all metals. When cooled to near absolute zero (below 0.14 K), iridium even becomes a superconductor, meaning it can conduct electricity with zero resistance.</p>",
        "Chemical properties": "<p>When it comes to corrosion, iridium is practically invincible. It is the most corrosion-resistant metal known to science. It does not react with standard acids, including the notoriously strong <em>aqua regia</em> (a mix of nitric and hydrochloric acids). However, under very specific conditions, it can be dissolved in concentrated hydrochloric acid if sodium perchlorate is present. At high temperatures, it will react with oxygen, halogens, or sulfur, forming compounds like iridium disulfide.</p>",
        "Isotopes": "<p>In nature, iridium is found as a mix of two stable isotopes: <sup>191</sup>Ir (about 37.3%) and <sup>193</sup>Ir (about 62.7%). Scientists have also discovered or created dozens of radioactive isotopes. The most important of these is Iridium-192, which has a half-life of roughly 74 days. Iridium-192 is highly valued in the medical field for cancer treatments (brachytherapy) and in industrial settings to X-ray and inspect steel welds in oil and gas pipelines.</p>",
        "History": "<p>Iridium was discovered in 1803 by the British chemist Smithson Tennant. While studying platinum ores, he found a residue that would not dissolve in acid. He realized this residue contained two new elements: osmium and iridium. Tennant named iridium after <em>Iris</em>, the Greek goddess of the rainbow, because the salts produced by the metal displayed a wide and striking array of colors. A major historical milestone for the metal occurred in 1889, when an alloy of 90% platinum and 10% iridium was used to craft the International Prototype Meter bar, which stood as the global standard for the unit of length for decades.</p>",
        "Occurrence": "<p>On Earth, iridium is one of the rarest elements in the crust&mdash;much rarer than gold or platinum. It is generally found alongside other platinum-group metals in natural alloys or nickel and copper deposits. The largest primary reserves are located in South Africa, Russia, and Canada. However, iridium is surprisingly common in meteorites. During Earth's early, molten history, most of its iridium sank into the planet's core because of its density and tendency to bond with iron. The thin layer of iridium found in the Earth's crust at the Cretaceous-Paleogene boundary is what gave scientists the clue that a massive asteroid impact caused the extinction of the dinosaurs.</p>",
        "Applications": "<p>Today, iridium is essential in modern technology. Its extreme heat resistance makes it perfect for high-grade spark plugs in airplanes and cars, as well as for making crucibles used to grow laser and LED crystals. It is also used in OLED screens and as a catalyst in chemical production. In the push for green energy, iridium electrodes are increasingly important for systems that produce green hydrogen. In the Indian context, iridium plays a role in advanced aerospace research. Institutions like the Indian Space Research Organisation (ISRO) and the Council of Scientific and Industrial Research (CSIR-NIIST) have studied iridium coatings for carbon-carbon composites, developing specialized materials designed to withstand the extreme conditions of space travel.</p>"
    }
}

base_file = r'c:\projects\apache\school1\src\ptable\data\master_elements.json'
with open(base_file, 'r', encoding='utf-8') as f:
    master = json.load(f)

draft_dir = r'c:\projects\apache\school1\src\ptable\data\drafts'
os.makedirs(draft_dir, exist_ok=True)

ir_data = master['Ir']
ir_data['extract_html'] = data['extract_html']
ir_data['sections'] = data['sections']

out_file = os.path.join(draft_dir, 'Iridium.json')
with open(out_file, 'w', encoding='utf-8') as f:
    json.dump(ir_data, f, indent=2)

print("Iridium.json created successfully.")
