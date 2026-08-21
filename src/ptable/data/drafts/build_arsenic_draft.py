import json
import os

draft_dir = r"c:\projects\apache\school1\src\ptable\data\drafts"
os.makedirs(draft_dir, exist_ok=True)
output_path = os.path.join(draft_dir, "Arsenic.json")

extract_html = (
    '<p><b><a href="/school1/ptable/element/As" class="wiki-link" title="Arsenic (As)">Arsenic</a></b> '
    '(symbol <b>As</b>, atomic number 33) is a versatile metalloid positioned in Group 15 of the periodic table, '
    'right beneath <a href="/school1/ptable/element/P" class="wiki-link" title="Phosphorus (P)">phosphorus</a>. '
    'Infamous throughout history as the "king of poisons" and the "inheritance powder," arsenic displays a dark, '
    'double-edged personality: while its volatile compounds were once the covert weapon of Renaissance poisoners, '
    'its crystalline forms are essential for modern high-speed microchips and life-saving leukemia treatments. '
    'Arsenic exists in several distinct forms (allotropes), most notably a brittle, metallic steel-grey crystal that '
    'sublimates directly into gas when heated. Arsenic holds profound historical and contemporary significance in India: '
    'ancient Ayurvedic scholars cataloged its mineral ores\u2014<b>Haritala</b> (yellow orpiment) and <b>Manashila</b> '
    '(red realgar)\u2014in texts like the <i>Rasa Ratna Samuccaya</i>, developing sophisticated multi-step purification '
    '(<i>Shodhana</i>) methods to turn these toxic minerals into therapeutic medicines. In modern times, while geogenic '
    'arsenic leaching into the shallow tubewells of the Ganga-Brahmaputra alluvial plain presents a major public health '
    'challenge, pioneering Indian scientists\u2014such as <b>Prof. T. Pradeep</b> of IIT Madras with his award-winning '
    '<b>AMRIT</b> filter and researchers at CSIR-CGCRI\u2014have developed world-leading nanotechnology and ceramic '
    'membrane filtration systems to bring safe drinking water to millions of affected citizens.</p>'
)

characteristics_html = (
    '<figure class="mw-default-size mw-halign-left" typeof="mw:File/Thumb">'
    '<img src="/school1/ptable/theme-assets/default/images/inline/3b87ee37111120b5516225ea62790257.jpg" '
    'decoding="async" width="250" height="230" class="mw-file-element" data-file-width="400" data-file-height="368" />'
    '<figcaption>Metallic grey <a href="/school1/ptable/element/As" class="wiki-link" title="Arsenic (As)">arsenic</a> crystal</figcaption>'
    '</figure>'
    '<p><b><a href="/school1/ptable/element/As" class="wiki-link" title="Arsenic (As)">Arsenic</a></b> is a chemical element '
    'classified as a metalloid\u2014an element that exhibits physical and chemical properties intermediate between true metals '
    'and non-metals. Positioned in Group 15 (the pnictogens) directly below <a href="/school1/ptable/element/P" class="wiki-link" '
    'title="Phosphorus (P)">phosphorus</a> and above <a href="/school1/ptable/element/Sb" class="wiki-link" title="Antimony (Sb)">antimony</a>, '
    'arsenic shares structural and chemical similarities with both of its periodic neighbors.</p>'
    '<p>Arsenic occurs in three major elemental allotropes (different structural forms of the same element):</p>'
    '<ul>'
    '<li><b>Grey Metallic Arsenic:</b> The most common, stable, and chemically reactive allotrope at ambient conditions. '
    'It forms a brittle, steel-grey crystalline solid with a density of 5.727 g/cm\u00b3. Its crystal structure consists of '
    'layered rhombohedral sheets of six-membered rings. Because its electrons can move across these sheets, grey metallic '
    'arsenic conducts electricity moderately well, though not as efficiently as true metals like '
    '<a href="/school1/ptable/element/Cu" class="wiki-link" title="Copper (Cu)">copper</a> or '
    '<a href="/school1/ptable/element/Ag" class="wiki-link" title="Silver (Ag)">silver</a>.</li>'
    '<li><b>Yellow Arsenic:</b> A soft, waxy molecular solid created by rapidly cooling arsenic vapor. Composed of tetrahedral '
    '\\(\\text{As}_4\\) units held together by weak van der Waals forces (analogous to white phosphorus), yellow arsenic has a '
    'low density of 1.97 g/cm\u00b3. It is volatile, extraordinarily toxic, and light-sensitive\u2014exposing yellow arsenic to room '
    'light causes it to rapidly transform back into grey arsenic.</li>'
    '<li><b>Black Arsenic:</b> An amorphous form structurally similar to black phosphorus, synthesized by condensing arsenic vapor '
    'onto surfaces kept between 100 \u00b0C and 280 \u00b0C. It is glassy, brittle, and a poor electrical conductor.</li>'
    '</ul>'
    '<p>An extraordinary physical property of arsenic is its behavior when heated under normal atmospheric pressure. '
    'Unlike most elements, solid arsenic does not melt when heated at 1 atm; instead, it undergoes <b>sublimation</b>, '
    'transforming directly from a solid into a dense, yellowish gas at 615 \u00b0C (888 K or 1,139 \u00b0F). Arsenic can only be '
    'forced to melt into a liquid state under high pressure (28 atmospheres), reaching its liquid melting point at 817 \u00b0C (1,090 K).</p>'
    '<p>Chemically, arsenic exhibits principal oxidation states of <b>\u22123</b>, <b>+3</b>, and <b>+5</b>. When heated in open air, '
    'arsenic burns with a faint bluish flame, producing toxic white clouds of arsenic trioxide (\\(\\text{As}_2\\text{O}_3\\)) '
    'accompanied by a characteristic garlic-like odor:</p>'
    '<p style="text-align:center;">\\( 4\\text{As} + 3\\text{O}_2 \\rightarrow 2\\text{As}_2\\text{O}_3 \\)</p>'
    '<p>Arsenic trioxide is weakly acidic (amphoteric), dissolving in water to form arsenious acid (\\(\\text{H}_3\\text{AsO}_3\\)). '
    'Arsenic reacts readily with halogens to form trihalides and pentahalides, such as arsenic pentafluoride (\\(\\text{AsF}_5\\)), '
    'and reacts with electropositive metals to form binary compounds called arsenides (such as gallium arsenide, \\(\\text{GaAs}\\)).</p>'
)

isotopes_html = (
    '<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r1368532237" />'
    '<div role="note" class="hatnote navigation-not-searchable">Main article: Isotopes of <a href="/school1/ptable/element/As" class="wiki-link" title="Arsenic (As)">arsenic</a></div>'
    '<p>Naturally occurring <a href="/school1/ptable/element/As" class="wiki-link" title="Arsenic (As)">arsenic</a> is a monoisotopic element, '
    'consisting entirely of a single stable isotope: <b><sup>75</sup>As</b> (100% natural abundance). Because it has only one stable nuclide, '
    'arsenic is also classified as a mononuclidic element, which allows its standard atomic weight to be determined with extreme precision (74.9215956 u).</p>'
    '<p>Nuclear physicists have synthesized 32 radioactive isotopes of arsenic, spanning atomic mass numbers from <sup>64</sup>As up to <sup>95</sup>As, '
    'along with at least 11 metastable nuclear isomers. Most of these synthetic radioisotopes are highly unstable, with half-lives measuring under '
    '100 minutes, and many decaying in less than a single minute.</p>'
    '<p>Among the radioactive isotopes, the longest-lived and most scientifically notable include:</p>'
    '<ul>'
    '<li><b><sup>73</sup>As:</b> Half-life of 80.30 days (decays via electron capture to germanium-73).</li>'
    '<li><b><sup>74</sup>As:</b> Half-life of 17.77 days.</li>'
    '<li><b><sup>71</sup>As:</b> Half-life of 65.30 hours.</li>'
    '<li><b><sup>77</sup>As:</b> Half-life of 38.79 hours.</li>'
    '<li><b><sup>76</sup>As:</b> Half-life of 26.24 hours.</li>'
    '<li><b><sup>72</sup>As:</b> Half-life of 26.0 hours.</li>'
    '</ul>'
    '<p>The radioactive decay pathways of arsenic radioisotopes follow distinct patterns determined by their atomic mass relative to stable <sup>75</sup>As:</p>'
    '<p>Isotopes lighter than stable <sup>75</sup>As decay primarily via <b>positron emission (\u03b2<sup>+</sup>)</b> or <b>electron capture</b> '
    'to form stable or long-lived isotopes of <a href="/school1/ptable/element/Ge" class="wiki-link" title="Germanium (Ge)">germanium</a>:</p>'
    '<p style="text-align:center;">\\( \\text{}^{n}_{33}\\text{As} + e^{-} \\rightarrow \\text{}^{n}_{32}\\text{Ge} + \\nu_e \\)</p>'
    '<p>Isotopes heavier than stable <sup>75</sup>As decay primarily via <b>beta-minus emission (\u03b2<sup>\u2212</sup>)</b>, transforming into '
    'isotopes of <a href="/school1/ptable/element/Se" class="wiki-link" title="Selenium (Se)">selenium</a>:</p>'
    '<p style="text-align:center;">\\( \\text{}^{n}_{33}\\text{As} \\rightarrow \\text{}^{n}_{34}\\text{Se} + e^{-} + \\bar{\\nu}_e \\)</p>'
    '<p>A fascinating nuclear exception is <b><sup>74</sup>As</b>, which undergoes dual decay pathways! It decays simultaneously by electron '
    'capture (66%) into germanium-74 and by beta-minus emission (34%) into selenium-74. Because of its positron-emitting properties, <sup>74</sup>As '
    'and <sup>72</sup>As have been utilized in medical physics as positron emitters for Positron Emission Tomography (PET) scanning to locate and map tumor growth in human tissue.</p>'
)

occurrence_html = (
    '<figure class="mw-default-size mw-halign-left" typeof="mw:File/Thumb">'
    '<img src="/school1/ptable/theme-assets/default/images/inline/832cf4ba22be943345fbeae61306e529.jpg" '
    'decoding="async" width="250" height="147" class="mw-file-element" data-file-width="340" data-file-height="200" />'
    '<figcaption>Vibrant red realgar (As<sub>4</sub>S<sub>4</sub>) crystal</figcaption>'
    '</figure>'
    '<figure class="mw-default-size mw-halign-left" typeof="mw:File/Thumb">'
    '<img src="/school1/ptable/theme-assets/default/images/inline/4593131df3d024fd9c8e5d0abded2a60.jpg" '
    'decoding="async" width="250" height="179" class="mw-file-element" data-file-width="640" data-file-height="458" />'
    '<figcaption>The historical arsenic labyrinth at Botallack Mine, Cornwall</figcaption>'
    '</figure>'
    '<p><b><a href="/school1/ptable/element/As" class="wiki-link" title="Arsenic (As)">Arsenic</a></b> is the 53rd most abundant element in Earth\'s crust, '
    'with an average background concentration of roughly 1.5 to 1.8 parts per million (ppm). Native elemental arsenic crystals occur in nature, '
    'but they are exceptionally rare. Instead, arsenic is geochemically classified as a <i>chalcophile</i> element, meaning it has a strong affinity '
    'for binding with <a href="/school1/ptable/element/S" class="wiki-link" title="Sulfur (S)">sulfur</a> and heavy metals in hydrothermal ore veins.</p>'
    '<p>Arsenic is found in over 500 distinct mineral species. The most abundant commercial arsenic minerals include:</p>'
    '<ul>'
    '<li><b>Arsenopyrite (FeAsS):</b> Also known as mispickel, a steel-grey to silver iron arsenic sulfide mineral that serves as the world\'s chief commercial source of arsenic.</li>'
    '<li><b>Realgar (As<sub>4</sub>S<sub>4</sub> or AsS):</b> A striking, ruby-red to orange-red arsenic disulfide mineral.</li>'
    '<li><b>Orpiment (As<sub>2</sub>S<sub>3</sub>):</b> A brilliant, canary-yellow arsenic trisulfide mineral with a pearly luster.</li>'
    '<li><b>Arsenolite and Claudetite (As<sub>2</sub>O<sub>3</sub>):</b> Secondary natural oxide minerals formed by the weathering of arsenic sulfides.</li>'
    '<li><b>Enargite (Cu<sub>3</sub>AsS<sub>4</sub>) and Tennantite:</b> Important copper-arsenic minerals mined primarily for copper extraction.</li>'
    '</ul>'
    '<p>Most industrial arsenic is not mined directly, but is recovered as a byproduct during the roasting and smelting of copper, '
    '<a href="/school1/ptable/element/Pb" class="wiki-link" title="Lead (Pb)">lead</a>, '
    '<a href="/school1/ptable/element/Au" class="wiki-link" title="Gold (Au)">gold</a>, and '
    '<a href="/school1/ptable/element/Zn" class="wiki-link" title="Zinc (Zn)">zinc</a> ores. Major global producers of arsenic trioxide include China, Morocco, Russia, and Chile.</p>'
    '<p><b>Geographical &amp; Hydro-Geological Context in India:</b> In India, arsenic presents a profound geographical and environmental story. '
    'The vast <b>Ganga-Brahmaputra alluvial plains</b>\u2014spanning West Bengal, Bihar, Uttar Pradesh, Jharkhand, and Assam\u2014contain deep layers of river '
    'sediments eroded over millions of years from the Himalayan mountains. These sediments naturally contain trace amounts of arsenopyrite and iron oxides.</p>'
    '<p>Under anaerobic (reducing) underground conditions in shallow alluvial aquifers, naturally occurring micro-organisms metabolize organic carbon and '
    'dissolve iron oxy-hydroxides. This natural biogeochemical process releases inorganic arsenic ions (toxic arsenite \\(\\text{As}^{3+}\\) and arsenate \\(\\text{As}^{5+}\\)) '
    'directly into shallow groundwater. As a result, millions of shallow tubewells drilled across rural West Bengal (such as in Murshidabad, Nadia, North and South 24 Parganas) '
    'and neighboring states draw groundwater with arsenic levels exceeding the World Health Organization (WHO) safety limit of 10 ppb (10 \u03bcg/L), creating one of the '
    'largest geogenic water quality challenges in human history.</p>'
)

history_html = (
    '<figure class="mw-default-size mw-halign-left" typeof="mw:File/Thumb">'
    '<img src="/school1/ptable/theme-assets/default/images/inline/8d827f8f3782b2b722dcf109a28da7df.png" '
    'decoding="async" width="90" height="90" style="--mw-file-upright: 0.35" class="mw-file-element mw-file-upright" data-file-width="16" data-file-height="16" />'
    '<figcaption>Alchemical symbol for <a href="/school1/ptable/element/As" class="wiki-link" title="Arsenic (As)">arsenic</a></figcaption>'
    '</figure>'
    '<p>The name <i><a href="/school1/ptable/element/As" class="wiki-link" title="Arsenic (As)">arsenic</a></i> traces a rich linguistic journey across ancient languages. '
    'It originated from the Syriac word <i>zarnika</i> and the Arabic <i>al-zarn\u012bkh</i> (meaning "the orpiment"), rooted in the ancient Persian word <i>zar</i> ("gold") '
    'and <i>zarnikh</i> ("yellow" or "gold-colored"). The Greeks adopted the word as <i>arsenikon</i> (\u1f00\u03c1\u03c3\u03b5\u03bd\u03b9\u03ba\u03cc\u03bd), playfully connecting '
    'it via folk etymology to the Greek adjective <i>arsenikos</i> (\u1f00\u03c1\u03c3\u03b5\u03bd\u03b9\u03ba\u03cc\u03c2), meaning "male" or "virile," due to arsenic\'s potent '
    'chemical reactivity. This entered Latin as <i>arsenicum</i>, evolved into Old French <i>arsenic</i>, and entered the English language as <i>arsenic</i>.</p>'
    '<p>Arsenic minerals were used by ancient civilizations thousands of years ago. During the Bronze Age, ancient smiths melted arsenic minerals together with '
    '<a href="/school1/ptable/element/Cu" class="wiki-link" title="Copper (Cu)">copper</a> to forge <b>arsenical bronze</b>\u2014an alloy harder and easier to cast than pure copper, '
    'widely used before tin bronze became dominant.</p>'
    '<p><b>Ancient Indian History &amp; Rasashastra Knowledge:</b> In ancient India, arsenic minerals played a central role in <b>Ayurveda</b> and the specialized alchemical '
    'discipline of <b>Rasashastra</b> (iatrochemistry or mineral-based medicine), which developed between the 1st millennium BCE and the 12th century CE. Classical texts such as '
    'the <i>Charaka Samhita</i>, <i>Sushruta Samhita</i>, <i>Rasa Ratna Samuccaya</i> (by Vagbhata), <i>Rasarnava</i>, and <i>Rasa Hridaya Tantra</i> cataloged three primary arsenic minerals:</p>'
    '<ul>'
    '<li><b>Haritala (Orpiment, As<sub>2</sub>S<sub>3</sub>):</b> A golden-yellow mineral classified as an <i>Uparasa</i> (secondary mineral). Ancient scholars recognized two varieties: <i>Patra Haritala</i> (foliated or layered, highly prized) and <i>Pinda Haritala</i> (lumpy).</li>'
    '<li><b>Manashila (Realgar, As<sub>4</sub>S<sub>4</sub>):</b> A vivid red to orange-red mineral, valued in traditional formulations for treating respiratory conditions, asthma, and chronic skin disorders.</li>'
    '<li><b>Somala or Gauripashana (White Arsenic, As<sub>2</sub>O<sub>3</sub>):</b> Arsenic trioxide, recognized as extraordinarily potent and toxic.</li>'
    '</ul>'
    '<p>Because raw arsenic compounds are inherently toxic, Rasashastra scholars developed sophisticated pharmaceutical protocols known as <b>Shodhana</b> (purification and detoxification). '
    'Minerals like <i>Haritala</i> were wrapped in cloth and suspended in specialized boiling apparatuses called <b>Dolayantra</b>, where they were detoxified by steaming them in herbal media '
    'such as <i>Kushmanda</i> (ash gourd) juice, lime water, or plant decoctions. This was followed by <b>Marana</b> (calcination), where the purified mineral was ground with herbal extracts '
    'and roasted in sealed clay retorts (<i>Puta</i>) to create bio-compatible micro-crystalline ashes known as <b>Haritala Bhasma</b> and <b>Manashila Bhasma</b>. These <i>Rasaushadhis</i> '
    'were meticulously prepared for treating severe skin ailments (like psoriasis), stubborn fevers, and pulmonary conditions, serving the twin alchemical goals of <i>Dhatuvada</i> '
    '(transmutation of metals) and <i>Dehavada</i> (body rejuvenation and longevity).</p>'
    '<p><b>Greco-Egyptian &amp; Islamic Alchemy:</b> In the Western world, Greco-Egyptian alchemist Zosimos of Panopolis (c. 300 AD) described roasting <i>sandarach</i> (realgar) '
    'to produce "cloud of arsenic" (arsenic trioxide), which he reduced to grey metallic arsenic. Islamic polymath <b>Jabir ibn Hayyan</b> (Geber) described isolating arsenic prior to 815 AD. '
    'Later in Europe, Albertus Magnus (Albert the Great, 1193\u20131280) isolated elemental arsenic in 1250 by heating soap together with arsenic trisulfide. In 1649, German chemist '
    'Johann Schr\u00f6der published two reliable methods for isolating pure arsenic metal.</p>'
    '<p><b>The "King of Poisons" &amp; Forensic Science:</b> Because arsenic trioxide is odorless, tasteless, and easily mixed into food or drink, producing symptoms that mimicked natural '
    'intestinal illnesses (like cholera), it became the secret weapon of choice for political assassinations and family poisonings during the Renaissance. It earned nicknames such as '
    'the "poison of kings," the "king of poisons," and "inheritance powder." This reign of undetected murder came to an abrupt end in 1836 when English chemist James Marsh invented the '
    '<b>Marsh test</b>\u2014a highly sensitive chemical procedure capable of detecting microscopic traces of arsenic in human tissue. (Another sensitive technique, the Reinsch test, was introduced shortly after.)</p>'
    '<p><b>18th to 20th Century Uses &amp; Pigments:</b> In 1760, French chemist Louis Claude Cadet de Gassicourt synthesized <i>Cadet\'s fuming liquid</i> (impure cacodyl) by reacting '
    '<a href="/school1/ptable/element/K" class="wiki-link" title="Potassium (K)">potassium</a> acetate with arsenic trioxide\u2014widely considered the very first synthetic organometallic compound in chemical history!</p>'
    '<p>During the 18th and 19th centuries, arsenic compounds entered everyday life in bizarre ways. Victorian women consumed "white arsenic" mixed with vinegar and chalk to produce a fashionable pale complexion. '
    'In 1858, accidental food adulteration with arsenic led to the tragic Bradford sweet poisoning in England, resulting in 21 deaths. Brilliant green arsenic pigments\u2014such as '
    '<b>Scheele\'s Green</b> (copper arsenite, invented in 1775 by Carl Wilhelm Scheele) and <b>Paris Green</b> (copper acetoarsenite, 1814)\u2014became immensely popular in dyed clothing, toys, and printed floral wallpapers. '
    'Historians suggest that damp wallpaper releasing toxic arsenic gas (trimethylarsine) contributed to Napoleon Bonaparte\'s illness and death on Saint Helena in 1821!</p>'
    '<p>In the 1860s, an industrial byproduct called <b>London Purple</b> (a mixture of arsenic trioxide, aniline, lime, and iron oxide) was used as an agricultural insecticide, later superseded by Paris Green, lead arsenate, '
    'and calcium arsenite, which dominated farming until DDT was discovered in 1942.</p>'
    '<p>In medicine, small diluted doses of arsenic were prescribed as general tonics. <b>Fowler\'s Solution</b> (1% potassium arsenite solution) was widely used from 1786 into the mid-20th century to treat skin diseases, anemia, '
    'and even performance issues in racehorses and work dogs. In 1932, the legendary Australian racehorse <i>Phar Lap</i> died suddenly; a 2006 forensic analysis of his preserved hair proved he died of an accidental massive arsenic overdose. '
    'As Sydney veterinarian Percy Sykes recalled: <i>"In those days, arsenic was quite a common tonic... I\'d reckon 90 per cent of the horses had arsenic in their system."</i></p>'
)

applications_html = (
    '<ol>'
    '<li><b>High-Tech Semiconductors &amp; Optoelectronics:</b> The single most important modern technological use of high-purity arsenic is in the compound semiconductor '
    '<b>Gallium Arsenide (GaAs)</b> and Indium Arsenide (InAs). Gallium arsenide possesses a wider bandgap and significantly higher electron mobility than silicon, allowing electronic transistors '
    'to operate at ultra-high microwave frequencies. GaAs microchips power 5G smartphone radio-frequency modules, radar systems, satellite communications, high-efficiency solar cells for space probes, '
    'infrared LEDs, and semiconductor laser diodes used in fiber-optic communications. Arsenic is also used as an n-type dopant in traditional <a href="/school1/ptable/element/Si" class="wiki-link" title="Silicon (Si)">silicon</a> semiconductor manufacturing.</li>'
    '<li><b>Metallurgy &amp; Alloys:</b> Adding tiny amounts of arsenic (0.5% to 2%) to <a href="/school1/ptable/element/Pb" class="wiki-link" title="Lead (Pb)">lead</a> improves the sphericity of lead shot '
    'during manufacturing and significantly increases the mechanical hardness and grid strength of lead-acid automotive batteries. Small amounts of arsenic are also added to brass alloys to prevent dezincification (corrosion) in plumbing fittings.</li>'
    '<li><b>Modern Pharmaceuticals &amp; Oncology:</b> In a remarkable medical comeback, arsenic trioxide (marketed under the brand name <i>Trisenox</i>) is an FDA-approved first-line chemotherapy medication used globally to treat '
    '<b>Acute Promyelocytic Leukemia (APL)</b>. It works by targeting and degrading the PML-RARA fusion protein, inducing apoptosis (programmed cell death) in cancer cells and restoring healthy blood cell production.</li>'
    '<li><b>Wood Preservation &amp; Specialty Glass:</b> Historically, Chromated Copper Arsenate (CCA) was applied to millions of cubic meters of structural lumber to protect wood against termites and fungal rot '
    '(though its residential use has been restricted due to environmental concerns). In glassmaking, small amounts of arsenic trioxide act as a clarifying agent to eliminate tiny air bubbles from optical lenses.</li>'
    '</ol>'
)

biological_role_html = (
    '<p>While trace amounts of arsenic have been shown to be essential in the diets of certain animals (such as chicks, rats, and goats), arsenic has no known beneficial biological role in human physiology. '
    'On the contrary, inorganic arsenic compounds are potent toxicants, cellular poisons, and confirmed Class 1 human carcinogens.</p>'
    '<p><b>Biochemical Toxicity Mechanism:</b> Arsenic toxicity operates through two primary biochemical pathways depending on its oxidation state:</p>'
    '<ul>'
    '<li><b>Trivalent Arsenic (Arsenite, As<sup>3+</sup>):</b> Soft electrophilic arsenite ions avidly bind to vicinal sulfhydryl (-SH) thiol groups on cysteine amino acid residues within critical enzymes. '
    'In particular, arsenite inhibits the pyruvate dehydrogenase multi-enzyme complex, disrupting the citric acid (Krebs) cycle and halting the production of cellular adenosine triphosphate (ATP). '
    'This triggers severe oxidative stress, cellular damage, and tissue necrosis.</li>'
    '<li><b>Pentavalent Arsenic (Arsenate, As<sup>5+</sup>):</b> Pentavalent arsenate acts as a structural mimic of inorganic phosphate (\\(\\text{PO}_4^{3-}\\)). It substitutes for phosphate in biochemical reactions, '
    'forming unstable arsenate esters that spontaneously hydrolyze\u2014a process known as <b>arsenolysis</b>. This uncouples oxidative phosphorylation, preventing cells from synthesizing ATP energy molecules.</li>'
    '</ul>'
    '<p><b>Groundwater Public Health Crisis in India:</b> In the Ganga-Brahmaputra alluvial basin of India (covering rural districts in West Bengal, Bihar, Uttar Pradesh, Jharkhand, and Assam), long-term consumption of groundwater '
    'containing high levels of geogenic arsenic causes chronic <b>arsenicosis</b>. Clinical symptoms include hyperpigmentation (dark rain-drop spots on the skin), palmoplantar keratosis (painful nodular thickening of the skin on palms and soles), '
    'peripheral vascular disease (blackfoot disease), and elevated incidence of skin, bladder, lung, and liver cancers.</p>'
    '<p><b>Pioneering Indian Scientific Innovations in Remediation:</b> To tackle this mass health crisis, Indian scientists and institutions have pioneered world-leading, low-cost water purification technologies:</p>'
    '<ul>'
    '<li><b>Prof. T. Pradeep &amp; the AMRIT Filter (IIT Madras):</b> A research team led by <b>Professor Thalappil Pradeep</b> at the Indian Institute of Technology Madras developed <b>AMRIT</b> '
    '(<i>Arsenic and Metal Removal by Indian Technology</i>). This revolutionary filter utilizes nanoscale iron oxy-hydroxide held within biopolymer cages to selectively adsorb arsenite and arsenate ions from contaminated water. '
    'AMRIT consistently reduces arsenic levels from 200\u2013500 ppb down to well below the WHO safe limit of 10 ppb, operating without electricity or releasing nanoparticles, at an ultra-affordable cost of less than 5 paise per liter! '
    'Deployed in thousands of rural villages across West Bengal, Bihar, and Uttar Pradesh, this life-saving technology earned Prof. Pradeep the prestigious <b>Padma Shri</b> national honor and the global <b>Eni Award</b>.</li>'
    '<li><b>CSIR-CGCRI Ceramic Membrane Technology (Kolkata):</b> Scientists at the <b>CSIR-Central Glass and Ceramic Research Institute</b> in Kolkata developed modular hybrid ceramic microfiltration membrane systems. '
    'By combining colloidal adsorbents with robust ceramic membranes, CSIR-CGCRI community plants purify up to 100,000 liters of groundwater per day, removing both arsenic and iron for rural populations.</li>'
    '<li><b>CSIR-NEERI &amp; Academic Research:</b> Indian institutes like CSIR-NEERI, CSIR-CMERI (Durgapur), IISc Bengaluru, and IIT Kharagpur continue to advance phytoremediation (using hyperaccumulating ferns like <i>Pteris vittata</i> to extract arsenic from soils) '
    'and microbial bioremediation, demonstrating India\'s leadership in environmental protection and clean water technology.</li>'
    '</ul>'
)

arsenic_data = {
    "symbol": "As",
    "name": "Arsenic",
    "atomic": 33,
    "category": "metalloid",
    "description": "Chemical element with atomic number 33 (As)",
    "extract_html": extract_html,
    "local_image": "https://upload.wikimedia.org/wikipedia/commons/3/3b/Arsenic_%2833_As%29.jpg",
    "atomic_mass": 74.9215956,
    "density": 5.727,
    "melt": None,
    "boil": None,
    "phase": "Solid",
    "discovered_by": "Bronze Age",
    "electron_configuration": "1s2 2s2 2p6 3s2 3p6 4s2 3d10 4p3",
    "electronegativity_pauling": 2.18,
    "electron_affinity": 77.65,
    "ionization_energies": [
        947,
        1798,
        2735,
        4837,
        6043,
        12310
    ],
    "sections": {
        "Characteristics": characteristics_html,
        "Isotopes": isotopes_html,
        "Occurrence": occurrence_html,
        "History": history_html,
        "Applications": applications_html,
        "Biological role": biological_role_html
    }
}

with open(output_path, "w", encoding="utf-8") as f:
    json.dump(arsenic_data, f, indent=4, ensure_ascii=False)

print(f"Successfully generated {output_path}")
