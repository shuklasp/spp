import json
import os

def main():
    master_path = 'src/ptable/data/master_elements.json'
    draft_path = 'src/ptable/data/drafts/Palladium.json'
    
    os.makedirs(os.path.dirname(draft_path), exist_ok=True)
    
    with open(master_path, 'r', encoding='utf-8') as f:
        master_data = json.load(f)
    
    pd_master = master_data['Pd']
    
    extract_html = '<p><b><a href="/school1/ptable/element/Pd" class="wiki-link" title="Palladium (Pd)">Palladium</a></b> (symbol <b>Pd</b>, atomic number 46) is a rare, lustrous, silvery-white metal belonging to the elite <b>platinum group metals (PGMs)</b>. Discovered in 1803 by the English chemist William Hyde Wollaston, it was named after the asteroid 2 Pallas, which itself honors Athena, the Greek goddess of wisdom. Palladium is legendary for its astounding ability to absorb up to 900 times its own volume of hydrogen gas, making it an indispensable superhero metal for clean energy and environmental protection. Today, it plays a vital role as the primary catalyst in automobile exhaust systems, converting toxic fumes into harmless gases. From groundbreaking green catalysis research at IIT Bombay to precious platinum-group deposits in Odisha and Tamil Nadu, palladium is also at the forefront of India\'s scientific and industrial advance.</p>'
    
    characteristics_html = '''<p>Palladium resides in Group 10 and Period 5 of the periodic table as a d-block transition metal, sitting directly below <a href="/school1/ptable/element/Ni" class="wiki-link" title="Nickel (Ni)">nickel</a> and right above <a href="/school1/ptable/element/Pt" class="wiki-link" title="Platinum (Pt)">platinum</a>. However, palladium harbors a fascinating atomic surprise! Standard electron filling rules (the Madelung rule) would predict its outermost electron configuration to be 5s<sup>2</sup> 4d<sup>8</sup>. Instead, according to Hund's rule, electrons shift to completely fill the 4d subshell, yielding a unique 5s<sup>0</sup> 4d<sup>10</sup> electron configuration because a completely filled d-shell is energetically more stable.</p>
<table class="wikitable">
<tbody><tr>
<th>Z</th>
<th>Element</th>
<th>No. of electrons/shell</th>
</tr>
<tr>
<td>28</td>
<td><a href="/school1/ptable/element/Ni" class="wiki-link" title="Nickel (Ni)">nickel</a></td>
<td>2, 8, 16, 2 (or 2, 8, 17, 1)</td>
</tr>
<tr>
<td>46</td>
<td><a href="/school1/ptable/element/Pd" class="wiki-link" title="Palladium (Pd)">palladium</a></td>
<td>2, 8, 18, 18, 0</td>
</tr>
<tr>
<td>78</td>
<td><a href="/school1/ptable/element/Pt" class="wiki-link" title="Platinum (Pt)">platinum</a></td>
<td>2, 8, 18, 32, 17, 1</td>
</tr>
<tr>
<td>110</td>
<td><a href="/school1/ptable/element/Ds" class="wiki-link" title="Darmstadtium (Ds)">darmstadtium</a></td>
<td>2, 8, 18, 32, 32, 16, 2 (predicted)</td>
</tr></tbody></table>
<p>This 5s<sup>0</sup> arrangement makes palladium the heaviest element in the periodic table with only <i>one</i> incomplete electron shell, leaving every higher electron shell entirely empty.</p>
<p>In appearance, palladium is a brilliant, soft, silvery-white metal that closely resembles <a href="/school1/ptable/element/Pt" class="wiki-link" title="Platinum (Pt)">platinum</a>. Among all six platinum-group metals, palladium has the lowest density (12.023 g/cm³) and the lowest melting point (1,828.05 K or 1,554.9 °C). When annealed (heated and cooled slowly), it is soft and highly ductile, but cold-working dramatically increases its physical strength and hardness.</p>
<p>Chemically, palladium is resistant to corrosion. At room temperature, it does not react with <a href="/school1/ptable/element/O" class="wiki-link" title="Oxygen (O)">oxygen</a> and will not tarnish in air. When heated to 800 °C, it forms a protective surface layer of palladium(II) oxide (PdO). Over extended periods in air, it may develop a light brownish tint due to a thin surface monoxide coating. Palladium dissolves slowly in concentrated nitric acid (HNO<sub>3</sub>), hot concentrated sulfuric acid (H<sub>2</sub>SO<sub>4</sub>), and finely ground hydrochloric acid (HCl), while dissolving readily at room temperature in <i>aqua regia</i> (a mix of nitric and hydrochloric acids).</p>
<p>At extreme temperatures, palladium exhibits exotic quantum phenomena. When thin palladium films are bombarded with alpha particles at ultra-low temperatures to introduce lattice defects, the metal becomes superconductive at a critical temperature of <i>T</i><sub>c</sub> = 3.2 K!</p>'''

    isotopes_html = '''<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r1368532237" /><div role="note" class="hatnote navigation-not-searchable">Main article: Isotopes of <a href="/school1/ptable/element/Pd" class="wiki-link" title="Palladium (Pd)">palladium</a></div>
<p>Naturally occurring palladium consists of a mix of six stable isotopes: <sup>102</sup>Pd, <sup>104</sup>Pd, <sup>105</sup>Pd, <sup>106</sup>Pd, <sup>108</sup>Pd, and <sup>110</sup>Pd, with <sup>106</sup>Pd being the most abundant in nature. Nuclear scientists have also created 28 synthetic radioactive isotopes, ranging from <sup>91</sup>Pd to <sup>129</sup>Pd. Most of these radioisotopes decay away in under 30 minutes, though a few are remarkably long-lived. The most stable radioisotopes are <b><sup>107</sup>Pd</b> with a half-life of 6.5 million years (found in trace amounts in nature), <b><sup>103</sup>Pd</b> with a half-life of 16.99 days, and <b><sup>100</sup>Pd</b> with a half-life of 3.63 days. Other notable radioisotopes include <sup>101</sup>Pd (8.47 hours), <sup>109</sup>Pd (13.6 hours), and <sup>112</sup>Pd (21.0 hours).</p>
<p>The decay path of palladium radioisotopes depends on their atomic mass relative to stable <sup>106</sup>Pd. Isotopes lighter than <sup>106</sup>Pd decay primarily by electron capture into <a href="/school1/ptable/element/Rh" class="wiki-link" title="Rhodium (Rh)">rhodium</a>, while heavier isotopes undergo beta decay (β<sup>−</sup>) to transform into <a href="/school1/ptable/element/Ag" class="wiki-link" title="Silver (Ag)">silver</a>.</p>
<p>Decay products of palladium radioisotopes offer extraordinary insights into the history of our Solar System. Radiogenic <b><sup>107</sup>Ag</b>—the decay product of <sup>107</sup>Pd—was first discovered in 1978 inside the Santa Clara iron meteorite (discovered in 1976). The ratio of <sup>107</sup>Pd to silver in meteorites reveals that small iron-cored protoplanets coalesced and melted within just 10 million years after the nucleosynthetic explosion that seeded our early Solar System.</p>
<p>Palladium-107 is also produced as a nuclear fission byproduct during the splitting of <a href="/school1/ptable/element/U" class="wiki-link" title="Uranium (U)">uranium-235</a> in nuclear power plants. Because <sup>107</sup>Pd releases low decay energy and binds tightly to soil, it is considered one of the safer, more environmentally benign long-lived radioactive fission products.</p>'''

    occurrence_html = '''<figure class="mw-default-size" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/50ca7d62cbfc880192921a931f7bf5e7.PNG" decoding="async" width="400" height="175" style="--mw-file-upright: 1.6" class="mw-file-element mw-file-upright"  data-file-width="1425" data-file-height="625" /><figcaption><a href="/school1/ptable/element/Pd" class="wiki-link" title="Palladium (Pd)">Palladium</a> output in 2005</figcaption></figure>
<p>Palladium is an extraordinarily scarce element in the Earth's crust, ranking among the rarest metals on Earth. In 2022, global mine production reached 210,000 kilograms (210 tonnes). Russia leads global production with 88,000 kg, followed by South Africa, Canada, the United States, and Zimbabwe. The Russian mining giant <i>Norilsk Nickel</i> is the single largest producer, generating 39% of the world's total supply.</p>
<p>Natural palladium can occur as a native free metal alloyed with <a href="/school1/ptable/element/Au" class="wiki-link" title="Gold (Au)">gold</a> and other platinum group metals in river placer deposits across Russia's Ural Mountains, Australia, Ethiopia, and the Americas. However, placer deposits supply only a tiny fraction of global demand. The world's primary commercial sources are massive nickel-copper ore deposits, notably the <b>Sudbury Basin</b> in Ontario, Canada, and the <b>Norilsk-Talnakh</b> deposits in Siberia. Another major source is the famous <b>Merensky Reef</b> platinum formation within South Africa's Bushveld Igneous Complex. In North America, the <b>Stillwater Complex</b> in Montana and the <b>Lac des Îles Complex</b> (Roby zone) in Ontario provide vital supplies. Palladium is also found in rare mineral compounds such as <i>cooperite</i> (a platinum-palladium sulfide) and <i>polarite</i> (a palladium-lead-bismuth compound).</p>
<p>Spent nuclear fuel from fission reactors contains substantial amounts of palladium. However, no commercial nuclear reprocessing facility currently recovers it. Extracting spent-fuel palladium is hampered by high radioactive waste handling costs and the presence of trace radioactive <sup>107</sup>Pd, which would require expensive isotopic separation before the metal could be safely reused in commercial products.</p>
<p><b>Geological Occurrence and Exploration in India:</b></p>
<p>In India, Platinum Group Elements (PGEs) including palladium are hosted within ancient Precambrian mafic-ultramafic igneous rock formations. Through systematic exploration, the <b>Geological Survey of India (GSI)</b> identified India's primary proven PGE deposit in the <b>Baula-Nuasahi Ultramafic Complex</b> (Bangur sector) in Keonjhar district, Odisha, which holds an estimated 14.2 million tonnes of PGE-enriched ore. Promising palladium-bearing prospects have also been mapped in the <b>Sittampundi Anorthosite Complex</b> in Namakkal district, Tamil Nadu—where discrete palladium minerals like <i>kotulskite</i> (PdTe), <i>sperrylite</i>, and <i>braggite</i> occur alongside chromitite—as well as the <b>Sukinda Ultramafic Complex</b> in Odisha. Research institutes like CSIR-IMMT (Institute of Minerals and Materials Technology) in Bhubaneswar actively develop eco-friendly mineral processing techniques to recover these precious metals for national self-reliance under the <i>Aatmanirbhar Bharat</i> vision.</p>'''

    applications_html = '''<figure class="mw-default-size" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/cfdf6d512bcda7a7cd7fef876ddc205c.jpg" decoding="async" width="250" height="116" class="mw-file-element"  data-file-width="640" data-file-height="297" /><figcaption>Cross-section of a metal-core catalytic converter</figcaption></figure>
<figure class="mw-default-size" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/7064a6be3bd3750a58018948349cd3e1.jpg" decoding="async" width="250" height="123" class="mw-file-element"  data-file-width="816" data-file-height="400" /><figcaption>The Soviet 25-rouble commemorative <a href="/school1/ptable/element/Pd" class="wiki-link" title="Palladium (Pd)">palladium</a> coin is a rare example of the monetary usage of <a href="/school1/ptable/element/Pd" class="wiki-link" title="Palladium (Pd)">palladium</a>.</figcaption></figure>
<p>By far the largest global consumer of palladium is the automotive industry. Over 80% of world production goes into <b>catalytic converters</b>, where palladium acts as a powerful chemical catalyst to transform up to 90% of harmful exhaust gases—such as carbon monoxide, nitrogen oxides, and unburnt hydrocarbons—into harmless water vapor, carbon dioxide, and nitrogen gas.</p>
<p>Beyond vehicle exhausts, palladium is a versatile wonder metal across many industries:</p>
<ul>
<li><b>Electronics & Dentistry:</b> Palladium is used in multi-layer ceramic capacitors, electrical contacts, blood sugar test strips, surgical instruments, aircraft spark plugs, dental crowns, and fine watchmaking.</li>
<li><b>Luxury Jewelry & Music:</b> Alloyed with <a href="/school1/ptable/element/Au" class="wiki-link" title="Gold (Au)">gold</a>, palladium creates lustrous, tarnish-resistant "white gold." It is also crafted into premium concert-grade transverse flutes.</li>
<li><b>Financial Bullion:</b> Palladium is a prized precious metal asset with official ISO international currency codes <b>XPD</b> and <b>964</b> (joining <a href="/school1/ptable/element/Au" class="wiki-link" title="Gold (Au)">gold</a>, <a href="/school1/ptable/element/Ag" class="wiki-link" title="Silver (Ag)">silver</a>, and <a href="/school1/ptable/element/Pt" class="wiki-link" title="Platinum (Pt)">platinum</a> as the only four metals with currency status).</li>
<li><b>Hydrogen Energy & Storage:</b> Palladium possesses an extraordinary ability to soak up <a href="/school1/ptable/element/H" class="wiki-link" title="Hydrogen (H)">hydrogen</a> gas like a metallic sponge, absorbing up to 900 times its own volume at room temperature. Thin palladium membranes are used to purify hydrogen gas for fuel cells. This remarkable sponge effect also made palladium the center of the famous 1989 "cold fusion" experiments by Fleischmann and Pons.</li>
</ul>
<p><b>Cutting-Edge Indian Scientific Contributions:</b></p>
<p>Indian scientists and research institutions are globally recognized leaders in palladium catalysis, green chemistry, and energy technology:</p>
<ul>
<li><b>Pioneering C–H Activation (IIT Bombay):</b> Prof. Debabrata Maiti and his team at IIT Bombay have developed revolutionary palladium catalysts capable of selective "remote C–H activation." By selectively snapping inert carbon-hydrogen bonds at precise distant locations on organic molecules, their work enables green, highly efficient synthesis of life-saving pharmaceutical drugs.</li>
<li><b>Sustainable Heterogeneous Catalysts (CSIR-IICT):</b> Dr. B. M. Choudary at CSIR-IICT Hyderabad pioneered reusable, eco-friendly supported palladium catalysts (using layered double hydroxides) for Suzuki-Miyaura, Heck, and Sonogashira cross-coupling reactions, drastically reducing industrial metal waste and cost.</li>
<li><b>Green Synthesis & Hydrogen Storage (IISc & CSIR-NCL):</b> Researchers at the Indian Institute of Science (IISc, Bengaluru) and CSIR-National Chemical Laboratory (NCL, Pune) lead breakthroughs in bio-synthesizing palladium nanoparticles using natural plant extracts (such as <i>Allium fistulosum</i> and <i>Basella alba</i>). Indian teams are also engineering palladium-doped carbon nanomaterials, Metal-Organic Frameworks (MOFs), and high-entropy alloy catalysts to power clean green hydrogen production and room-temperature hydrogen storage systems.</li>
</ul>'''

    history_html = '''<figure class="mw-default-size mw-halign-left" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/b8c7d5d7eacb9a4385e982f25f665bc5.jpg" decoding="async" width="190" height="251" style="--mw-file-upright: 0.75" class="mw-file-element mw-file-upright"  data-file-width="605" data-file-height="800" /><figcaption>William Hyde Wollaston</figcaption></figure>
<figure typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/c4ca12f1c402d9fd225d0b2a00d1d91b.png" decoding="async" width="191" height="192" class="mw-file-element"  data-file-width="314" data-file-height="316" /><figcaption>Very Large Telescope image of 2 Pallas, the asteroid after which <a href="/school1/ptable/element/Pd" class="wiki-link" title="Palladium (Pd)">Palladium</a> was named.</figcaption></figure>
<p><b>Ancient Metallurgy and Alchemical Heritage:</b></p>
<p>Long before modern chemistry classified elemental metals, ancient metallurgists unknowingly worked with platinum-group elements. In ancient Indian alchemical tradition (<i>Rasaśāstra</i>)—as recorded in classical 13th-century texts like Vāgbhaṭa's <i>Rasaratna Samuccaya</i>—metals were classified into <i>Śuddha Lohas</i> (pure metals like gold, silver, copper, iron), <i>Pūtilohas</i> (low-melting metals), and <i>Miśralohas</i> (alloys). While elemental palladium was not isolated until the 19th century, ancient Indian artisans processed alluvial gold and silver placer sands that naturally contained trace amounts of palladium and platinum, giving ancient ornamental metalwork exceptional brilliance and tarnish resistance.</p>
<p><b>Discovery and Scientific Drama in Europe:</b></p>
<p>The formal discovery of palladium reads like a 19th-century detective novel! In July 1802, English chemist <b>William Hyde Wollaston</b> noted in his lab notebook that he had isolated a new noble metal from crude South American platinum ore. In August 1802, he named it <b>Palladium</b> after the asteroid <i>2 Pallas</i>, which had been discovered just two months earlier (and was then classified as a new planet).</p>
<p>To profit from his discovery without disclosing his secret refining techniques, Wollaston anonymously put the metal up for sale in a small shop in Soho, London, in April 1803. Prominent chemist Richard Chenevix bought a sample, analyzed it, and publicly denounced palladium as a fraud, claiming it was merely a synthetic alloy of <a href="/school1/ptable/element/Pt" class="wiki-link" title="Platinum (Pt)">platinum</a> and <a href="/school1/ptable/element/Hg" class="wiki-link" title="Mercury (Hg)">mercury</a>. In response, Wollaston secretly published an anonymous challenge offering a £20 reward (a huge sum at the time) to anyone who could produce 20 grains of synthetic palladium alloy. No one could replicate it. Chenevix was awarded the prestigious Copley Medal in 1803 for his paper, but Wollaston finally stepped forward in 1805 with a formal scientific publication revealing himself as the true discoverer of both palladium and <a href="/school1/ptable/element/Rh" class="wiki-link" title="Rhodium (Rh)">rhodium</a>!</p>
<p>Wollaston's chemical isolation technique was ingenious: he dissolved crude South American platinum ore in <i>aqua regia</i>, neutralized the acid with <a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> hydroxide, and precipitated out platinum using ammonium chloride. He then added mercuric cyanide (Hg(CN)<sub>2</sub>) to precipitate palladium(II) cyanide (Pd(CN)<sub>2</sub>), which he strongly heated to drive off cyanogen gas and leave pure, shiny palladium metal.</p>
<p><b>Medicine, Economics, and Market Rollercoasters:</b></p>
<p>In the late 19th century, palladium chloride was briefly prescribed as a medical treatment for tuberculosis at 0.065 grams per day (~1 mg per kg of body weight). However, severe adverse side-effects led doctors to abandon it in favor of safer medications.</p>
<p>In modern times, palladium has become one of the most volatile precious commodities on global markets. Because catalytic converters require palladium, supply disruptions can trigger economic chaos. In late 2000, political delays in granting Russian export quotas caused market panic, sending palladium prices soaring to a record $1,340 per troy ounce ($43/g) in January 2001. Fearing production halts, Ford Motor Company stockpiled palladium at peak prices; when prices collapsed shortly after, Ford suffered a massive loss of nearly US$1 billion!</p>
<p>Global demand surged from 100 tonnes in 1990 to nearly 300 tonnes by 2000, with global mine output at 222 tonnes in 2006 (USGS). Concerns over supply stability mounted following Russia's annexation of Crimea in 2014, pushing prices past $900/oz. Prices hovered around $614/oz in 2016 before embarking on a historic rally: futures surged past $1,344/oz in January 2019, breached $2,000/oz ($2,024.64) for the first time on January 6, 2020, and shattered all-time records above $3,000 per troy ounce in May 2021 and March 2022 due to stringent global emission standards and tight supplies.</p>'''

    palladium_draft = {
        'symbol': pd_master['symbol'],
        'name': pd_master['name'],
        'atomic': pd_master['atomic'],
        'category': pd_master['category'],
        'description': pd_master['description'],
        'extract_html': extract_html,
        'local_image': pd_master['local_image'],
        'atomic_mass': pd_master['atomic_mass'],
        'density': pd_master['density'],
        'melt': pd_master['melt'],
        'boil': pd_master['boil'],
        'phase': pd_master['phase'],
        'discovered_by': pd_master['discovered_by'],
        'electron_configuration': pd_master['electron_configuration'],
        'electronegativity_pauling': pd_master['electronegativity_pauling'],
        'electron_affinity': pd_master['electron_affinity'],
        'ionization_energies': pd_master['ionization_energies'],
        'sections': {
            'Characteristics': characteristics_html,
            'Isotopes': isotopes_html,
            'Occurrence': occurrence_html,
            'Applications': applications_html,
            'History': history_html
        }
    }
    
    with open(draft_path, 'w', encoding='utf-8') as f:
        json.dump(palladium_draft, f, indent=2, ensure_ascii=False)
        
    print("Successfully created Palladium.json!")

if __name__ == '__main__':
    main()
