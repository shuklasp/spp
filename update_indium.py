import json

file_path = 'src/ptable/data/drafts/Indium.json'
with open(file_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

data['extract_html'] = """<p><strong>Indium</strong> (symbol <strong>In</strong>, atomic number 49) is a brilliant, silvery-white metal that is incredibly soft—so soft you can actually bite into it or cut it with a butter knife! When bent, it produces a high-pitched "squealing" sound known as a "tin cry."</p>
<p>Though not named after India directly, its name has a deep Indian connection. It comes from the indigo blue line seen in its light spectrum, and the word "indigo" is derived from the Latin <em>indicum</em>, meaning "of India" (referring to the famous indigo dye historically exported from the Indian subcontinent).</p>
<p>Today, indium is the unsung hero of the digital age. In the form of indium tin oxide (ITO), it is a vital, transparent, electrically conductive coating used in almost every flat-screen TV, smartphone touch screen, and solar panel.</p>"""

data['sections'] = {
    "Isotopes": """<p>In nature, indium is a fascinating anomaly. It is made up almost entirely of a single radioactive isotope, Indium-115, which accounts for about 95.7% of all natural indium. You might wonder why we aren't worried about its radioactivity! The secret lies in its incredibly slow decay: its half-life is 441 trillion years, which is nearly 30,000 times longer than the current age of the universe. The rest of natural indium is made up of the stable isotope Indium-113. It is extremely rare for an element to have a radioactive isotope be far more abundant than its stable one.</p>""",
    
    "History": """<p>Indium was discovered in 1863 in Germany by chemists Ferdinand Reich and Hieronymus Theodor Richter. Reich, who was colorblind, was examining zinc ores and asked Richter to look at the colored lines produced in a spectroscope. Richter spotted a brilliant, never-before-seen blue line. They named the new element <em>indium</em> after this indigo-blue spectral line.</p>
<p>The name holds a special historical connection to <strong>India</strong>. The word "indigo" comes from the Latin word <em>indicum</em>, which literally translates to "of India." For centuries, India was the world's primary supplier of the rich indigo dye, cementing the country's legacy in the very name of this vital modern element. Richter successfully isolated the pure metal a year later, but the two scientists eventually had a falling out over who deserved the credit for the discovery!</p>""",
    
    "Occurrence": """<p>In the cosmos, indium is born in the hearts of low-to-medium mass stars over thousands of years through a process called slow neutron capture (the s-process). On Earth, indium is quite rare—about as scarce as silver—and it never forms its own concentrated minerals or "indium mines."</p>
<p>Instead, indium hides away as a trace element within other mineral deposits, most notably zinc sulfide (sphalerite). Because of this, practically all of the world's indium is extracted as a byproduct of smelting zinc and, to a lesser extent, lead.</p>""",
    
    "Applications": """<p>Indium's unique properties make it a cornerstone of modern electronics. Its most famous application is as <strong>Indium Tin Oxide (ITO)</strong>. This remarkable compound is optically transparent but conducts electricity, making it the perfect coating for the touch screens on our smartphones, tablets, flat-screen televisions, and even solar panels. It's also used in low-melting-point alloys, high-vacuum seals, and to make the semiconductors that power blue and white LED lights.</p>
<p>In <strong>India</strong>, the rapidly growing electronics market and the push for technological self-reliance have sparked important scientific research regarding indium. Since India does not possess primary indium mining reserves, modern Indian scientific contributions are heavily focused on sustainability and recovery. Institutions like <strong>IIT Roorkee</strong>, supported by India's Ministry of Mines, have pioneered research into recycling electronic waste (e-waste) to recover valuable indium from discarded LCD screens. These domestic innovations are crucial for securing the critical materials needed for India's booming semiconductor and electronics manufacturing industries.</p>"""
}

with open(file_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2)

print("Updated successfully.")
