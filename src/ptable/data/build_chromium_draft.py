import json
import os

master_path = r'c:\projects\apache\school1\src\ptable\data\master_elements.json'
output_dir = r'c:\projects\apache\school1\src\ptable\data\drafts'
output_path = os.path.join(output_dir, 'Chromium.json')

with open(master_path, 'r', encoding='utf-8') as f:
    master_data = json.load(f)

cr_data = master_data['Cr']

extract_html = (
    '<p><b><a href="/school1/ptable/element/Cr" class="wiki-link" title="Chromium (Cr)">Chromium</a></b> '
    '(symbol <b>Cr</b>, atomic number 24) is a lustrous, steely-grey transition metal renowned for its '
    'extraordinary hardness, polished shine, and vivid array of colorful chemical compounds. Positioned in Group 6 '
    'of the periodic table, chromium derives its name from the Greek word <i>chroma</i>, meaning color, because its '
    'diverse chemical states produce a spectacular spectrum of brilliant hues—ranging from deep emerald greens and ruby reds '
    'to sunny yellows and intense oranges. Chromium is the essential shield behind modern stainless steel, forming an invisible, '
    'self-healing oxide barrier that defends iron against rust and corrosion. Beyond its industrial dominance in chrome plating '
    'and high-strength alloys, chromium holds deep geographical and cultural significance in India: the mineral-rich <b>Sukinda Valley</b> '
    'in Odisha harbors nearly all of India\'s vast chromite reserves, while trace chromium ions are the hidden natural artists responsible '
    'for the captivating green of emeralds (<i>Marakata</i>) and the radiant crimson of rubies (<i>Padmaraga</i>) celebrated in ancient Indian texts.</p>'
)

physical_properties_html = (
    '</div>\n'
    '<p><a href="/school1/ptable/element/Cr" class="wiki-link" title="Chromium (Cr)">Chromium</a> is a hard, brittle transition metal '
    'with a polished silvery-grey luster and a high resistance to tarnishing. With a density of 7.19 grams per cubic centimeter, '
    'a melting point of 2,180&#160;\u00b0C (3,956&#160;\u00b0F), and a boiling point of 2,944&#160;\u00b0C (5,331&#160;\u00b0F), chromium '
    'withstands extreme thermal and physical environments. On the Mohs scale of mineral hardness, pure chromium scores an impressive 8.5 to 9, '
    'making it one of the hardest pure metallic elements known.</p>\n'
    '<p>A key chemical superpower of chromium is its ability to undergo <b>passivation</b>. When exposed to ambient air or oxygen, '
    'chromium instantly forms a microscopic, transparent layer of chromium(III) oxide (Cr<sub>2</sub>O<sub>3</sub>) just a few atoms thick. '
    'This oxide film acts as an impenetrable armor that seals the metal beneath, rendering chromium highly resistant to further oxidation, rust, '
    'and attack by many acids. If the surface is scratched, the protective oxide film heals itself within milliseconds in the presence of air.</p>\n'
    '<p>In quantum chemistry, chromium is famous for its unique electron configuration: [Ar] 4s<sup>1</sup> 3d<sup>5</sup>. Instead of filling '
    'its outer 4s subshell with two electrons as expected, chromium promotes one electron into its 3d orbital to create a half-filled, highly stable '
    '3d<sup>5</sup> configuration. This special arrangement accounts for its strong metallic bonding, high melting point, and fascinating magnetic '
    'properties—chromium is the only elemental solid that exhibits antiferromagnetic ordering at room temperature (below its N\u00e9el temperature of about 312 K or 39 \u00b0C).</p>\n'
    '<div class="mw-heading mw-heading3">'
)

history_html = (
    '</div>\n'
    '<p>Long before chromium was isolated as a chemical element in Western laboratories, its dramatic colors captivated ancient civilizations. '
    'In ancient India, gemology (<i>Ratna Shastra</i>) flourished as a precise art and science. Sanskrit encyclopedias such as the 6th-century '
    '<i>Brihat Samhita</i> by Varahamihira and medieval alchemical texts like the 13th-century <i>Rasaratnasamuccaya</i> cataloged precious gems with '
    'meticulous detail. They celebrated the serene green emerald (<i>Marakata</i>) and the fiery red ruby (<i>Padmaraga</i> or <i>Manikya</i>). '
    'Modern spectroscopy later revealed that both of these prized <i>Navaratnas</i> (nine sacred gemstones) owe their vivid colors to the exact same element: '
    'trace trivalent chromium (Cr<sup>3+</sup>) ions substituting for <a href="/school1/ptable/element/Al" class="wiki-link" title="Aluminum (Al)">aluminum</a> '
    'in their crystal lattices!</p>\n'
    '<p>Chromium also played a silent protective role in ancient Chinese metallurgy. Over 2,000 years ago during the Qin Dynasty, weapon smiths treated '
    'bronze swords, spears, and arrowheads buried with the famous Terracotta Army with a thin chromium oxide coating, leaving these ancient weapons remarkably '
    'shiny and rust-free when excavated in modern times.</p>\n'
    '<p>The Western scientific discovery of chromium began in 1761 when German mineralogist Johann Gottlob Lehmann discovered a vibrant orange-red mineral in '
    'Siberia\'s Ural Mountains, which he named "Siberian red lead" (now known as crocoite, lead chromate, PbCrO<sub>4</sub>). In 1797, French chemist '
    '<b>Louis Nicolas Vauquelin</b> analyzed samples of crocoite ore. Vauquelin produced chromium trioxide and successfully isolated pure metallic chromium by '
    'reducing the oxide with charcoal in a high-temperature furnace. Intrigued by the brilliant red, yellow, and green colors of its chemical compounds, '
    'Vauquelin named the new element <i>chromium</i>, from the Greek word <i>chroma</i> (color).</p>\n'
    '<div class="mw-heading mw-heading2">'
)

isotopes_html = (
    '</div>\n'
    '<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r1368532237" /><div role="note" class="hatnote navigation-not-searchable">Main article: Isotopes of <a href="/school1/ptable/element/Cr" class="wiki-link" title="Chromium (Cr)">chromium</a></div>\n'
    '<p>Naturally occurring <a href="/school1/ptable/element/Cr" class="wiki-link" title="Chromium (Cr)">chromium</a> is composed of four stable isotopes: '
    '<sup>50</sup>Cr, <sup>52</sup>Cr, <sup>53</sup>Cr, and <sup>54</sup>Cr. Among these, <b><sup>52</sup>Cr</b> is by far the most abundant, '
    'constituting 83.789% of all natural chromium. The remaining stable isotopes make up 4.345% (<sup>50</sup>Cr), 9.501% (<sup>53</sup>Cr), and 2.365% (<sup>54</sup>Cr). '
    'Scientists have also synthesized 25 radioactive isotopes ranging from <sup>42</sup>Cr to <sup>70</sup>Cr, alongside two metastable nuclear isomers. '
    'The most stable radioisotope is <b><sup>51</sup>Cr</b>, which has a half-life of 27.70 days and is widely used in biomedical research to label red blood cells '
    'and measure blood volume.</p>\n'
    '<p>In nuclear physics, isotopes lighter than <sup>52</sup>Cr decay primarily through electron capture into isotopes of <a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">vanadium</a> '
    'or <a href="/school1/ptable/element/Ti" class="wiki-link" title="Titanium (Ti)">titanium</a>, while heavier isotopes decay via beta emission into isotopes of '
    '<a href="/school1/ptable/element/Mn" class="wiki-link" title="Manganese (Mn)">manganese</a>.</p>\n'
    '<p>Chromium isotopes play a crucial role in planetary science and isotope geology. The isotope <b><sup>53</sup>Cr</b> is the radiogenic decay product of extinct '
    'manganese-53 (<sup>53</sup>Mn, half-life of 3.7 million years). Because manganese and chromium are often concentrated together in planetary rocks, the ratio of <sup>53</sup>Cr to <sup>52</sup>Cr '
    'paired with Mn/Cr ratios in ancient meteorites provides astronomers with a high-precision chemical clock. This Mn–Cr isotopic system reinforces evidence from <sup>26</sup>Al and <sup>107</sup>Pd, '
    'proving that distinct planetary bodies differentiated within the first few million years of the Solar System\'s birth.</p>\n'
    '<p>Furthermore, variations in chromium isotope ratios (<sup>53</sup>Cr/<sup>52</sup>Cr) trapped in ancient marine sediments serve as a sensitive proxy for historical atmospheric '
    '<a href="/school1/ptable/element/O" class="wiki-link" title="Oxygen (O)">oxygen</a> concentrations, allowing geologists to reconstruct how Earth\'s atmosphere became oxygen-rich over billions of years.</p>\n'
    '<div class="mw-heading mw-heading2">'
)

occurrence_html = (
    '</div>\n'
    '<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r1368532237" /><div role="note" class="hatnote navigation-not-searchable selfref">See also: Category:<a href="/school1/ptable/element/Cr" class="wiki-link" title="Chromium (Cr)">Chromium</a> minerals</div>\n'
    '<figure class="mw-default-size mw-halign-left" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/f430d9fdf335cdfbb9bc7a1e7ba47dad.jpg" decoding="async" width="190" height="126" style="--mw-file-upright: 0.75" class="mw-file-element mw-file-upright" data-file-width="3008" data-file-height="2000" /><figcaption>Crocoite (PbCrO<sub>4</sub>)</figcaption></figure>\n'
    '<figure class="mw-default-size mw-halign-left" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/94e898e6707d6456d3354fb68d0b8e58.jpg" decoding="async" width="190" height="152" style="--mw-file-upright: 0.75" class="mw-file-element mw-file-upright" data-file-width="1837" data-file-height="1469" /><figcaption>Chromite ore</figcaption></figure>\n'
    '<p><a href="/school1/ptable/element/Cr" class="wiki-link" title="Chromium (Cr)">Chromium</a> is the 21st most abundant element in Earth\'s crust, with an average concentration of roughly 100 parts per million (ppm). '
    'Natural erosion of chromium-bearing rocks and volcanic eruptions constantly redistribute trace amounts into the environment. Typical background concentrations are under 10 ng/m<sup>3</sup> in the atmosphere, '
    'under 500 mg/kg in soils, under 0.5 mg/kg in vegetation, under 10 &#181;g/L in freshwater, and under 1 &#181;g/L in seawater. Virtually all commercial chromium is extracted from <b>chromite ore</b> (iron chromium oxide, FeCr<sub>2</sub>O<sub>4</sub>). '
    'Pure native elemental chromium metal is exceptionally rare in nature, found only in unusual reducing environments such as the diamond-rich Udachnaya kimberlite pipe in Russia.</p>\n'
    '<p>Globally, chromite deposits are concentrated in a few key nations. South Africa produces roughly two-fifths of the world\'s chromite ores and concentrates, Kazakhstan produces about one-third, while India, Russia, and Turkey are major global suppliers.</p>\n'
    '<p><b>India\'s Geographical & Geological Hub:</b> India holds world-class chromite reserves, with the state of <b>Odisha</b> serving as the country\'s unquestioned chromite capital. The famous <b>Sukinda Valley</b> in Jajpur district, '
    'nestled between the Daitari and Mahagiri hill ranges, contains an astounding <b>95% to 98% of India\'s total chromite deposits</b>. Geological formations in the Sukinda Ultramafic Complex feature serpentinized peridotites and host the world\'s thickest known single chromite seam, '
    'measuring up to 40 meters across! Major mining companies such as Tata Steel, Odisha Mining Corporation (OMC), and FACOR operate extensive open-cast mines in Sukinda, producing high-grade ore for domestic ferrochrome smelting and global export.</p>\n'
    '<p>In natural water systems, chromium exists primarily as non-toxic trivalent Cr(III) or toxic hexavalent Cr(VI), depending on soil pH and oxygen levels. While Cr(III) predominates in most natural soils, certain mineral-rich groundwaters can accumulate total chromium levels up to 39 &#181;g/L, of which up to 30 &#181;g/L may be present as hexavalent Cr(VI).</p>\n'
    '<div style="clear:left;" class=""></div>\n'
    '<div class="mw-heading mw-heading2">'
)

applications_html = (
    '</div>\n'
    '<p>About <b>85% of all chromium consumed globally</b> is used in metal alloys, with the remainder powering the chemical, refractory ceramic, and foundry industries. Chromium\'s primary application is the manufacture of <b>stainless steel</b>. '
    'Adding at least 10.5% chromium to <a href="/school1/ptable/element/Fe" class="wiki-link" title="Iron (Fe)">iron</a> creates an alloy that is practically immune to rust, because chromium forms an invisible, self-healing oxide passivation layer on the steel surface. '
    'Stainless steel is essential for modern kitchen cutlery, medical surgical instruments, food processing plants, chemical storage tanks, and architectural skyscrapers.</p>\n'
    '<p>In India, Sukinda\'s rich chromite ore is converted into ferrochrome and charge-chrome inside heavy industrial furnaces. This domestic ferrochrome powers India\'s booming steel industry, supplying high-performance stainless steel for structural infrastructure, heavy machinery, defense armor, and lightweight passenger coaches like the Indian Railways\' high-speed <b>Vande Bharat Express</b> trains.</p>\n'
    '<p>Chromium is also famous for <b>chrome plating</b>. Through electroplating, a paper-thin layer of chromium is deposited onto metal or plastic surfaces, providing a bright, mirror-like finish that resists tarnishing, scratches, and corrosion. Shiny chrome trims are ubiquitous on automobiles, motorcycles, bathroom faucets, hand tools, and commercial machinery. '
    'Beyond metals, chromium compounds serve as high-temperature refractory bricks to line blast furnaces, as specialized foundry sand, and as vivid industrial pigments (such as chrome yellow and chrome green).</p>\n'
    '<p><b>Indian Scientific Innovation in Leather Processing:</b> Historically, chromium salts (specifically trivalent chromium sulfate) have been the world\'s primary agent for tanning animal hides into durable leather. However, traditional chrome tanning discharged large amounts of chromium-laden wastewater. To solve this environmental challenge, scientists at the <b>CSIR-Central Leather Research Institute (CSIR-CLRI)</b> in Chennai developed revolutionary <b>Waterless Chrome Tanning Technology (WCTT)</b>. '
    'This indigenous Indian breakthrough completely eliminates water from the chrome tanning step, cuts salt usage by 20%, saves millions of liters of freshwater, and eliminates chromium discharge into rivers, setting a global benchmark for eco-friendly industrial chemistry.</p>\n'
    '<div class="mw-heading mw-heading3">'
)

biological_role_html = (
    '</div>\n'
    '<p>The biological role of <a href="/school1/ptable/element/Cr" class="wiki-link" title="Chromium (Cr)">chromium</a> depends entirely on its chemical oxidation state. Trivalent chromium (<b>Cr(III)</b> or Cr<sup>3+</sup>) is considered a trace dietary element. For decades, scientists hypothesized that Cr(III) assisted insulin in regulating carbohydrate, fat, and protein metabolism through an oligopeptide molecule known as chromodulin (low-molecular-weight chromium-binding substance). '
    'However, modern medical research has not conclusively established an essential biochemical pathway, and "chromium deficiency" is not recognized as a formal medical condition because healthy individuals do not require dietary chromium supplements. Common foods contain small amounts of chromium (1 to 13 micrograms per serving). Trace amounts of chromium (and <a href="/school1/ptable/element/Ni" class="wiki-link" title="Nickel (Ni)">nickel</a>) can also leach into food when acidic dishes are cooked for extended periods in stainless steel cookware, particularly when the cookware is brand new.</p>\n'
    '<p>In sharp contrast, hexavalent chromium (<b>Cr(VI)</b> or Cr<sup>6+</sup>) is a severe toxin, mutagen, and known human carcinogen. Ingesting Cr(VI) through contaminated drinking water has been linked to stomach tumors, liver damage, and severe allergic contact dermatitis, while inhaling Cr(VI) dust causes lung cancer and nasal ulcers.</p>\n'
    '<p><b>Modern Indian Remediation Science:</b> Environmental contamination from open-cast chromite mining in regions like the Sukinda Valley in Odisha has historically led to hexavalent Cr(VI) leaching into local water bodies like the Damsala Nallah and the Brahmani River basin. To combat this public health risk, Indian scientists at the <b>CSIR-Institute of Minerals and Materials Technology (CSIR-IMMT)</b> in Bhubaneswar, along with researchers at the Bhabha Atomic Research Centre (BARC) and leading Indian universities, have developed cutting-edge remediation technologies. '
    'They isolate indigenous, chromium-tolerant bacteria (such as <i>Bacillus</i> and <i>Pseudomonas</i> species) and native fungi from Sukinda soils capable of bio-reducing toxic Cr(VI) into non-toxic, insoluble Cr(III). Coupled with phytoremediation using local metallophyte plants, these innovative bio-cleanup methods neutralize chromium pollution, protecting ecosystems and communities across India.</p>\n'
    '<div class="mw-heading mw-heading3">'
)

# Update cr_data with rewritten content
updated_cr_data = dict(cr_data)
updated_cr_data['extract_html'] = extract_html
updated_cr_data['sections'] = {
    "Physical properties": physical_properties_html,
    "Isotopes": isotopes_html,
    "Occurrence": occurrence_html,
    "History": history_html,
    "Applications": applications_html,
    "Biological role": biological_role_html
}

os.makedirs(output_dir, exist_ok=True)
with open(output_path, 'w', encoding='utf-8') as f:
    json.dump(updated_cr_data, f, indent=2, ensure_ascii=False)

print(f"Successfully generated draft Chromium JSON at {output_path}")
