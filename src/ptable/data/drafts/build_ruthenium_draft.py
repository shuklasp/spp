import json
import os

ruthenium_data = {
    "symbol": "Ru",
    "name": "Ruthenium",
    "atomic": 44,
    "category": "transition metal",
    "description": "Chemical element with atomic number 44 (Ru)",
    "extract_html": """<p><b><a href="/school1/ptable/element/Ru" class="wiki-link" title="Ruthenium (Ru)">Ruthenium</a></b> (symbol <b>Ru</b>, atomic number 44) is an extraordinarily rare, hard, silvery-white transition metal belonging to the prestigious <b>platinum group metals (PGMs)</b>. Located in group 8 of the periodic table, it is celebrated for its extreme chemical inertness, remarkably high melting point, and exceptional ability to strengthen other precious metals. Though obscure in everyday life, ruthenium plays a quiet yet crucial role in modern technology, from microchip resistors and jet engine superalloys to cutting-edge clean energy catalysts. In India, ruthenium is at the center of pioneering innovations—ranging from life-saving eye cancer treatments developed at the Bhabha Atomic Research Centre (BARC) to revolutionary brain-like neuromorphic artificial intelligence chips created at the Indian Institute of Science (IISc).</p>""",
    "local_image": "https://upload.wikimedia.org/wikipedia/commons/a/a8/Ruthenium_crystal.jpg",
    "atomic_mass": 101.072,
    "density": 12.45,
    "melt": 2607,
    "boil": 4423,
    "phase": "Solid",
    "discovered_by": "Karl Ernst Claus",
    "electron_configuration": "1s2 2s2 2p6 3s2 3p6 4s2 3d10 4p6 5s1 4d7",
    "electronegativity_pauling": 2.2,
    "electron_affinity": 100.96,
    "ionization_energies": [
        710.2,
        1620,
        2747
    ],
    "sections": {
        "Characteristics": """<p>Ruthenium sits in group 8 of the periodic table, occupying the 4d transition metal series right below <a href="/school1/ptable/element/Fe" class="wiki-link" title="Iron (Fe)">iron</a> and above <a href="/school1/ptable/element/Os" class="wiki-link" title="Osmium (Os)">osmium</a>. While it shares many chemical traits with its group members, ruthenium exhibits an intriguing atomic quirk in its electron structure.</p>
<table class="wikitable">
<tbody><tr>
<th>Z</th>
<th>Element</th>
<th>No. of electrons/shell</th>
</tr>
<tr>
<td>26</td>
<td><a href="/school1/ptable/element/Fe" class="wiki-link" title="Iron (Fe)">iron</a></td>
<td>2, 8, 14, 2</td>
</tr>
<tr>
<td>44</td>
<td><a href="/school1/ptable/element/Ru" class="wiki-link" title="Ruthenium (Ru)">ruthenium</a></td>
<td>2, 8, 18, 15, 1</td>
</tr>
<tr>
<td>76</td>
<td><a href="/school1/ptable/element/Os" class="wiki-link" title="Osmium (Os)">osmium</a></td>
<td>2, 8, 18, 32, 14, 2</td>
</tr>
<tr>
<td>108</td>
<td><a href="/school1/ptable/element/Hs" class="wiki-link" title="Hassium (Hs)">hassium</a></td>
<td>2, 8, 18, 32, 32, 14, 2</td>
</tr></tbody></table>
<p>Most group 8 elements possess two electrons in their outermost <i>s</i> orbital. However, ruthenium's ground-state electron configuration is [Kr] 4d<sup>7</sup> 5s<sup>1</sup>, leaving only a single electron in its outermost 5s shell while placing the remaining electron into a lower 4d shell. This structural anomaly—also observed in neighboring elements from <a href="/school1/ptable/element/Nb" class="wiki-link" title="Niobium (Nb)">niobium</a> (Z = 41) to <a href="/school1/ptable/element/Rh" class="wiki-link" title="Rhodium (Rh)">rhodium</a> (Z = 45), with the exception of <a href="/school1/ptable/element/Tc" class="wiki-link" title="Technetium (Tc)">technetium</a>—does not compromise its chemical stability. Instead, it makes ruthenium a fascinating subject of study in atomic physics and quantum chemistry.</p>""",
        "Physical properties": """<p>Ruthenium is a lustrous, silvery-white metal that is extremely hard and brittle at room temperature. It exists in four distinct crystal modifications and boasts a dense hexagonal close-packed lattice. With a density of 12.45 g/cm³, it melts at an astonishing 2,607 K (2,334 °C) and boils at 4,423 K (4,150 °C).</p>
<p>Along the 4d transition series, ruthenium marks the beginning of a downward trend in melting point, boiling point, and enthalpy of atomization following the peak seen at <a href="/school1/ptable/element/Mo" class="wiki-link" title="Molybdenum (Mo)">molybdenum</a>. This occurs because its 4d electron subshell is more than half full, meaning outer electrons contribute less to metallic bonding. While standard bulk ruthenium is paramagnetic at room temperature, scientists discovered that growing a metastable tetragonal thin film of ruthenium on a single-crystal molybdenum substrate produces room-temperature ferromagnetism!</p>
<p>Modern Indian physics researchers are keenly exploring ruthenium's unique electronic and magnetic properties. At IIT Delhi, scientists utilizing low-temperature Raman spectroscopy investigate rutile-type oxides like ruthenium dioxide (RuO<sub>2</sub>). Their research focuses on electron-lattice interactions and the emerging phenomenon of <i>altermagnetism</i>—a newly discovered magnetic phase that combines the high-speed data transfer of ferromagnets with the ultra-dense storage capability of antiferromagnets, paving the way for next-generation spintronics and quantum computing.</p>""",
        "Chemical properties": """<p>Ruthenium is famously unreactive under ambient conditions, refusing to tarnish in air at room temperature. When heated in air above 800 °C (1,070 K), it reacts with oxygen to form ruthenium dioxide (RuO<sub>2</sub>). Ruthenium resists attack by almost all acids—it remains completely undissolved even in boiling <i>aqua regia</i> (a potent mixture of nitric acid and hydrochloric acid). However, it dissolves in molten alkalis to yield ruthenate ions (<span class="chemf nowrap">RuO<span class="nowrap"><span style="display:inline-block;margin-bottom:-0.3em;vertical-align:-0.4em;line-height:1em;font-size:80%;text-align:left"><sup style="font-size:inherit;line-height:inherit;vertical-align:baseline">2−</sup><br /><sub style="font-size:inherit;line-height:inherit;vertical-align:baseline">4</sub></span></span></span>), and is attacked by room-temperature sodium hypochlorite as well as high-temperature halogens.</p>
<p>Ruthenium displays an impressively wide range of oxidation states from −2 to +8. Notably, ruthenium is the only 4d transition metal capable of reaching the maximum +8 oxidation state (in ruthenium tetroxide, RuO<sub>4</sub>), though it is less stable than its heavier congener <a href="/school1/ptable/element/Os" class="wiki-link" title="Osmium (Os)">osmium</a> in this state. Unlike osmium but like <a href="/school1/ptable/element/Fe" class="wiki-link" title="Iron (Fe)">iron</a>, ruthenium readily forms stable aqueous cations in lower +2 and +3 oxidation states (Ru<sup>2+</sup> and Ru<sup>3+</sup>).</p>
<p>Adding tiny amounts of ruthenium dramatically alters other metals: adding just 0.1% ruthenium to <a href="/school1/ptable/element/Ti" class="wiki-link" title="Titanium (Ti)">titanium</a> increases its corrosion resistance hundredfold, while small additions significantly harden <a href="/school1/ptable/element/Pt" class="wiki-link" title="Platinum (Pt)">platinum</a> and <a href="/school1/ptable/element/Pd" class="wiki-link" title="Palladium (Pd)">palladium</a> alloys. A ruthenium–<a href="/school1/ptable/element/Mo" class="wiki-link" title="Molybdenum (Mo)">molybdenum</a> alloy acts as a superconductor at temperatures below 10.6 K.</p>
<p>The standard reduction potentials for key ruthenium species in acidic solution highlight its rich electrochemistry:</p>
<table class="wikitable">
<tbody><tr>
<th>Potential</th>
<th colspan="2">Reaction</th>
</tr>
<tr>
<td>0.455 V</td>
<td>Ru<sup>2+</sup> + 2e<sup>−</sup></td>
<td>↔ Ru</td>
</tr>
<tr>
<td>0.249 V</td>
<td>Ru<sup>3+</sup> + e<sup>−</sup></td>
<td>↔ Ru<sup>2+</sup></td>
</tr>
<tr>
<td>1.120 V</td>
<td>RuO<sub>2</sub> + 4H<sup>+</sup> + 2e<sup>−</sup></td>
<td>↔ Ru<sup>2+</sup> + 2H<sub>2</sub>O</td>
</tr>
<tr>
<td>1.563 V</td>
<td><span class="chemf nowrap">RuO<span class="nowrap"><span style="display:inline-block;margin-bottom:-0.3em;vertical-align:-0.4em;line-height:1em;font-size:80%;text-align:left"><sup style="font-size:inherit;line-height:inherit;vertical-align:baseline">2−</sup><br /><sub style="font-size:inherit;line-height:inherit;vertical-align:baseline">4</sub></span></span></span> + 8H<sup>+</sup> + 4e<sup>−</sup></td>
<td>↔ Ru<sup>2+</sup> + 4H<sub>2</sub>O</td>
</tr>
<tr>
<td>1.368 V</td>
<td><span class="chemf nowrap">RuO<span class="nowrap"><span style="display:inline-block;margin-bottom:-0.3em;vertical-align:-0.4em;line-height:1em;font-size:80%;text-align:left"><sup style="font-size:inherit;line-height:inherit;vertical-align:baseline">−</sup><br /><sub style="font-size:inherit;line-height:inherit;vertical-align:baseline">4</sub></span></span></span> + 8H<sup>+</sup> + 5e<sup>−</sup></td>
<td>↔ Ru<sup>2+</sup> + 4H<sub>2</sub>O</td>
</tr>
<tr>
<td>1.387 V</td>
<td>RuO<sub>4</sub> + 4H<sup>+</sup> + 4e<sup>−</sup></td>
<td>↔ RuO<sub>2</sub> + 2H<sub>2</sub>O</td>
</tr></tbody></table>""",
        "Isotopes": """<p>Natural ruthenium is a blend of seven stable isotopes: <sup>96</sup>Ru, <sup>98</sup>Ru, <sup>99</sup>Ru, <sup>100</sup>Ru, <sup>101</sup>Ru, <sup>102</sup>Ru, and <sup>104</sup>Ru, with <sup>102</sup>Ru being the most abundant at 31.6%. Scientists have also synthesized 34 radioactive isotopes ranging from <sup>85</sup>Ru to <sup>125</sup>Ru. Most of these radioisotopes decay in under five minutes, though a few have longer half-lives: <sup>106</sup>Ru (371.8 days), <sup>103</sup>Ru (39.25 days), <sup>97</sup>Ru (2.84 days), <sup>94</sup>Ru (51.8 minutes), <sup>95</sup>Ru (1.61 hours), and <sup>105</sup>Ru (4.44 hours). Isotopes lighter than <sup>102</sup>Ru decay primarily by electron capture into <a href="/school1/ptable/element/Tc" class="wiki-link" title="Technetium (Tc)">technetium</a>, while heavier isotopes undergo beta emission to form <a href="/school1/ptable/element/Rh" class="wiki-link" title="Rhodium (Rh)">rhodium</a>.</p>
<p>The radioactive isotope <b>Ruthenium-106 (<sup>106</sup>Ru)</b> is produced during the nuclear fission of <a href="/school1/ptable/element/U" class="wiki-link" title="Uranium (U)">uranium</a> and <a href="/school1/ptable/element/Pu" class="wiki-link" title="Plutonium (Pu)">plutonium</a> inside nuclear reactors. In late 2017, trace amounts of airborne <sup>106</sup>Ru detected over Europe were linked to an unacknowledged nuclear reprocessing incident in Russia.</p>
<p>In India, <sup>106</sup>Ru has been harnessed for a life-saving medical milestone. Scientists at the <b>Bhabha Atomic Research Centre (BARC)</b> developed an indigenous technology to recover radioactive Ru-106 from spent nuclear fuel waste. BARC electro-deposits the radioisotope onto silver discs to create custom ocular radiotherapy plaques. Successfully deployed at premier medical institutions like AIIMS, New Delhi, these Indian-made Ru-106 plaques provide affordable, world-class radiotherapy for eye cancers (such as uveal melanoma), freeing Indian hospitals from reliance on expensive imported radiation sources.</p>""",
        "Occurrence": """<p>Ruthenium is one of the rarest elements in the Earth's crust, ranking 78th in abundance with a concentration of just 100 parts per trillion (0.0000001%). Native ruthenium—where <a href="/school1/ptable/element/Ir" class="wiki-link" title="Iridium (Ir)">iridium</a> partially replaces ruthenium in its crystal lattice—is an exceptionally rare natural mineral. Globally, ruthenium occurs locked alongside other platinum group metals (PGMs) in platinum ores found in the Ural Mountains of Russia, North and South America, Sudbury (Ontario, Canada) nickel-copper pentlandite deposits, and Bushveld pyroxenite formations in South Africa.</p>
<p>In India, Platinum Group Elements (PGEs) including ruthenium are primarily associated with chromite deposits in ancient Precambrian rock formations. Through extensive surveys, the <b>Geological Survey of India (GSI)</b> identified the nation's premier PGE deposit in the <b>Baula-Nuasahi ultramafic complex</b> in Keonjhar district, Odisha—a region holding approximately 68% of India's total identified platinum-group potential. Additional PGE-bearing geological sites have been mapped in the <b>Sittampundi Anorthosite Complex</b> in Namakkal district, Tamil Nadu, and the Shivamogga schist belt in Karnataka.</p>
<p>To achieve national self-reliance under the <i>Aatmanirbhar Bharat</i> initiative and secure critical mineral supply chains, the Odisha Mining Corporation (OMC) in partnership with CSIR-IMMT (Institute of Minerals and Materials Technology) established India's first pilot plant in 2026 to extract PGMs, including ruthenium, directly from domestic chromite mining ores and tailings.</p>""",
        "History": """<p>Naturally occurring platinum alloys containing all six platinum-group metals were fashioned into artifacts by pre-Columbian South Americans for centuries. European chemists encountered these heavy, unmelting sands in the 16th century, though pure platinum was only recognized as an element in the mid-18th century. By the 1820s, rich alluvial platinum sands discovered in Russia's Ural Mountains were used to mint imperial rouble coins and medals.</p>
<p>The quest to isolate element 44 was filled with scientific twists. In 1807, Polish chemist Jędrzej Śniadecki claimed to extract a new metal from South American platinum ore, calling it <i>vestium</i> after the newly discovered asteroid Vesta. When other scientists failed to confirm his findings, Śniadecki withdrew his claim in 1808. In 1827, Swedish chemist Jöns Jacob Berzelius and German-Russian chemist Gottfried Osann examined aqua-regia-insoluble residues from Ural platinum. Osann believed he had found three new metals, proposing the names <i>pluranium</i>, <i>ruthenium</i>, and <i>polinium</i>. Unable to isolate them consistently, Osann relinquished his claim, but his suggested name <i>ruthenium</i>—derived from <i>Ruthenia</i>, the Latin name for Russia—remained in chemical lore.</p>
<p>The true discovery of ruthenium occurred in 1844. <b>Karl Ernst Claus</b>, a Russian chemist of Baltic German heritage working at Kazan University, proved that Osann's residues contained a genuinely new metal. Claus successfully isolated 6 grams of pure ruthenium from platinum waste left over from imperial rouble coin production. Claus officially named the element <b>Ruthenium</b> in honor of his motherland, establishing a historic scientific tradition of naming newly discovered chemical elements after countries.</p>""",
        "Applications": """<p>With global annual consumption around 31 tonnes, ruthenium is a compact industrial titan. Approximately 45% of world production goes into electronics, 25% into chemical catalysis, and 15% into electrochemistry. Its principal electronic application is in thick-film chip resistors, where ruthenium dioxide (RuO<sub>2</sub>) mixed with lead and bismuth ruthenates creates heat-stable resistive layers—accounting for half of global consumption. Additionally, electroplated or sputtered micro-layers of ruthenium provide wear-resistant coatings for electrical contacts as a cost-effective alternative to <a href="/school1/ptable/element/Rh" class="wiki-link" title="Rhodium (Rh)">rhodium</a>.</p>
<p>Ruthenium is a key alloy hardener. Adding just 0.1% ruthenium to <a href="/school1/ptable/element/Ti" class="wiki-link" title="Titanium (Ti)">titanium</a> dramatically enhances its corrosion resistance in harsh industrial environments. In aerospace engineering, ruthenium is alloyed into advanced single-crystal nickel superalloys (such as EPM-102 with 3% Ru, and TMS-162, TMS-138, and TMS-174 with up to 6% Ru) to enable jet engine turbine blades to withstand extreme temperatures and stress. Fountain pen connoisseurs cherish ruthenium alloys used in luxury nib tips, such as the famous 1944 Parker 51 "RU" nib (96.2% Ru, 3.8% Ir).</p>
<p>In industrial chemistry, ruthenium forms mixed-metal oxide (MMO) anodes used in cathodic protection of undersea pipelines and in chlor-alkali cells for generating <a href="/school1/ptable/element/Cl" class="wiki-link" title="Chlorine (Cl)">chlorine</a> from saltwater. Its fluorescent complexes are employed in optode sensors to measure oxygen levels. In biology, "ruthenium red" serves as a vital microscopic stain for pectin and nucleic acids, while volatile ruthenium tetroxide (RuO<sub>4</sub>) reacts with skin lipids to reveal latent fingerprints at crime scenes as dark brown RuO<sub>2</sub> deposits.</p>
<p>In modern Indian science and technology, ruthenium is driving groundbreaking innovations:</p>
<ul>
<li><b>Neuromorphic Computing (IISc):</b> Researchers at the Centre for Nano Science and Engineering (CeNSE) at the Indian Institute of Science (IISc), Bengaluru, developed a revolutionary molecular memristor using ruthenium complexes. Mimicking human biological synapses with 14-bit analog precision, this device enables ultra-low-power, high-accuracy edge artificial intelligence computing.</li>
<li><b>Green Hydrogen & Sustainable Catalysis:</b> Indian chemists across IISc, IITs, and CSIR laboratories are designing ruthenium-based single-atom electrocatalysts for efficient water splitting to produce clean green hydrogen, as well as catalysts converting industrial nitrate pollutants into valuable ammonia.</li>
<li><b>Next-Generation Cancer Therapeutics:</b> Research teams at IIT Indore and Banaras Hindu University (BHU) are synthesizing organometallic ruthenium "piano-stool" complexes and metallodendrimers. These ruthenium-centered drugs target tumor cells with high precision and lower toxicity, offering a promising alternative to traditional platinum-based chemotherapy drugs like cisplatin.</li>
</ul>"""
    }
}

target_path = r"c:\projects\apache\school1\src\ptable\data\drafts\Ruthenium.json"

with open(target_path, "w", encoding="utf-8") as f:
    json.dump(ruthenium_data, f, indent=2, ensure_ascii=False)

print(f"Successfully saved Ruthenium draft to {target_path}")
