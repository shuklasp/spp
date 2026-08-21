import json
import os

extract_html = """<p><b><a href="/school1/ptable/element/Zr" class="wiki-link" title="Zirconium (Zr)">Zirconium</a></b> (symbol <b>Zr</b>, atomic number <b>40</b>) is a lustrous, greyish-white transition metal famous for its incredible resistance to corrosion, exceptionally high melting point, and vital role in nuclear energy. Named after the ancient mineral zircon (derived from the Persian word <i>zargun</i>, meaning "gold-colored"), zirconium is solid at room temperature, soft, and malleable when pure. Because it has an exceptionally low absorption rate for neutrons, zirconium alloys—known as Zircaloy—are indispensable for housing nuclear fuel rods in atomic power stations worldwide. From high-grade ceramic tiles and heat-resistant furnace crucibles to spark-dazzling pyrotechnics and sparkling cubic zirconia gemstones, zirconium bridges the gap between ancient geology, high-tech energy, and everyday beauty. In India, rich zircon beach sands along the coasts of Kerala, Odisha, and Tamil Nadu are processed indigenously by the Nuclear Fuel Complex (NFC) to power the nation's nuclear energy program, while ancient zircon crystals held within Indian rocks serve as deep-time capsules recording the earliest history of Earth's crust.</p>"""

characteristics_html = """</div>
<figure class="mw-default-size" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/a5d92a3aa5aa2e12f9913d058d56accd.jpg" decoding="async" width="250" height="195" class="mw-file-element" data-file-width="665" data-file-height="520" /><figcaption><a href="/school1/ptable/element/Zr" class="wiki-link" title="Zirconium (Zr)">Zirconium</a> rod</figcaption></figure>
<p><b>Physical Properties:</b></p>
<p><a href="/school1/ptable/element/Zr" class="wiki-link" title="Zirconium (Zr)">Zirconium</a> is a shiny, greyish-white metal that closely resembles <a href="/school1/ptable/element/Hf" class="wiki-link" title="Hafnium (Hf)">hafnium</a> and <a href="/school1/ptable/element/Ti" class="wiki-link" title="Titanium (Ti)">titanium</a>. When exceptionally pure, zirconium is soft, ductile, and malleable, meaning it can be easily bent, drawn into thin wires, or hammered into sheets. However, if even trace amounts of impurities (like oxygen or carbon) are present, the metal becomes quite hard and brittle. With a density of 6.52 g/cm³, it is moderately dense and possesses an extraordinarily high melting point of 1,855 °C (3,371 °F / 2,128 K) and a boiling point of 4,409 °C (7,968 °F / 4,650 K).</p>
<p>Zirconium exhibits a low electronegativity of 1.33 on the Pauling scale, making it the fourth lowest among all d-block transition metals (after hafnium, yttrium, and lutetium). In fine powder form, metallic zirconium is highly flammable and pyrophoric (spontaneously igniting in air), though solid blocks of the metal are completely safe and very difficult to ignite. When alloyed with <a href="/school1/ptable/element/Zn" class="wiki-link" title="Zinc (Zn)">zinc</a>, zirconium becomes magnetic at ultra-cold temperatures below 35 K.</p>
<p>At room temperature, zirconium exists in a hexagonal close-packed crystal structure known as <b>alpha-zirconium (α-Zr)</b>. When heated above 863 °C (1,585 °F), its crystal lattice transforms into a body-centered cubic arrangement called <b>beta-zirconium (β-Zr)</b>, which remains stable all the way up to its melting point.</p>
<p><b>Chemical Properties & Passivation Armor:</b></p>
<p>Zirconium's defining chemical superpower is its incredible resistance to chemical corrosion. Just like titanium, when fresh zirconium is exposed to air or moisture, it rapidly reacts with <a href="/school1/ptable/element/O" class="wiki-link" title="Oxygen (O)">oxygen</a> to form a microscopic, self-healing protective layer of zirconium dioxide (ZrO₂). This skin of passivation renders zirconium virtually immune to attack by harsh alkalis, seawater, organic acids, and dilute mineral acids.</p>
<p>However, zirconium can be dissolved by hydrofluoric acid (HF), as well as hot concentrated hydrochloric acid (HCl) and sulfuric acid (H₂SO₄), especially when <a href="/school1/ptable/element/F" class="wiki-link" title="Fluorine (F)">fluorine</a> ions are present. Chemically, zirconium and hafnium are "chemical twins"—because of a phenomenon called the <i>lanthanide contraction</i>, their atomic and ionic radii are almost identical, giving them nearly indistinguishable chemical properties and making their industrial separation exceptionally challenging.</p>
<div class="mw-heading mw-heading3">"""

isotopes_html = """</div>
<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r1368532237" /><div role="note" class="hatnote navigation-not-searchable">Main article: Isotopes of <a href="/school1/ptable/element/Zr" class="wiki-link" title="Zirconium (Zr)">zirconium</a></div>
<p><b>Naturally Occurring Isotopes:</b></p>
<p>Natural <a href="/school1/ptable/element/Zr" class="wiki-link" title="Zirconium (Zr)">zirconium</a> found on Earth is composed of five isotopes: <b>⁹⁰Zr</b>, <b>⁹¹Zr</b>, <b>⁹²Zr</b>, <b>⁹⁴Zr</b>, and <b>⁹⁶Zr</b>. Among these, the first four are completely stable. By far the most abundant is <b>zirconium-90 (⁹⁰Zr)</b>, which accounts for 51.45% of all zirconium in nature. The remaining stable isotopes make up 11.22% (⁹¹Zr), 17.15% (⁹²Zr), and 17.38% (⁹⁴Zr).</p>
<p>The fifth natural isotope, <b>zirconium-96 (⁹⁶Zr)</b>, is primordial and faintly radioactive, comprising 2.80% of natural abundance. It undergoes rare double beta decay with an astonishing half-life of 2.34 × 10¹⁹ years (23.4 quintillion years)—a duration more than 1.6 billion times older than the age of the entire universe! Theoretical physics suggests that zirconium-94 (⁹⁴Zr) may also eventually undergo double beta decay, though this has not yet been experimentally observed.</p>
<p><b>Synthetic Radioisotopes & Nuclear Decays:</b></p>
<p>Nuclear scientists have artificially created 38 radioactive isotopes of zirconium in particle accelerators, spanning mass numbers from <b>⁷⁷Zr</b> to <b>¹¹⁴Zr</b>, alongside 13 nuclear isomers. The most stable artificial radioisotope is <b>zirconium-93 (⁹³Zr)</b>, a long-lived nuclear fission product with a half-life of 1.61 million years.</p>
<p>Radioactive zirconium isotopes decay along clear paths depending on their atomic mass: isotopes lighter than mass 90 decay via positron emission or electron capture to transform into isotopes of <a href="/school1/ptable/element/Y" class="wiki-link" title="Yttrium (Y)">yttrium</a> (Y), while isotopes heavier than mass 92 decay via beta emission to become isotopes of <a href="/school1/ptable/element/Nb" class="wiki-link" title="Niobium (Nb)">niobium</a> (Nb).</p>
<div class="mw-heading mw-heading3">"""

occurrence_html = """</div>
<figure class="mw-default-size" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/19ba2dc86f9c78941c0f9fe445198179.PNG" decoding="async" width="250" height="110" class="mw-file-element" data-file-width="1425" data-file-height="625" /><figcaption><a href="/school1/ptable/element/Zr" class="wiki-link" title="Zirconium (Zr)">Zirconium</a> output in 2005</figcaption></figure>
<p><b>Geological Abundance & Mining:</b></p>
<p><a href="/school1/ptable/element/Zr" class="wiki-link" title="Zirconium (Zr)">Zirconium</a> is the 18th most abundant element in Earth's crust, with an average concentration of about 130 to 165 parts per million (ppm). This makes zirconium far more plentiful than well-known metals like <a href="/school1/ptable/element/Cu" class="wiki-link" title="Copper (Cu)">copper</a>, <a href="/school1/ptable/element/Ni" class="wiki-link" title="Nickel (Ni)">nickel</a>, or <a href="/school1/ptable/element/Pb" class="wiki-link" title="Lead (Pb)">lead</a>! Because it reacts greedily with oxygen and silica, pure metallic zirconium is never found free in nature. Instead, it occurs across more than 140 known minerals, the most commercially significant being <b>zircon</b> (zirconium silicate, ZrSiO₄) and <b>baddeleyite</b> (pure zirconium dioxide, ZrO₂), along with eudialyte.</p>
<p>Most commercial zircon is extracted as a co-product during the mining of heavy mineral beach sands for <a href="/school1/ptable/element/Ti" class="wiki-link" title="Titanium (Ti)">titanium</a> ores (ilmenite and rutile) and <a href="/school1/ptable/element/Sn" class="wiki-link" title="Tin (Sn)">tin</a>. Zircon sand is separated from lighter quartz particles using spiral gravity concentrators, after which high-intensity magnetic separation extracts titanium minerals. While raw zircon mineral ore is relatively inexpensive ($360 to $840 per tonne historically), pure unwrought zirconium metal is much more costly ($22,700 to $39,900 per ton) because extracting metallic zirconium from zircon sand requires complex, energy-intensive chemical reduction via the Kroll process using molten <a href="/school1/ptable/element/Mg" class="wiki-link" title="Magnesium (Mg)">magnesium</a>.</p>
<p><b>Indian Mineral Wealth & Coastline Geography:</b></p>
<p>India possesses vast reserves of heavy mineral beach sands exceptionally rich in high-grade zircon. State-owned enterprise <b>IREL (India) Limited</b> (formerly Indian Rare Earths Limited) and <i>Kerala Minerals and Metals Limited (KMML)</i> mine and process zircon sand across major coastal belts:</p>
<ul>
  <li><b>Chavara Coastal Belt (Kollam, Kerala):</b> World-famous for high-purity zircon and ilmenite sand deposits along the Arabian Sea.</li>
  <li><b>Manavalakurichi (Kanyakumari, Tamil Nadu):</b> Rich coastal deposits mined for heavy mineral sands.</li>
  <li><b>OSCOM Plant, Chatrapur & Brahmagiri (Ganjam, Odisha):</b> Massive mineral sand operation run by IREL along the Bay of Bengal coast.</li>
  <li><b>Bhavanapadu & Srikakulam (Andhra Pradesh):</b> Extensive coastal sand deposits holding high concentrations of zircon silicate.</li>
</ul>
<p><b>Ancient Zircon Geochronology in Indian Geology:</b></p>
<p>Zircon crystals are nature's ultimate "time capsules." Because zircon tightly traps uranium atoms while strictly excluding lead during crystallization, geologists measure uranium-to-lead radioisotope ratios inside ancient zircon crystals to date the exact age of Earth's rock formations. In India, geologists have discovered Archean zircons in the <b>Singhbhum Craton</b> (spanning Odisha and Jharkhand) and the <b>Dharwar Craton</b> (Karnataka) dated to over <b>3.5 billion years old</b>! Furthermore, Hadean-aged zircon grains recovered from the Baitarani river basin in Odisha and Wayanad in Kerala provide key scientific evidence that fragments of Earth's earliest continental crust formed on the Indian subcontinent over 4 billion years ago.</p>
<div class="mw-heading mw-heading2">"""

history_html = """</div>
<p><b>Ancient History & Etymology:</b></p>
<p>The name <b>zirconium</b> is derived from its primary mineral, <i>zircon</i>, which traces back to the ancient Persian word <i>zargun</i> (زرگون), meaning "gold-colored" or "like gold" (from <i>zar</i> meaning gold, and <i>gun</i> meaning color). Transparent, gem-quality varieties of zircon—historically known as <i>jargoon</i>, <i>jacinth</i>, <i>hyacinth</i>, or <i>ligure</i>—have been admired and traded since antiquity, and are mentioned in ancient biblical and classical texts.</p>
<p><b>Vedic Gemology & Ancient Indian Traditions:</b></p>
<p>In ancient Indian lapidary sciences (<i>Ratnashastra</i>) and Vedic astrology, yellow, brown, and honey-colored zircons were revered under the Sanskrit name <b>Gomedaka</b> (or <i>Gomed</i>). Classified as one of the sacred gemstones of the <b>Navaratna</b> (the nine cosmic gems), <i>Gomedaka</i> was traditionally associated with the shadow planet <b>Rahu</b> (the North Node of the Moon). Texts such as the <i>Garuda Purana</i> and <i>Agastimata</i> describe the qualities and origins of <i>Gomedaka</i>. In the traditional Indian alchemical and medical system of <b>Rasashastra</b> (as documented in texts like the <i>Rasaratnasamuccaya</i>), gemstone minerals underwent a meticulous multi-step purification process called <i>Shodhana</i> to prepare therapeutic mineral preparations (<i>Bhasmas</i>) used in Ayurvedic healing traditions.</p>
<p><b>Scientific Discovery & Isolation:</b></p>
<p>Zircon was long assumed to be a compound of known earths until 1789, when Prussian chemist <b>Martin Heinrich Klaproth</b> analyzed a sample of jargoon from Ceylon (modern-day Sri Lanka). Klaproth discovered a previously unknown chemical earth (oxide) and named it <i>Zirkonerde</i> (zirconia). In 1808, English scientist Sir Humphry Davy attempted to isolate the new metal using electrolysis, but was unsuccessful.</p>
<p>The element was first isolated in an impure metallic form in <b>1824</b> by Swedish chemist <b>Jöns Jacob Berzelius</b>, who heated a mixture of potassium and potassium zirconium fluoride inside an iron tube. However, producing pure, ductile zirconium metal suitable for industrial use remained a challenge for another century. In 1925, Dutch chemists Anton Eduard van Arkel and Jan Hendrik de Boer invented the <b>Crystal Bar Process</b> (Iodide Process), which purified metal by thermally decomposing zirconium tetraiodide (ZrI₄) vapour over a red-hot filament. In 1945, Luxembourgish metallurgist William Justin Kroll developed the far more economical <b>Kroll process</b>, reducing zirconium tetrachloride (ZrCl₄) vapour with molten <a href="/school1/ptable/element/Mg" class="wiki-link" title="Magnesium (Mg)">magnesium</a> metal:</p>
<dl><dd><span class="chemf nowrap">ZrCl<sub>4</sub> + 2 Mg → Zr + 2 MgCl<sub>2</sub></span></dd></dl>
<p>The Kroll process remains the leading commercial technology used to manufacture zirconium sponge today.</p>
<div class="mw-heading mw-heading2">"""

applications_html = """</div>
<p>Although much of the world's <a href="/school1/ptable/element/Zr" class="wiki-link" title="Zirconium (Zr)">zirconium</a> is hidden within high-tech industrial alloys and specialized ceramics, the element plays an indispensable role in nuclear power, industrial manufacturing, luxury jewelry, and aerospace engineering:</p>
<ul>
  <li><b>Ceramics & Refractories (ZrSiO₄ & ZrO₂):</b> Over 90% of all mined zircon mineral is consumed directly in high-temperature applications. Because zircon is extremely refractory, hard, and chemically inert, its primary use is as an opacifier in ceramic glazes—imparting a brilliant white, durable appearance to floor tiles, sanitaryware, and fine china. Zirconium dioxide (zirconia, ZrO₂) is used to make high-temperature laboratory crucibles, furnace linings, glass-melting moulds, ceramic knives, and ultra-tough abrasives like grinding wheels and sandpaper.</li>
  <li><b>Jewelry & Gemstones (Cubic Zirconia):</b> Natural gem-quality zircon (ZrSiO₄) has been cut into sparkling gems for centuries. Synthetic <b>cubic zirconia</b> (a crystalline form of zirconium dioxide, ZrO₂) is world-famous as a durable, highly refractive, affordable diamond substitute in fine jewelry.</li>
  <li><b>Nuclear Reactor Cladding & Fuel Assemblies (Zircaloy):</b> Metallic zirconium is the ultimate material for nuclear energy! Pure zirconium has an extraordinarily low absorption cross-section for thermal neutrons, meaning neutrons pass right through it without being absorbed. Zirconium alloys (such as <b>Zircaloy-2</b>, <b>Zircaloy-4</b>, and <b>Zr-2.5%Nb</b>) are used to fabricate fuel cladding tubes, pressure tubes, and structural grids that safely house uranium fuel pellets in nuclear power reactors. Because <a href="/school1/ptable/element/Hf" class="wiki-link" title="Hafnium (Hf)">hafnium</a> absorbs neutrons extremely strongly, nuclear-grade zirconium must be thoroughly purified to reduce hafnium content to less than 100 parts per million.</li>
  <li><b>Chemical Processing & Special Metallurgy:</b> Due to its supreme corrosion resistance against acids and alkalis, metallic zirconium is used to manufacture chemical reactors, pumps, heat exchangers, valves, surgical tools, and premium watch cases.</li>
  <li><b>Pyrotechnics, Flashbulbs & Vacuum Getters:</b> Zirconium powder burns with blinding, intense white sparks. It is used in pyrotechnic fireworks, explosive primers, photographic flashbulbs, and as a "getter" (degassing agent) to absorb residual oxygen and nitrogen inside vacuum tubes and electron grid supports.</li>
</ul>
<p><b>Modern Indian Scientific & Industrial Leadership (NFC & DAE):</b></p>
<p>India holds a position of global distinction in nuclear-grade zirconium metallurgy. To ensure complete self-reliance in nuclear power generation, India established the <b>Nuclear Fuel Complex (NFC)</b> in Hyderabad in 1971 under the <b>Department of Atomic Energy (DAE)</b>:</p>
<ul>
  <li><b>Integrated Ore-to-Core Manufacturing:</b> NFC Hyderabad is one of the only integrated facilities in the world that converts raw zircon beach sand (mined by IREL) all the way into nuclear-grade zirconium oxide, zirconium sponge, and finished Zircaloy cladding tubes under a single roof. NFC supplies the fuel assemblies and core components for all of India's Pressurised Heavy Water Reactors (PHWRs) and research reactors.</li>
  <li><b>Indigenous Hafnium Separation (BARC):</b> Scientists at the <b>Bhabha Atomic Research Centre (BARC)</b> developed indigenous liquid-liquid solvent extraction techniques to separate hafnium from zirconium down to nuclear-grade purity (< 100 ppm Hf), securing India's strategic nuclear autonomy.</li>
  <li><b>Expansion to Pazhayakayal & Kota:</b> To support India's expanding nuclear power capacity, DAE commissioned the specialized <b>Zirconium Complex (ZC)</b> at Pazhayakayal in Tamil Nadu for large-scale zirconium sponge production, along with the NFC-Kota facility in Rajasthan.</li>
  <li><b>Space & Advanced Research (ISRO & IGCAR):</b> Zirconium alloys and zirconia thermal barrier coatings are actively developed by ISRO for rocket motor nozzles and by the <i>Indira Gandhi Centre for Atomic Research (IGCAR)</i> at Kalpakkam for sodium-cooled fast breeder reactors.</li>
</ul>
<div class="mw-heading mw-heading3">"""

zirconium_data = {
    "symbol": "Zr",
    "name": "Zirconium",
    "atomic": 40,
    "category": "transition metal",
    "description": "Corrosion-resistant, high-melting transition metal (Zr, atomic number 40) crucial for nuclear power reactors, ceramics, and gemstones",
    "extract_html": extract_html,
    "local_image": "https://upload.wikimedia.org/wikipedia/commons/1/1d/Zirconium-pieces.jpg",
    "atomic_mass": 91.2242,
    "density": 6.52,
    "melt": 2128,
    "boil": 4650,
    "phase": "Solid",
    "discovered_by": "Martin Heinrich Klaproth",
    "electron_configuration": "1s2 2s2 2p6 3s2 3p6 4s2 3d10 4p6 5s2 4d2",
    "electronegativity_pauling": 1.33,
    "electron_affinity": 41.806,
    "ionization_energies": [
        640.1,
        1270,
        2218,
        3313,
        7752,
        9500
    ],
    "sections": {
        "Characteristics": characteristics_html,
        "Isotopes": isotopes_html,
        "Occurrence": occurrence_html,
        "History": history_html,
        "Applications": applications_html
    }
}

target_file = r"c:\projects\apache\school1\src\ptable\data\drafts\Zirconium.json"
os.makedirs(os.path.dirname(target_file), exist_ok=True)
with open(target_file, "w", encoding="utf-8") as f:
    json.dump(zirconium_data, f, indent=2, ensure_ascii=False)

print("Successfully generated Zirconium.json!")
