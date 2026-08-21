import json
import os

draft_dir = r"c:\projects\apache\school1\src\ptable\data\drafts"
os.makedirs(draft_dir, exist_ok=True)
output_path = os.path.join(draft_dir, "Chlorine.json")

# Load original data to preserve all exact physical/numeric fields
master_path = r"c:\projects\apache\school1\src\ptable\data\master_elements.json"
with open(master_path, "r", encoding="utf-8") as f:
    master_data = json.load(f)

orig_cl = master_data["Cl"]

chlorine_data = {
    "symbol": orig_cl["symbol"],
    "name": orig_cl["name"],
    "atomic": orig_cl["atomic"],
    "category": orig_cl["category"],
    "description": orig_cl["description"],
    "extract_html": (
        '<p><b><a href="/school1/ptable/element/Cl" class="wiki-link" title="Chlorine (Cl)">Chlorine</a></b> '
        '(symbol <b>Cl</b>, atomic number 17) is a yellow-green, highly reactive gas at room temperature '
        'and the second-lightest member of the halogen family, sitting right between '
        '<a href="/school1/ptable/element/F" class="wiki-link" title="Fluorine (F)">fluorine</a> and '
        '<a href="/school1/ptable/element/Br" class="wiki-link" title="Bromine (Br)">bromine</a> on the periodic table. '
        'Known for its sharp, suffocating odor reminiscent of household bleach and swimming pool purifiers, chlorine '
        'is a powerhouse oxidizing agent. It boasts the single highest electron affinity of all chemical elements '
        '(348.575 kJ/mol) and holds the third-highest electronegativity (3.16 on the Pauling scale), ranking just behind '
        '<a href="/school1/ptable/element/O" class="wiki-link" title="Oxygen (O)">oxygen</a> and '
        '<a href="/school1/ptable/element/F" class="wiki-link" title="Fluorine (F)">fluorine</a>. Because it eagerly '
        'snatches electrons from other substances, pure chlorine gas never exists free in nature; instead, it is bound '
        'up in vast salt deposits and ocean waters—most famously in common table salt '
        '(<a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> chloride, NaCl)—which '
        'has sustained human life, ancient medicine, and global industry for thousands of years.</p>'
    ),
    "local_image": orig_cl["local_image"],
    "atomic_mass": orig_cl["atomic_mass"],
    "density": orig_cl["density"],
    "melt": orig_cl["melt"],
    "boil": orig_cl["boil"],
    "phase": orig_cl["phase"],
    "discovered_by": orig_cl["discovered_by"],
    "electron_configuration": orig_cl["electron_configuration"],
    "electronegativity_pauling": orig_cl["electronegativity_pauling"],
    "electron_affinity": orig_cl["electron_affinity"],
    "ionization_energies": orig_cl["ionization_energies"],
    "sections": {
        "History": (
            '</div>\n'
            '<p>The history of chlorine begins long before it was isolated as a gas, inextricably linked with its most '
            'famous compound: <a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> chloride '
            '(common salt). Archaeological discoveries show that humans harvested natural rock salt as early as 3000 BC '
            'and evaporated brine to extract salt as early as 6000 BC. In ancient India, salt held immense medical, dietary, '
            'and cultural significance. The classic Ayurvedic treatises of the <i>Brihat-trayi</i>—the <i>Charaka Samhita</i>, '
            '<i>Sushruta Samhita</i>, and <i>Ashtanga Hridaya</i>—detailed <i>Pancha Lavana</i> (the Five Sacred Salts). '
            'Among these, <i>Saindhava Lavana</i> (rock salt from the Salt Range) was revered as the finest, cooling, and '
            'most balanced salt (<i>uttama</i>), used in over 60 therapeutic formulations for digestion, eye health, and '
            'respiratory strength, alongside <i>Samudra Lavana</i> (sea salt) and <i>Sauvarchala Lavana</i> (black salt).</p>\n'
            '<p>Centuries later, medieval Indian alchemists practicing <i>Rasa Shastra</i> (iatrochemistry) distilled mineral '
            'salts and alkalis using specialized apparatus (<i>Tiryakpatana</i>) to prepare acidic liquids known as '
            '<i>Dravaka Kalpana</i>. Formulations like <i>Shankha Dravaka</i> and <i>Lavanamla</i> contained crude hydrochloric '
            'acid (HCl)—the primary acid of chlorine—which alchemists utilized for dissolving tough minerals, refining metals, '
            'and treating severe digestive disorders.</p>\n'
            '<p>Elemental chlorine gas was first prepared in 1774 by Swedish-German chemist Carl Wilhelm Scheele. He produced '
            'a pale green gas by reacting hydrochloric acid (then called spirit of salt) or a mixture of dilute sulfuric acid '
            'and <a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> chloride with '
            '<a href="/school1/ptable/element/Mn" class="wiki-link" title="Manganese (Mn)">manganese</a> dioxide. However, Scheele '
            'mistakenly believed the suffocating gas was a compound containing <a href="/school1/ptable/element/O" class="wiki-link" title="Oxygen (O)">oxygen</a> '
            '("dephlogisticated muriatic acid"). It was not until 1810 that English chemist Sir Humphry Davy proved that the '
            'gas was a fundamental chemical element, naming it <i>chlorine</i> after the Ancient Greek word <i>khloros</i>, '
            'meaning "pale green" or "yellowish-green". Later in the 19th century, industrial processes like the Leblanc '
            'process produced massive quantities of byproduct hydrochloric acid, which was converted back into chlorine using '
            '<a href="/school1/ptable/element/Mn" class="wiki-link" title="Manganese (Mn)">manganese</a> dioxide recycled by the Weldon process.</p>\n'
            '<p>In modern history, common salt—chlorine\'s universal source—became the supreme symbol of India\'s freedom struggle. '
            'In March 1930, Mahatma Gandhi led the legendary 241-mile Dandi March (Salt Satyagraha) to the coastal village of Dandi '
            'in Gujarat to harvest salt illegally in defiance of the oppressive British colonial salt tax. Gandhi\'s simple act '
            'of picking up a handful of natural salt boiled from sea brine ignited nationwide civil disobedience, proving how this '
            'humble chlorine compound could shake an empire and lay the groundwork for Indian independence.</p>\n'
            '<div class="mw-heading mw-heading3">'
        ),
        "Isotopes": (
            '</div>\n'
            '<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r1368532237" /><div role="note" class="hatnote navigation-not-searchable">Main article: Isotopes of <a href="/school1/ptable/element/Cl" class="wiki-link" title="Chlorine (Cl)">chlorine</a></div>\n'
            '<p>Natural chlorine consists of two stable isotopes: chlorine-35 (<sup>35</sup>Cl), which makes up approximately '
            '76% (75.76%) of Earth\'s chlorine, and chlorine-37 (<sup>37</sup>Cl), which accounts for the remaining 24% (24.24%). '
            'Both stable isotopes are forged in giant stars during cosmic stellar evolution through '
            '<a href="/school1/ptable/element/O" class="wiki-link" title="Oxygen (O)">oxygen</a>-burning and '
            '<a href="/school1/ptable/element/Si" class="wiki-link" title="Silicon (Si)">silicon</a>-burning nuclear fusion '
            'processes. Both <sup>35</sup>Cl and <sup>37</sup>Cl possess a nuclear spin of 3/2+. While this allows chlorine to '
            'be studied using Nuclear Magnetic Resonance (NMR) spectroscopy, their non-spherical nuclear charge distribution '
            'produces a non-zero nuclear quadrupole moment. This causes rapid quadrupolar relaxation, which broadens NMR spectral lines.</p>\n'
            '<p>All other chlorine isotopes are radioactive, with half-lives too short to have survived since the formation of '
            'planet Earth. In laboratory research, the two most commonly used synthetic radioisotopes are chlorine-36 '
            '(<sup>36</sup>Cl, half-life <i>t</i><sub>1/2</sub> = 3.0 × 10<sup>5</sup> years) and chlorine-38 '
            '(<sup>38</sup>Cl, half-life <i>t</i><sub>1/2</sub> = 37.2 minutes), both produced through neutron activation '
            'of natural chlorine in nuclear reactors.</p>\n'
            '<p>Chlorine-36 (<sup>36</sup>Cl) is by far the most stable radioisotope of chlorine. Radioisotopes lighter than '
            '<sup>35</sup>Cl decay primarily by electron capture into isotopes of '
            '<a href="/school1/ptable/element/S" class="wiki-link" title="Sulfur (S)">sulfur</a>, whereas radioisotopes '
            'heavier than <sup>37</sup>Cl decay via beta (β<sup>−</sup>) decay into isotopes of '
            '<a href="/school1/ptable/element/Ar" class="wiki-link" title="Argon (Ar)">argon</a>. Chlorine-36 is unique '
            'because it can decay by either pathway—decaying into stable <sup>36</sup>S via electron capture or into stable '
            '<sup>36</sup>Ar via beta emission. In nature, trace amounts of cosmogenic <sup>36</sup>Cl exist at a tiny ratio '
            'of about (7–10) × 10<sup>−13</sup> to 1 relative to stable chlorine. It forms in Earth\'s atmosphere when cosmic '
            'ray protons smash into argon-36 (spallation). In the upper meter of the Earth\'s crust (lithosphere), <sup>36</sup>Cl '
            'is generated primarily by thermal neutron activation of <sup>35</sup>Cl and spallation of '
            '<a href="/school1/ptable/element/K" class="wiki-link" title="Potassium (K)">potassium</a>-39 (<sup>39</sup>K) and '
            '<a href="/school1/ptable/element/Ca" class="wiki-link" title="Calcium (Ca)">calcium</a>-40 (<sup>40</sup>Ca). '
            'Deeper underground, muon capture by <sup>40</sup>Ca becomes the main mechanism for creating <sup>36</sup>Cl.</p>\n'
            '<p>In modern Indian science and hydrology, cosmic <sup>36</sup>Cl serves as an invaluable geochemical clock. '
            'Hydrogeologists from institutions like the Bhabha Atomic Research Centre (BARC) and the Physical Research Laboratory '
            '(PRL) use <sup>36</sup>Cl isotope tracing to accurately age ancient underground aquifers and measure groundwater '
            'recharge rates in hyper-arid regions such as Rajasthan\'s Thar Desert and Gujarat\'s Rann of Kutch, ensuring '
            'sustainable water resource management for farming and rural communities.</p>\n'
            '<div class="mw-heading mw-heading2">'
        ),
        "Occurrence": (
            '</div>\n'
            '<figure class="mw-default-size" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/e249e1ca85c3039b85abd7e65e64c7aa.jpg" decoding="async" width="190" height="399" style="--mw-file-upright: 0.75" class="mw-file-element mw-file-upright"  data-file-width="787" data-file-height="1653" /><figcaption>Liquid <a href="/school1/ptable/element/Cl" class="wiki-link" title="Chlorine (Cl)">chlorine</a> analysis</figcaption></figure>\n'
            '<p>Because chlorine is far too chemically reactive to exist as a free element in nature, it is found entirely in the '
            'form of chloride salts. Chlorine is the 20th most abundant element in Earth\'s crust, averaging about 126 parts per '
            'million (ppm) by weight in rock minerals like halite (<a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> chloride), '
            'sylvite (<a href="/school1/ptable/element/K" class="wiki-link" title="Potassium (K)">potassium</a> chloride), and carnallite. '
            'However, continental rocks pale in comparison to Earth\'s oceans, which hold an immense reservoir of dissolved chloride '
            'ions (making up nearly 1.9% of total ocean mass). Inland salt lakes and underground brine wells—such as the Dead Sea in '
            'Israel and the Great Salt Lake in Utah—contain even higher concentrations of chloride.</p>\n'
            '<p>India possesses vast natural salt reserves and is the world\'s 3rd largest producer of common salt (NaCl), generating '
            'over 30 million tonnes annually. India\'s major salt harvesting hubs include the vast white salt flats of the Rann of Kutch '
            'and Bhavnagar in Gujarat, the Sambhar Salt Lake in Rajasthan, the coastal salt pans of Tuticorin (Thoothukudi) in Tamil Nadu, '
            'and Chilika Lake in Odisha. Over 70% of India\'s total salt production comes from Gujarat alone.</p>\n'
            '<p>While small laboratory batches of chlorine gas can be prepared by combining hydrochloric acid with '
            '<a href="/school1/ptable/element/Mn" class="wiki-link" title="Manganese (Mn)">manganese</a> dioxide (MnO<sub>2</sub>), '
            'the gas is readily available in pressurized steel cylinders. Industrially, elemental chlorine is manufactured on a massive '
            'scale by the electrolysis of aqueous <a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> '
            'chloride (saltwater brine). Known as the chloralkali process (industrialized in 1892), this electrochemical reaction '
            'generates chlorine gas at the anode, while yielding <a href="/school1/ptable/element/H" class="wiki-link" title="Hydrogen (H)">hydrogen</a> '
            'gas and <a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> hydroxide (NaOH, or caustic '
            'soda—the most valuable co-product) at the cathode according to the stoichiometric equation:</p>\n'
            '<dl><dd>2 NaCl + 2 H<sub>2</sub>O → Cl<sub>2</sub> + H<sub>2</sub> + 2 NaOH</dd></dl>\n'
            '<p>In India, modern scientific leadership for salt and chlor-alkali technology is anchored by the CSIR-Central Salt & Marine '
            'Chemicals Research Institute (CSIR-CSMCRI) in Bhavnagar, Gujarat. CSMCRI develops advanced solar salt crystallization '
            'techniques, brine purification technologies, and zero-liquid discharge systems. Major Indian industrial conglomerates—such '
            'as Gujarat Alkalies and Chemicals Limited (GACL), DCM Shriram (with major plants in Kota, Rajasthan and Jhagadia, Gujarat), '
            'DCW Ltd, and Grasim Industries—operate giant membrane-cell chlor-alkali facilities that produce millions of tonnes of chlorine '
            'and caustic soda annually to fuel India\'s rapidly expanding chemical sector.</p>\n'
            '<div class="mw-heading mw-heading2">'
        ),
        "Applications": (
            '</div>\n'
            '<figure class="mw-default-size" typeof="mw:File/Thumb"><img src="/school1/ptable/theme-assets/default/images/inline/4e88489a3b6b6b193c8281926bc337c4.jpg" decoding="async" width="250" height="188" class="mw-file-element"  data-file-width="4032" data-file-height="3024" /><figcaption>A railway tank car carrying <a href="/school1/ptable/element/Cl" class="wiki-link" title="Chlorine (Cl)">chlorine</a>, displaying hazardous materials information including a diamond-shaped U.S. DOT placard showing a UN number</figcaption></figure>\n'
            '<p>Common salt (<a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> chloride) is the '
            'ultimate starting raw material for nearly all commercial chlorine production. Worldwide, about 15,000 distinct '
            'chlorine-containing chemical compounds are commercially traded. Quantitatively, roughly 63% of all elemental chlorine '
            'gas produced is used to synthesize organic chemical compounds, 18% is dedicated to manufacturing inorganic chlorine chemicals, '
            'and the remaining 19% is used directly for household bleaches, industrial bleaching of paper pulp, and public water disinfection.</p>\n'
            '<p>The single largest organic consumer of chlorine by volume is 1,2-dichloroethane, which is converted into vinyl chloride '
            'monomer—the essential building block for polyvinyl chloride (PVC) plastic. PVC is one of the world\'s most widely '
            'manufactured polymers, vital for producing durable water pipes, electrical wire insulation, window frames, blood bags, '
            'and medical tubing. In India, PVC pipe manufacturing plays a transformational role in national agricultural irrigation '
            'networks and drinking water infrastructure under rural development programs. Other major industrial organochlorines include '
            'methyl chloride, methylene chloride, chloroform, vinylidene chloride, trichloroethylene, perchloroethylene (used in dry '
            'cleaning), allyl chloride, epichlorohydrin (for epoxy resins), chlorobenzene, dichlorobenzenes, and trichlorobenzenes.</p>\n'
            '<p>Key inorganic chlorine compounds include hydrochloric acid (HCl), dichlorine monoxide (Cl<sub>2</sub>O), hypochlorous acid '
            '(HOCl), <a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> chlorate (NaClO<sub>3</sub>), '
            '<a href="/school1/ptable/element/Al" class="wiki-link" title="Aluminium (Al)">aluminium</a> trichloride (AlCl<sub>3</sub>, '
            'an essential industrial catalyst), <a href="/school1/ptable/element/Si" class="wiki-link" title="Silicon (Si)">silicon</a> '
            'tetrachloride (SiCl<sub>4</sub>, used to manufacture ultra-pure solar silicon and fiber-optic glass), '
            '<a href="/school1/ptable/element/Sn" class="wiki-link" title="Tin (Sn)">tin</a> tetrachloride (SnCl<sub>4</sub>), '
            '<a href="/school1/ptable/element/P" class="wiki-link" title="Phosphorus (P)">phosphorus</a> trichloride (PCl<sub>3</sub>), '
            '<a href="/school1/ptable/element/P" class="wiki-link" title="Phosphorus (P)">phosphorus</a> pentachloride (PCl<sub>5</sub>), '
            'phosphorus oxychloride (POCl<sub>3</sub>), <a href="/school1/ptable/element/As" class="wiki-link" title="Arsenic (As)">arsenic</a> '
            'trichloride (AsCl<sub>3</sub>), <a href="/school1/ptable/element/Sb" class="wiki-link" title="Antimony (Sb)">antimony</a> '
            'trichloride (SbCl<sub>3</sub>), <a href="/school1/ptable/element/Sb" class="wiki-link" title="Antimony (Sb)">antimony</a> '
            'pentachloride (SbCl<sub>5</sub>), <a href="/school1/ptable/element/Bi" class="wiki-link" title="Bismuth (Bi)">bismuth</a> '
            'trichloride (BiCl<sub>3</sub>), and <a href="/school1/ptable/element/Zn" class="wiki-link" title="Zinc (Zn)">zinc</a> '
            'chloride (ZnCl<sub>2</sub>). Metal chlorides are also crucial metallurgical intermediates for extracting pure metals: for instance, '
            '<a href="/school1/ptable/element/Mg" class="wiki-link" title="Magnesium (Mg)">magnesium</a> chloride, '
            '<a href="/school1/ptable/element/Ti" class="wiki-link" title="Titanium (Ti)">titanium</a> tetrachloride (TiCl<sub>4</sub> in '
            'the Kroll process), <a href="/school1/ptable/element/Zr" class="wiki-link" title="Zirconium (Zr)">zirconium</a> tetrachloride, '
            'and <a href="/school1/ptable/element/Hf" class="wiki-link" title="Hafnium (Hf)">hafnium</a> tetrachloride serve as indispensable '
            'precursors for producing pure metal sponges.</p>\n'
            '<p>Chlorine is universally famous as a life-saving water disinfectant. Treating drinking water with chlorine gas, '
            '<a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> hypochlorite (liquid bleach), or '
            '<a href="/school1/ptable/element/Ca" class="wiki-link" title="Calcium (Ca)">calcium</a> hypochlorite (bleaching powder) '
            'kills deadly waterborne pathogens like cholera, typhoid, and dysentery. In India, public health chlorination is the backbone '
            'of urban municipal water treatment plants and rural drinking water schemes like the Jal Jeevan Mission. To protect flood-affected '
            'communities during natural disasters, scientists at the CSIR-National Environmental Engineering Research Institute (CSIR-NEERI) '
            'in Nagpur developed <i>NEERI-ZAR</i>—a portable, gravity-based water purification system that uses controlled chemical chlorination '
            'to deliver clean drinking water to disaster relief camps across India without requiring electricity.</p>\n'
            '<div class="mw-heading mw-heading3">'
        ),
        "Biological role": (
            '</div>\n'
            '<p>The chloride anion (Cl<sup>−</sup>) is an indispensable macro-nutrient and major extracellular electrolyte required '
            'for human life and animal metabolism. In the human body, chloride ions play a pivotal role in maintaining fluid balance, '
            'regulating osmotic pressure, preserving proper blood pH, and conducting nerve impulses across cell membranes. Chlorine is '
            'also essential for digestion: specialised parietal cells in the stomach lining pump chloride and '
            '<a href="/school1/ptable/element/H" class="wiki-link" title="Hydrogen (H)">hydrogen</a> ions into gastric juice to form '
            'hydrochloric acid (HCl), which creates an acidic environment (pH 1.5 to 3.5) necessary to activate digestive enzymes '
            'like pepsin and kill ingested bacteria. The primary dietary source of chloride is common table salt '
            '(<a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> chloride), alongside sea salt, '
            'tomatoes, celery, and olives.</p>\n'
            '<p>Disruptions in blood chloride concentration lead to significant electrolyte imbalances:</p>\n'
            '<p><b>Hypochloremia</b> (abnormally low blood chloride concentration) rarely occurs on its own and is typically accompanied '
            'by other metabolic disturbances. It can result from prolonged vomiting, severe sweating, kidney disease, or chronic '
            'hypoventilation, and is often linked with chronic respiratory acidosis.</p>\n'
            '<p><b>Hyperchloremia</b> (abnormally high blood chloride concentration) usually produces no obvious symptoms by itself, '
            'but when symptoms occur, they closely resemble hypernatremia (excessive <a href="/school1/ptable/element/Na" class="wiki-link" title="Sodium (Na)">sodium</a> '
            'levels), including extreme thirst, confusion, muscle twitching, and high blood pressure. Severe hyperchloremia can impair '
            '<a href="/school1/ptable/element/O" class="wiki-link" title="Oxygen (O)">oxygen</a> transport in red blood cells. A sudden drop '
            'in blood chloride can cause cerebral dehydration (brain cell shrinkage), whereas overly rapid fluid rehydration to treat it can '
            'trigger dangerous cerebral edema (brain swelling).</p>\n'
            '<div class="mw-heading mw-heading2">'
        )
    }
}

with open(output_path, "w", encoding="utf-8") as f:
    json.dump(chlorine_data, f, indent=4, ensure_ascii=False)

print(f"Successfully generated {output_path}")
