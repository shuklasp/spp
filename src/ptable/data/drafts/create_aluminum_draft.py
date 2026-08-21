import json
import os

extract_html = """<p><b><a href="/school1/ptable/element/Al" class="wiki-link" title="Aluminum (Al)">Aluminum</a></b> (symbol <b>Al</b>, atomic number 13) is the lightweight champion of the metal world. Sitting in Group 13 of the periodic table, aluminum is a silvery-white, soft, non-magnetic, and remarkably flexible metal. It holds the proud title of being the third most abundant chemical element in Earth's crust—surpassed only by <a href="/school1/ptable/element/O" class="wiki-link" title="Oxygen (O)">oxygen</a> and <a href="/school1/ptable/element/Si" class="wiki-link" title="Silicon (Si)">silicon</a>—and the single most abundant metal on our planet, making up roughly 8.1% of Earth's solid surface. Weighing only one-third as much as steel, aluminum instantly forms a microscopic, self-healing shield of oxide when exposed to air, making it virtually immune to rust. From ancient Indian Ayurvedic remedies and vibrant textile dyes to high-speed trains, beverage cans, and ISRO space rockets, aluminum is an indispensable pillar of modern civilization.</p>"""

history_html = """<p>While metallic <a href="/school1/ptable/element/Al" class="wiki-link" title="Aluminum (Al)">aluminum</a> is a relatively modern discovery, human civilization has relied on natural aluminum compounds for thousands of years. In ancient India, potassium aluminum sulfate—commonly known as alum—held a revered position in science, medicine, and industry. Ancient Sanskrit texts, including classical <i>Rasaśāstra</i> (Ayurvedic mineral alchemy) treatises such as the 13th-century <i>Rasaratnasamuccaya</i> and the <i>Rasarnava</i>, refer to alum by names such as <i>Sphatika</i>, <i>Tuvari</i>, <i>Shubhra</i>, <i>Kankshi</i>, and <i>Saurashtri</i> (named after the mineral-rich clay deposits of Saurashtra in Gujarat). Alum was classified under the <i>Uparasa</i> category of vital minerals. Ayurvedic physicians processed raw alum through <i>Śodhana</i> (purification in organic liquids) and <i>Māraṇa</i> (calcination) to drive out its 24 molecules of crystallization water, creating a light, snow-white medicinal ash known as <i>Sphatika Bhasma</i>. Valued for its powerful <i>Raktastambhaka</i> (hemostatic) and astringent properties, <i>Sphatika Bhasma</i> was prescribed for stopping internal and external bleeding, healing stubborn wounds, and treating respiratory ailments. Across Indian households, alum (known locally as <i>Phitkari</i>) has also been used for centuries as a natural water clarifier—coagulating suspended silt particles in river water—and as an antiseptic facial splash after shaving.</p>

<p>Alum also drove ancient India's world-renowned textile empire. Ancient Indian dyers utilized <i>Saurashtri</i> (alum) as a critical chemical mordant—a substance that forms a molecular bridge between plant fibers and natural dyes. By treating cotton and silk with alum solutions, Indian artisans permanently fixed bright vegetable colors like <i>Manjistha</i> (Indian madder red) into fabrics, creating colorfast textiles that were prized from Rome to East Asia. In the Western world, alum was similarly cherished by ancient Egyptians, Greeks, and Romans for medicine and leather tanning. In 1787, French chemist Antoine Lavoisier deduced that alum's oxide base, alumina, contained a previously unknown metal with an intense grip on <a href="/school1/ptable/element/O" class="wiki-link" title="Oxygen (O)">oxygen</a>.</p>

<p>In the early 19th century, English chemist Sir Humphry Davy named the elusive element <i>alumium</i> (later adjusting it to <i>aluminum</i> and <i>aluminium</i>). However, isolating pure metallic aluminum proved extraordinarily difficult because it binds far more tightly to oxygen than <a href="/school1/ptable/element/Fe" class="wiki-link" title="Iron (Fe)">iron</a> or <a href="/school1/ptable/element/Cu" class="wiki-link" title="Copper (Cu)">copper</a>. Danish scientist Hans Christian Ørsted finally succeeded in isolating impure metallic aluminum in 1825 by reacting aluminum chloride with <a href="/school1/ptable/element/K" class="wiki-link" title="Potassium (K)">potassium</a> amalgam, and German chemist Friedrich Wöhler refined the process in 1827 to obtain pure metallic globules.</p>

<p>Because early chemical extraction required costly metallic <a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a>, aluminum was initially far more valuable than <a href="/school1/ptable/element/Au" class="wiki-link" title="Gold (Au)">gold</a> or <a href="/school1/ptable/element/Ag" class="wiki-link" title="Silver (Ag)">silver</a>. Emperor Napoleon III of France hosted state banquets where elite guests were given aluminum forks and spoons while lesser nobility ate with gold cutlery! In 1884, a 100-ounce pyramid of solid aluminum was placed atop the Washington Monument in the United States as a supreme symbol of industrial luxury and engineering prowess.</p>

<p>The aluminum age truly exploded in 1886 when American inventor Charles Martin Hall and French engineer Paul Héroult independently discovered that electrolyzing alumina dissolved in a molten salt bath of cryolite (Na₃AlF₆) yielded pure aluminum metal efficiently. Three years later, in 1889, Austrian chemist Carl Josef Bayer invented the Bayer process to economically extract pure alumina from raw bauxite ore. Together, the Bayer and Hall-Héroult processes slashed the price of aluminum overnight, converting a precious royal curiosity into the cornerstone of modern transportation, architecture, and flight.</p>"""

characteristics_html = """<p><a href="/school1/ptable/element/Al" class="wiki-link" title="Aluminum (Al)">Aluminum</a> is a post-transition metal characterized by an astonishing combination of lightness, flexibility, and strength. With a density of just 2.70 grams per cubic centimeter, aluminum weighs approximately one-third as much as <a href="/school1/ptable/element/Fe" class="wiki-link" title="Iron (Fe)">iron</a> or <a href="/school1/ptable/element/Cu" class="wiki-link" title="Copper (Cu)">copper</a>. In its purest state, it is soft, silvery-white, non-magnetic, and non-sparking. Aluminum is extraordinarily ductile and malleable, allowing it to be drawn into fine electrical wires or flattened into thin kitchen foils just 0.004 millimeters thick without tearing.</p>

<p>From an electrical and thermal perspective, aluminum is a stellar performer. Although its electrical conductivity is about 62% that of copper by volume, aluminum's low density means that a kilogram of aluminum conducts twice as much electricity as a kilogram of copper. This exceptional strength-to-weight efficiency makes aluminum the material of choice for high-voltage overhead power grids across the globe. Aluminum also conducts heat rapidly (thermal conductivity of 237 W/m·K), which is why it is used in everything from household cookware to heat sinks inside computers and electronic devices.</p>

<p>Chemically, an isolated <a href="/school1/ptable/element/Al" class="wiki-link" title="Aluminum (Al)">aluminum</a> atom has the electron configuration [Ne] 3s² 3p¹, readily relinquishing its three outer electrons to adopt a stable +3 oxidation state. Aluminum has a fierce chemical appetite for <a href="/school1/ptable/element/O" class="wiki-link" title="Oxygen (O)">oxygen</a>. When fresh aluminum metal is exposed to air, it reacts instantly to form an invisible, dense layer of aluminum oxide (Al₂O₃) only a few nanometers thick. This oxide film acts as a permanent, self-healing armor that seals the metal beneath, preventing oxygen and moisture from causing rust or deep corrosion. If the surface is scratched, the oxide layer instantly reforms in milliseconds.</p>

<p>Aluminum is amphoteric, meaning it reacts with both strong acids and strong bases. In acidic solutions, it dissolves to form aluminum salts while releasing <a href="/school1/ptable/element/H" class="wiki-link" title="Hydrogen (H)">hydrogen</a> gas; in strong alkaline solutions (such as caustic soda), it forms soluble aluminate complexes like [Al(OH)₄]⁻. While pure aluminum metal is relatively soft, alloying it with tiny amounts of <a href="/school1/ptable/element/Cu" class="wiki-link" title="Copper (Cu)">copper</a>, <a href="/school1/ptable/element/Mg" class="wiki-link" title="Magnesium (Mg)">magnesium</a>, <a href="/school1/ptable/element/Si" class="wiki-link" title="Silicon (Si)">silicon</a>, or <a href="/school1/ptable/element/Zn" class="wiki-link" title="Zinc (Zn)">zinc</a> dramatically increases its tensile strength to rival structural steel while preserving its lightweight advantage.</p>"""

isotopes_html = """<p>Naturally occurring <a href="/school1/ptable/element/Al" class="wiki-link" title="Aluminum (Al)">aluminum</a> is a monoisotopic and mononuclidic element. Virtually 100% of all aluminum found in Earth's rocks, soils, and oceans consists of a single stable isotope: <b>aluminum-27 (²⁷Al)</b>. Containing 13 protons and 14 neutrons, aluminum-27 was forged in the hearts of dying giant stars through proton-capture fusion cycles before being scattered across the cosmos in ancient supernova explosions.</p>

<p>In addition to stable ²⁷Al, nature contains trace amounts of an extraordinarily important radioactive isotope: <b>aluminum-26 (²⁶Al)</b>. With a half-life of roughly 717,000 years, ²⁶Al decays by positron emission or electron capture into stable <a href="/school1/ptable/element/Mg" class="wiki-link" title="Magnesium (Mg)">magnesium</a>-26 (²⁶Mg). On Earth, tiny amounts of ²⁶Al are continuously produced in the upper atmosphere when energetic cosmic rays bombard <a href="/school1/ptable/element/Ar" class="wiki-link" title="Argon (Ar)">argon</a> atoms, making it a valuable tracer for cosmic ray exposure and geological dating.</p>

<p>In planetary science and astrophysics, aluminum-26 plays a starring historical role. When our solar system formed 4.56 billion years ago, live ²⁶Al was abundant in the protoplanetary disk. As this radioactive isotope decayed, it released immense thermal energy—acting as the primary heat engine that melted the interiors of early asteroids and planetesimals. This internal heating allowed heavy metals like <a href="/school1/ptable/element/Fe" class="wiki-link" title="Iron (Fe)">iron</a> and nickel to sink to the center while lighter silicate minerals floated to the top, driving planetary differentiation across the early solar system.</p>

<p>In India, planetary scientists at the Physical Research Laboratory (PRL) in Ahmedabad utilize advanced high-precision mass spectrometry to measure excess ²⁶Mg produced by the decay of ²⁶Al inside primitive meteorites that fall across the Indian subcontinent. By studying Calcium-Aluminum-rich Inclusions (CAIs) in meteorites like the Efremovka and Kamargaon falls, Indian researchers calculate the precise timing of early solar system events down to a resolution of a few hundred thousand years, shedding light on the birth of planets.</p>"""

occurrence_html = """<p><a href="/school1/ptable/element/Al" class="wiki-link" title="Aluminum (Al)">Aluminum</a> is the third most abundant element in Earth's crust (comprising roughly 8.1% by mass), surpassed only by <a href="/school1/ptable/element/O" class="wiki-link" title="Oxygen (O)">oxygen</a> (46.6%) and <a href="/school1/ptable/element/Si" class="wiki-link" title="Silicon (Si)">silicon</a> (27.7%). Because aluminum has an intense chemical affinity for oxygen, it never occurs naturally as a free native metal under normal conditions. Instead, it is locked inside hundreds of widespread minerals, including feldspars, micas, garnets, and clay minerals like kaolinite.</p>

<p>The primary commercial ore for aluminum extraction is <b>bauxite</b>—a clay-like sedimentary rock formed by millions of years of intense tropical weathering of aluminous rocks under humid monsoonal climates. Bauxite consists primarily of aluminum hydroxide minerals such as gibbsite [Al(OH)₃], boehmite [γ-AlO(OH)], and diaspore [α-AlO(OH)], mixed with iron oxides, silica, and titania.</p>

<p>India is blessed with vast, high-grade bauxite reserves, ranking fifth globally in total bauxite resources. The state of <b>Odisha</b> is the unquestioned heartland of Indian aluminum, holding over half of the country's total bauxite deposits. The majestic <b>Panchpatmali hills</b> in Koraput district, Odisha, contain one of the largest contiguous plateau bauxite deposits in the world. Operations at Panchpatmali are managed by the National Aluminium Company Limited (NALCO), a premier Navratna public sector enterprise that extracts bauxite to feed its massive alumina refinery at Damanjodi and smelter at Angul. Other major bauxite-rich regions in India include the East Coast bauxite belt spanning Odisha and Andhra Pradesh, the Maikala hill range in Chhattisgarh and Madhya Pradesh, the Ranchi plateau in Jharkhand, and coastal belts in Gujarat and Maharashtra.</p>

<p>Extracting pure aluminum metal requires a two-step industrial process: first, bauxite is refined into pure alumina (Al₂O₃) using hot caustic soda via the Bayer process; second, alumina is dissolved in molten cryolite at 950°C and electrolyzed using massive electric currents via the Hall-Héroult process. Because primary aluminum smelting demands substantial electrical energy (~13–15 kilowatt-hours per kilogram), recycling aluminum offers incredible environmental benefits. Recycling aluminum requires only <b>5% of the energy</b> needed to extract new metal from bauxite ore and produces zero loss in metal quality, making aluminum one of the most sustainable and infinitely recyclable materials on Earth.</p>"""

applications_html = """<p><a href="/school1/ptable/element/Al" class="wiki-link" title="Aluminum (Al)">Aluminum</a> is the world's most widely used non-ferrous metal, serving as an essential building block across modern transportation, energy, architecture, packaging, and aerospace. In everyday life, aluminum foil seals food freshness, beverage cans offer lightweight recyclability, and sleek aluminum window frames provide structural durability without rusting. In the electrical sector, Aluminum Conductor Steel Reinforced (ACSR) cables form the backbone of national power distribution grids, carrying high-voltage electricity across thousands of kilometers.</p>

<p>In modern transportation and defense, aluminum's lightweight strength drives energy efficiency. Indian Railways is undergoing a major technological transformation with the introduction of indigenous <b>aluminum Vande Bharat trainsets</b>. Engineered with lightweight aluminum alloy bodies, these modern trains reduce overall vehicle weight by 20% to 30% compared to traditional steel coaches. This weight reduction leads to faster acceleration, lower energy consumption, reduced track wear, and complete freedom from rust during heavy monsoons.</p>

<p>India's high-tech aerospace sector relies heavily on advanced aluminum metallurgy. The Indian Space Research Organisation (ISRO) uses specialized high-strength aluminum alloys—such as AA2219, 2000-series, 7000-series, and advanced aluminum-lithium (Al-Li) alloys—developed by the Vikram Sarabhai Space Centre (VSSC) in Thiruvananthapuram and the Liquid Propulsion Systems Centre (LPSC) in Valiamala, fabricated in partnership with Hindustan Aeronautics Limited (HAL). These cryogenic-tolerant alloys form the massive liquid propellant tanks and structural fuselages for ISRO's PSLV, GSLV Mark III (LVM3), and the Next Generation Launch Vehicle (NGLV), carrying Indian satellites and scientific payloads into orbit.</p>

<p>Scientific innovation in aluminum is led by institutions like the <b>Jawaharlal Nehru Aluminium Research Development and Design Centre (JNARDDC)</b> in Nagpur—an autonomous Centre of Excellence under the Ministry of Mines that pioneers bauxite beneficiation, advanced alloy development, and eco-friendly utilization of "red mud" (bauxite byproduct) into green building materials. Simultaneously, CSIR-Advanced Materials and Processes Research Institute (CSIR-AMPRI) in Bhopal develops ultra-light aluminum matrix composites and metallic foams for structural noise damping and defense armor. Combining ancient roots in Indian medicine and crafts with cutting-edge space technology, aluminum continues to shape the future of human innovation.</p>"""

aluminum_data = {
  "symbol": "Al",
  "name": "Aluminum",
  "atomic": 13,
  "category": "post-transition metal",
  "description": "Chemical element with atomic number 13 (Al)",
  "extract_html": extract_html,
  "local_image": "https://upload.wikimedia.org/wikipedia/commons/3/3e/Aluminium.jpg",
  "atomic_mass": 26.98153857,
  "density": 2.7,
  "melt": 933.47,
  "boil": 2743,
  "phase": "Solid",
  "discovered_by": "Hans Christian Ørsted",
  "electron_configuration": "1s2 2s2 2p6 3s2 3p1",
  "electronegativity_pauling": 1.61,
  "electron_affinity": 41.762,
  "ionization_energies": [
    577.5,
    1816.7,
    2744.8,
    11577,
    14842,
    18379,
    23326,
    27465,
    31853,
    38473,
    42647,
    201266,
    222316
  ],
  "sections": {
    "History": history_html,
    "Characteristics": characteristics_html,
    "Isotopes": isotopes_html,
    "Occurrence": occurrence_html,
    "Applications": applications_html
  }
}

target_path = r"c:\projects\apache\school1\src\ptable\data\drafts\Aluminum.json"
os.makedirs(os.path.dirname(target_path), exist_ok=True)

with open(target_path, "w", encoding="utf-8") as f:
    json.dump(aluminum_data, f, indent=2, ensure_ascii=False)

print(f"Successfully generated draft for Aluminum at {target_path}")
