import json
import os

extract_html = """<p><b><a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">Vanadium</a></b> (symbol <b>V</b>, atomic number 23) is a hard, silvery-grey, ductile transition metal celebrated for its extraordinary strength-enhancing abilities and vibrant chemical versatility. Rarely found as a free metal in nature, vanadium forms a protective oxide surface layer (passivation) that shields it against corrosion by air, water, and acids. Though named after <i>Vanadís</i>, the Norse goddess of beauty and fertility, due to the dazzling colors of its chemical compounds, vanadium has a rich heritage in metallurgy—from trace inclusions in ancient Indian crucible Wootz steel to modern superalloys in supersonic aircraft, industrial catalysts, and long-duration vanadium flow batteries storing clean renewable energy.</p>"""

history_html = """<p><a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">Vanadium</a> has a colorful discovery history filled with mistaken identities, scientific rivalry, and ancient metallurgical mastery. The element was first discovered in 1801 by Spanish mineralogist Andrés Manuel del Río while working in Mexico City. Del Río extracted the element from a sample of Mexican "brown lead" ore (now known as vanadinite, Pb₅(VO₄)₃Cl). Intrigued by the brilliant red, yellow, and blue colors of its chemical salts, he initially named the element <i>panchromium</i> (Greek for "all colors") and later renamed it <i>erythronium</i> (Greek for "red") because its salts turned bright red upon heating. However, in 1805, French chemist Hippolyte Victor Collet-Descotils—supported by del Río's close friend, the famous explorer Baron Alexander von Humboldt—incorrectly claimed that del Río's discovery was merely an impure sample of <a href="/school1/ptable/element/Cr" class="wiki-link" title="Chromium (Cr)">chromium</a>. Doubting his own work, del Río accepted their verdict and withdrew his claim.</p>
<p>Thirty years later, in 1831, Swedish chemist Nils Gabriel Sefström rediscovered the mysterious metal inside a newly analyzed sample of iron ore from Taberg, Sweden. Wanting a name starting with the letter V, Sefström named the element <i>vanadium</i> after <i>Vanadís</i>, an Old Norse name for Freyja, the Scandinavian goddess of beauty and fertility, inspired by the gorgeous colors of its chemical compounds. Later that same year, German chemist Friedrich Wöhler rigorously analyzed both samples and conclusively proved that Sefström's vanadium was identical to del Río's erythronium. Although geologist George William Featherstonhaugh suggested renaming the element <i>rionium</i> to honor del Río's original discovery, the name vanadium stuck worldwide.</p>
<p>Isolating pure metallic vanadium proved exceptionally difficult because vanadium eagerly reacts with carbon, oxygen, and nitrogen at high temperatures. In 1831, Swedish chemist Jöns Jakob Berzelius believed he had isolated pure vanadium metal, but British chemist Sir Henry Enfield Roscoe demonstrated in 1867 that Berzelius had actually produced vanadium nitride (VN). Roscoe became the first scientist to isolate relatively pure vanadium metal by reducing vanadium(II) chloride (VCl₂) with <a href="/school1/ptable/element/H" class="wiki-link" title="Hydrogen (H)">hydrogen</a> gas. In 1927, American chemists J. W. Marden and M. N. Rich achieved high-purity (99.9%) metallic vanadium by reducing vanadium pentoxide (V₂O₅) with metallic <a href="/school1/ptable/element/Ca" class="wiki-link" title="Calcium (Ca)">calcium</a> inside a sealed steel bomb.</p>
<p>The industrial revolution transformed vanadium into a strategic commodity. In 1905, after studying high-strength French racing cars, American automotive pioneer Henry Ford adopted vanadium steel for the chassis and crankshafts of the iconic Ford Model T automobile. Adding just a tiny fraction of vanadium doubled the steel's tensile strength while significantly reducing vehicle weight, making cars durable enough to handle rugged country roads. In the early 20th century, most of the world's vanadium was mined from the remote Minas Ragra patrónite deposit in Peru by the American Vanadium Company. Later, during the 1910s and 1920s, vanadium became widely available as a valuable byproduct during the extraction of <a href="/school1/ptable/element/U" class="wiki-link" title="Uranium (U)">uranium</a> from carnotite ore.</p>
<p>In biological history, German chemist Martin Henze made a startling discovery in 1911: he found that marine sea squirts (Ascidiacea) concentrate extraordinary amounts of vanadium in specialized blood cells called vanadocytes, unveiling vanadium's unique role in marine biochemistry.</p>
<p><b>Ancient Indian Crucible Steel (Wootz Steel):</b></p>
<p>Long before European chemists isolated vanadium, ancient Indian metallurgists unknowingly harnessed its power to forge the world's finest steel. From the 1st millennium BCE, ironsmiths in South India (across present-day Telangana, Andhra Pradesh, Tamil Nadu, and Karnataka) crafted high-carbon crucible steel known as <i>Wootz steel</i> (the precursor to legendary Damascus swords). Modern microscopic and chemical analyses by materials scientists have revealed that the local iron ores mined by ancient Indian smiths contained natural trace impurities of vanadium, <a href="/school1/ptable/element/Cr" class="wiki-link" title="Chromium (Cr)">chromium</a>, and molybdenum. During specialized high-temperature crucible smelting and repeated thermal forging, these vanadium trace atoms segregated into microscopic layers, forming submicroscopic networks of ultra-hard vanadium carbides embedded within the iron matrix. These vanadium carbide bands gave Wootz blades their legendary combination of extreme razor sharpness, flexibility, and their iconic "watered silk" wavy surface patterns (<i>Jauhar</i>). When the specific vanadium-bearing iron ore veins in South India were exhausted in the 19th century, the traditional secret of manufacturing authentic Damascus blades was temporarily lost to history.</p>"""

characteristics_html = """<figure class="mw-default-size" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/422c3b92c058c6ab86577238508cabb3.jpg" decoding="async" width="250" height="167" class="mw-file-element"  data-file-width="800" data-file-height="534" /><figcaption>Polycrystalline high-purity (99.95%) <a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">vanadium</a> cuboids, ebeam remelted and macro-etched</figcaption></figure>
<p><a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">Vanadium</a> is a medium-hard, ductile, malleable transition metal with a lustrous steel-blue appearance. With a density of 6.11 g/cm³, vanadium is significantly lighter than <a href="/school1/ptable/element/Fe" class="wiki-link" title="Iron (Fe)">iron</a> (7.87 g/cm³) yet remarkably hard and resistant to wear. It possesses a very high melting point of 2183 K (1910 °C / 3470 °F) and a boiling point of 3680 K (3407 °C / 6165 °F), classifying it as a refractory metal along with niobium and tantalum.</p>
<p>Chemically, pure vanadium metal exhibits excellent resistance to corrosion. At room temperature, exposure to air causes vanadium to instantly form a thin, protective oxide passivation layer that prevents deeper oxidation. Vanadium resists attack by alkalis, saltwater, hydrofluoric acid, cold hydrochloric acid, and dilute sulfuric acid, though it dissolves in hot concentrated nitric acid, aqua regia, and concentrated sulfuric acid. When heated in air above 933 K (660 °C / 1220 °F), vanadium oxidizes rapidly into yellow-orange vanadium pentoxide (V₂O₅). Vanadium also absorbs <a href="/school1/ptable/element/H" class="wiki-link" title="Hydrogen (H)">hydrogen</a> gas readily at high temperatures, forming brittle vanadium hydrides.</p>
<p><b>The Chameleon of Chemistry: Four Vivid Colors:</b></p>
<p>Vanadium is world-famous in chemistry education as the "chameleon metal" because it exhibits four distinct, beautifully colored oxidation states in aqueous solution, corresponding to four consecutive electronic configurations:</p>
<ul>
  <li><b>+5 Oxidation State (Yellow/Orange):</b> Found in dioxovanadium(V) cations (VO₂⁺) and vanadate ions (VO₄³⁻). This is vanadium's most stable and highest oxidation state.</li>
  <li><b>+4 Oxidation State (Bright Blue):</b> Present as the oxovanadium(IV) or vanadyl ion (VO²⁺). The vanadyl ion is exceptionally stable and forms numerous coordination complexes.</li>
  <li><b>+3 Oxidation State (Emerald Green):</b> Occurs as the hydrated vanadium(III) ion (V³⁺).</li>
  <li><b>+2 Oxidation State (Lavender/Violet):</b> Formed by the hydrated vanadium(II) ion (V²⁺).</li>
</ul>
<p>In a famous classroom chemistry demonstration, adding metallic <a href="/school1/ptable/element/Zn" class="wiki-link" title="Zinc (Zn)">zinc</a> and acid to a yellow vanadate (+5) solution causes a spectacular rainbow reaction: as zinc gradually reduces vanadium, the solution transitions step-by-step from vibrant yellow (+5) to deep blue (+4), brilliant green (+3), and finally soft lavender-violet (+2)!</p>"""

isotopes_html = """<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r1368532237" /><div role="note" class="hatnote navigation-not-searchable">Main article: Isotopes of <a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">vanadium</a></div>
<p>Naturally occurring <a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">vanadium</a> found on Earth consists of two primary isotopes: one stable isotope, <b>vanadium-51 (⁵¹V)</b>, which accounts for 99.75% of natural abundance, and one primordial radioactive isotope, <b>vanadium-50 (⁵⁰V)</b>, which makes up the remaining 0.25%.</p>
<p>Vanadium-50 is a fascinating nuclear anomaly. It has an extraordinarily long half-life of 2.71 × 10¹⁷ years (271 quadrillion years)—a duration more than 19 million times longer than the age of the universe itself! Because it decays so incredibly slowly via electron capture (decaying into stable <a href="/school1/ptable/element/Ti" class="wiki-link" title="Titanium (Ti)">titanium</a>-50) and beta decay (decaying into stable <a href="/school1/ptable/element/Cr" class="wiki-link" title="Chromium (Cr)">chromium</a>-50), vanadium-50 poses zero radiation hazard and behaves as an effectively stable isotope in practical everyday applications.</p>
<p>The dominant stable isotope, vanadium-51 (⁵¹V), possesses a nuclear spin of 7/2. Because of its favorable nuclear properties, ⁵¹V is widely utilized in Nuclear Magnetic Resonance (NMR) spectroscopy to investigate the structural geometry, active sites, and chemical bonding of complex vanadium catalysts and biological metalloproteins.</p>
<p>Nuclear scientists have artificially synthesized 25 radioactive isotopes of vanadium in particle accelerators, ranging in mass number from ⁴²V to ⁶⁸V. Among these synthetic radioisotopes, the longest-lived are <b>vanadium-49 (⁴⁹V)</b> with a half-life of 330 days, and <b>vanadium-48 (⁴⁸V)</b> with a half-life of 15.97 days. All other artificial radioisotopes have half-lives shorter than one hour, with most decaying in under ten seconds. For vanadium isotopes lighter than ⁵¹V, the primary decay pathway is electron capture into titanium; for isotopes heavier than ⁵¹V, the main pathway is beta emission into chromium.</p>"""

occurrence_html = """<figure class="mw-default-size" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/b42ae57c240be596583327492a7369d7.jpg" decoding="async" width="250" height="166" class="mw-file-element"  data-file-width="2240" data-file-height="1488" /><figcaption>Vanadinite</figcaption></figure>
<p><a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">Vanadium</a> is the 19th most abundant element in Earth's crust, with an average crustal concentration of roughly 120 parts per million (0.012% by weight)—making it nearly as common as <a href="/school1/ptable/element/Cu" class="wiki-link" title="Copper (Cu)">copper</a> or <a href="/school1/ptable/element/Zn" class="wiki-link" title="Zinc (Zn)">zinc</a>. Across the wider universe, vanadium accounts for about 0.0001% of cosmic matter and has been detected spectroscopically in the light from the Sun and cool M-type red giant stars. Ocean water contains dissolved vanadyl ions at an average concentration of 30 nanomolar (1.5 mg/m³), while certain volcanic mineral springs—such as those surrounding Mount Fuji in Japan—contain up to 54 micrograms of vanadium per liter.</p>
<p>Uncombined metallic vanadium (known as <i>native vanadium</i>) is exceptionally rare in nature, discovered only in volcanic gas vents (fumaroles) at the active Colima Volcano in Mexico, alongside rare minerals like shcherbinaite (V₂O₅) and colimaite (K₃VS₄). Instead, vanadium compounds are broadly distributed across more than 65 different minerals. Major vanadium-bearing minerals include vanadinite (Pb₅(VO₄)₃Cl), carnotite (K₂(UO₂)₂(VO₄)₂·3H₂O), patrónite (VS₄), and roscoelite (a vanadium-rich mica).</p>
<p>Despite its mineral diversity, primary mining of pure vanadium ores is rare. Today, roughly 90% of global vanadium production is recovered as a high-value byproduct from leftover slag during steel manufacturing! Most commercial vanadium originates from vanadium-bearing titanomagnetite (titaniferous iron ores) found in layered igneous rock formations (gabbros). When these ores are smelted in blast furnaces to produce <a href="/school1/ptable/element/Fe" class="wiki-link" title="Iron (Fe)">iron</a>, vanadium concentrates in the molten slag, which is then processed with sodium salts to extract pure vanadium pentoxide (V₂O₅). The world's top vanadium producers are China (which accounts for over 70% of global output), South Africa, and Russia.</p>
<p>Significant quantities of vanadium are also present in fossil fuel deposits, including crude oil, coal, oil shale, and tar sands. Crude oils from Venezuela and the Middle East report vanadium concentrations as high as 1200 ppm. When vanadium-rich heavy oils are burned in industrial boilers and marine engines, vanadium residues can cause high-temperature ash corrosion on boiler tubes and engine valves. Globally, fossil fuel combustion releases an estimated 110,000 tonnes of vanadium into the atmosphere every year.</p>
<p><b>Indian Geological Reserves & Resources:</b></p>
<p>India possesses substantial resources of vanadium, estimated at over 24 million tonnes of vanadium-bearing ore (containing over 64,000 tonnes of V₂O₅ equivalent). Geologically, India's vanadium wealth occurs in two distinct environments:</p>
<ul>
  <li><b>Titaniferous Magnetite in Odisha & Peninsular India:</b> Extensive vanadiferous titanomagnetite (VTM) deposits occur hosted within the <b>Mayurbhanj Basic Igneous Complex</b> in Mayurbhanj district, Odisha (stretching 15 km from Kumhardubi to Hatichhar), as well as in Karnataka and Maharashtra. These ores contain between 0.28% and 1.38% V₂O₃ and serve as vital domestic feedstocks for steel slag extraction.</li>
  <li><b>Carbonaceous Phyllites in Arunachal Pradesh:</b> In a groundbreaking discovery, the Geological Survey of India (GSI) identified primary vanadium mineralization in Palaeo-proterozoic carbonaceous phyllite rocks of the Khetabari Formation in the Papum Pare district of <b>Arunachal Pradesh</b> (notably in the Depo and Tamang areas). Similar to China's famed "stone coal" deposits, this discovery marks India's first major primary sediment-hosted vanadium deposit, promising strategic self-reliance in critical energy minerals.</li>
</ul>"""

applications_html = """<figure class="mw-default-size mw-halign-right" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/bf3bed4784c2c4f29fcec26b0d1b3607.jpg" decoding="async" width="190" height="188" style="--mw-file-upright: 0.75" class="mw-file-element mw-file-upright"  data-file-width="2304" data-file-height="2275" /><figcaption>Tool made from <a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">vanadium</a> steel</figcaption></figure>
<p>Though hidden from plain sight, <a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">vanadium</a> is an indispensable workhorse of modern metallurgy, aerospace engineering, industrial chemistry, and green energy technology:</p>
<ul>
  <li><b>Steel Alloys & Ferrovanadium:</b> Approximately 85% to 90% of all vanadium produced worldwide is consumed in the steel industry in the form of <i>ferrovanadium</i> (an alloy containing 35%–80% vanadium). Adding as little as 0.05% to 0.15% vanadium to molten steel refines grain size, binds excess nitrogen and carbon into stable micro-carbides, and dramatically enhances tensile strength, hardness, and thermal resistance. Vanadium steel is essential for earthquake-resistant building rebar, high-span bridges, oil pipelines, automotive axles, railway tracks, and high-speed cutting tools (wrenches, drill bits, and knives).</li>
  <li><b>Aerospace Titanium Alloys (Ti-6Al-4V):</b> Vanadium is a critical alloying element in high-performance titanium alloys, most notably <b>Ti-6Al-4V</b> (Grade 5 titanium), which consists of 90% titanium, 6% <a href="/school1/ptable/element/Al" class="wiki-link" title="Aluminum (Al)">aluminum</a>, and 4% vanadium. Vanadium acts as a beta-phase stabilizer, giving the alloy superior strength-to-weight ratio, high temperature tolerance, and exceptional corrosion resistance. Ti-6Al-4V is extensively used in jet engine compressor blades, supersonic airframes, rocket motor casings, and surgical implants.</li>
  <li><b>Industrial Chemical Catalysts:</b> Vanadium pentoxide (V₂O₅) is the core chemical catalyst used in the industrial Contact Process to oxidize sulfur dioxide into sulfur trioxide for manufacturing <b>sulfuric acid</b>—the world's most widely produced industrial chemical. Vanadium catalysts are also essential in producing maleic anhydride (for plastics), phthalic anhydride, and selective catalytic reduction (SCR) systems that remove harmful nitrogen oxide (NOx) pollutants from power plant smokestacks.</li>
  <li><b>Vanadium Redox Flow Batteries (VRFB):</b> Vanadium is revolutionary for renewable energy storage! Unlike lithium-ion batteries, a <i>Vanadium Redox Flow Battery</i> uses liquid vanadium electrolytes stored in large external tanks, taking advantage of vanadium's four consecutive oxidation states (+2, +3, +4, +5) to store and release electrical energy. VRFBs offer non-flammable, long-duration energy storage (over 20 years of continuous use with zero capacity degradation), making them ideal for backing up solar and wind power grids.</li>
</ul>
<p><b>Modern Indian Scientific & Industrial Contributions:</b></p>
<p>India is actively harnessing vanadium across defense, nuclear metallurgy, green energy, and sustainable waste recycling:</p>
<ul>
  <li><b>Indigenous Aerospace Alloys (MIDHANI & HAL Tejas):</b> State-owned metallurgy giant <b>Mishra Dhatu Nigam Limited (MIDHANI)</b> in Hyderabad indigenously manufactures high-purity <b>Ti-6Al-4V</b> titanium-vanadium alloy products for Hindustan Aeronautics Limited (HAL). These strategic alloys form critical airframe structures, wing fittings, and gas turbine components for India's <b>HAL Tejas</b> light combat aircraft (Tejas Mk1A and Mk2) as well as launch vehicle components for the Indian Space Research Organisation (ISRO).</li>
  <li><b>Grid-Scale Flow Battery Innovation (CSIR-CECRI, IIT Madras & NTPC):</b> Premier Indian institutions are pioneering indigenous VRFB technology for national grid stability. Researchers at <b>CSIR-Central Electrochemical Research Institute (CSIR-CECRI)</b> in Karaikudi and <b>IIT Madras</b> have engineered indigenous VRFB stack architectures and cost-effective ion-exchange membranes. In a historic milestone for Indian green energy, India's first grid-scale 3 MWh Vanadium Redox Flow Battery system was commissioned at NTPC's NETRA facility in Greater Noida, establishing a national benchmark for long-duration renewable storage.</li>
  <li><b>Sustainable Metallurgical Recovery (CSIR-NML):</b> Scientists at the <b>CSIR-National Metallurgical Laboratory (CSIR-NML)</b> in Jamshedpur have developed eco-friendly hydrometallurgical extraction processes to recover strategic vanadium pentoxide (V₂O₅) and ammonium metavanadate from secondary resources—including spent sulfuric acid catalysts and titaniferous steel slag—supporting India's <i>Aatmanirbhar Bharat</i> mission for critical mineral independence.</li>
</ul>"""

biological_role_html = """<p><a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">Vanadium</a> plays a far more prominent biological role in marine life and specialized microorganisms than in higher land animals. Although present in trace amounts in the human body (totaling about 100 to 200 micrograms), vanadium is not officially classified as an essential micronutrient for humans, though it is required in micro-gram amounts by rats and chickens for normal growth and lipid metabolism.</p>
<figure class="mw-default-size" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/0a0f58ef80f4951652ad63dba7b315b9.jpg" decoding="async" width="250" height="200" class="mw-file-element"  data-file-width="2415" data-file-height="1932" /><figcaption>Tunicates such as this bluebell tunicate contain <a href="/school1/ptable/element/V" class="wiki-link" title="Vanadium (V)">vanadium</a> as vanabins.</figcaption></figure>
<figure class="mw-default-size" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/b1e44ba7081cce0e32ff477d96f66575.jpg" decoding="async" width="250" height="188" class="mw-file-element"  data-file-width="3072" data-file-height="2304" /><figcaption><i>Amanita muscaria</i> contains amavadin.</figcaption></figure>
<p><b>Marine Biochemistry & Specialized Organisms:</b></p>
<ul>
  <li><b>Tunicates (Sea Squirts):</b> Certain marine tunicates (such as the bluebell tunicate) absorb vanadyl ions from seawater and accumulate vanadium in specialized blood cells called <i>vanadocytes</i> at concentrations up to 1,000,000 times higher than surrounding ocean water! They bind the metal using specialized vanadium-binding proteins called <i>vanabins</i>. While their exact biological function remains an intriguing mystery, vanabins are believed to play roles in defense against predators or oxygen metabolism.</li>
  <li><b>Fungi (Fly Agaric):</b> The famous red-and-white spotted toadstool mushroom, <i>Amanita muscaria</i> (Fly Agaric), accumulates vanadium to form a unique organic coordination complex called <i>amavadin</i>.</li>
  <li><b>Bacterial & Algal Enzymes:</b> Certain nitrogen-fixing soil bacteria (such as <i>Azotobacter vinelandii</i>) express alternative <i>vanadium nitrogenase</i> enzymes that allow them to convert atmospheric nitrogen gas into ammonia when environmental <a href="/school1/ptable/element/Mo" class="wiki-link" title="Molybdenum (Mo)">molybdenum</a> is scarce. Additionally, marine brown algae and fungi utilize vanadium haloperoxidase enzymes to synthesize halogenated organic compounds.</li>
</ul>
<p><b>Medicinal Chemistry & Indian Insulin-Mimetic Research:</b></p>
<p>In pharmacology, vanadium compounds exhibit powerful <b>insulin-mimetic</b> (insulin-like) properties. Vanadate (VO₄³⁻) and vanadyl (VO²⁺) ions structurally resemble phosphate ions, allowing them to inhibit protein tyrosine phosphatase (PTPase) enzymes in the human body. By blocking PTPases, vanadium complexes keep insulin receptors active, stimulating glucose uptake into cells and lowering blood sugar levels independently of insulin.</p>
<p>To overcome the mild gastrointestinal toxicity of inorganic vanadium salts, bioinorganic chemists across premier Indian institutions—including the Indian Institute of Science (IISc Bengaluru), IIT Bombay, IIT Madras, and CSIR laboratories—are synthesizing novel organic vanadium complexes (such as vanadyl picolinate, acetylacetonates, and Schiff-base derivatives). These Indian research groups are designing bio-compatible, low-toxicity vanadium metallopharmaceuticals aimed at developing affordable oral therapeutics for managing type-2 diabetes.</p>"""

vanadium_data = {
  "symbol": "V",
  "name": "Vanadium",
  "atomic": 23,
  "category": "transition metal",
  "description": "Chemical element with atomic number 23 (V)",
  "extract_html": extract_html,
  "local_image": "https://upload.wikimedia.org/wikipedia/commons/0/0a/Vanadium-pieces.jpg",
  "atomic_mass": 50.94151,
  "density": 6,
  "melt": 2183,
  "boil": 3680,
  "phase": "Solid",
  "discovered_by": "Andrés Manuel del Río",
  "electron_configuration": "1s2 2s2 2p6 3s2 3p6 4s2 3d3",
  "electronegativity_pauling": 1.63,
  "electron_affinity": 50.911,
  "ionization_energies": [
    650.9,
    1414,
    2830,
    4507,
    6298.7,
    12363,
    14530,
    16730,
    19860,
    22240,
    24670,
    29730,
    32446,
    86450,
    94170,
    102300,
    112700,
    121600,
    130700,
    143400,
    151440,
    661050,
    699144
  ],
  "sections": {
    "History": history_html,
    "Characteristics": characteristics_html,
    "Isotopes": isotopes_html,
    "Occurrence": occurrence_html,
    "Applications": applications_html,
    "Biological role": biological_role_html
  }
}

target_path = r"c:\projects\apache\school1\src\ptable\data\drafts\Vanadium.json"
os.makedirs(os.path.dirname(target_path), exist_ok=True)
with open(target_path, "w", encoding="utf-8") as f:
    json.dump(vanadium_data, f, indent=2, ensure_ascii=False)

print("Successfully generated Vanadium.json!")
