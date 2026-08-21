import json
import os

with open('U_temp.json', 'r', encoding='utf-8') as f:
    d = json.load(f)

# The new extract_html
d['extract_html'] = """<p><strong>Uranium</strong> is a heavy, silvery-grey metal with the symbol <strong>U</strong> and atomic number 92. As a member of the actinide series on the periodic table, it is best known for its unique radioactive properties that changed the course of modern history. Every uranium atom has 92 protons and 92 electrons (with 6 valence electrons). It naturally radioactively decays by emitting alpha particles. Its half-life—ranging from 159,200 years to an astonishing 4.5 billion years—makes it a crucial tool for scientists determining the age of the Earth.</p>
<p>Uranium is the heaviest element that occurs in primordial nature. It is about 70% denser than lead, though slightly less dense than gold or tungsten. You can find traces of it almost everywhere—in rocks, soil, and even the ocean. Over 99% of naturally occurring uranium is the isotope uranium-238, which has 146 neutrons, while the remaining fraction is mostly uranium-235, with 143 neutrons.</p>
<p>Uranium's unique nuclear properties are the backbone of both nuclear power and nuclear weaponry. Uranium-235 is the only naturally occurring isotope that is <em>fissile</em>, meaning it can readily sustain a nuclear chain reaction. Because it makes up such a small fraction of natural uranium, the material often has to be "enriched" for use. Meanwhile, the far more common uranium-238 is <em>fertile</em>—it can be converted into fissile plutonium-239 within a reactor. From powering entire cities with electricity to the devastating weapons developed during the Cold War, uranium is deeply embedded in human society.</p>
<p>In India, uranium plays a deeply strategic role. Spearheaded by the visionary Dr. Homi J. Bhabha, India’s three-stage nuclear power program was designed precisely to overcome the country's initially modest uranium reserves, paving the way for utilizing its massive thorium deposits. Historic sites like the Jaduguda mine in Jharkhand (the country's first) and the massive, modern Tummalapalle mine in Andhra Pradesh highlight India's ongoing quest for energy independence and the complex environmental balancing act that comes with uranium extraction.</p>"""

sections = []

# Section 1: Characteristics
sections.append({
    "title": "Characteristics",
    "content": """<p>In its pure form, uranium is a silvery-white, weakly radioactive metal. It has a Mohs hardness of 6—hard enough to scratch glass and comparable to titanium and rhodium. The metal is malleable (able to be hammered into sheets), ductile (able to be drawn into wires), strongly electropositive, slightly magnetic, and a relatively poor conductor of electricity.</p>
<p>Uranium is incredibly dense at 19.1 grams per cubic centimeter, making it denser than lead but slightly less dense than gold and tungsten. It is highly reactive, combining with almost all non-metallic elements (except the noble gases). When exposed to air, it develops a dark, protective layer of uranium dioxide. If ground into a fine powder, it can even react with cold water. In industrial applications, it is usually extracted using acids (like hydrochloric and nitric acids) and converted into compounds like uranium dioxide.</p>
<p>Uranium exists in three different solid phases (allotropes) depending on the temperature:</p>
<ul>
<li><strong>Alpha (orthorhombic):</strong> Stable up to 668 °C.</li>
<li><strong>Beta (tetragonal):</strong> Stable between 668 °C and 775 °C.</li>
<li><strong>Gamma (body-centered cubic):</strong> From 775 °C to its melting point. In this state, the metal is at its most malleable and ductile.</li>
</ul>
<p>Uranium's most famous characteristic is its ability to undergo nuclear fission. In 1938, scientists Otto Hahn, Fritz Strassmann, Lise Meitner, and Otto Robert Frisch discovered that bombarding uranium-235 with slow neutrons causes the nucleus to split in two. This releases an immense amount of energy and more neutrons, triggering a chain reaction. In a nuclear reactor, this chain reaction is carefully controlled using "neutron poisons" like control rods to generate steady heat. In weapons, an uncontrolled reaction results in an explosion. While uranium-238 doesn't easily split on its own (it is fissionable with high-energy fast neutrons but not fissile), it is still a cornerstone of nuclear physics.</p>"""
})

# Section 2: Occurrence and Mining
sections.append({
    "title": "Occurrence and Mining",
    "content": """<p>Despite being known for its rarity and power, uranium is actually quite common. It is the 48th most abundant element in the Earth's crust, found in concentrations of 2 to 4 parts per million—making it roughly 40 times more abundant than silver and about as plentiful as arsenic or molybdenum. The Earth's crust is estimated to hold a staggering 100 trillion kilograms of uranium, with another 10 trillion kilograms dissolved in the oceans. Even common farm soils can contain higher traces due to the use of phosphate fertilizers.</p>
<p>The radioactive decay of uranium, along with thorium and potassium-40, is one of the main heat sources that keeps the Earth's outer core molten and drives the plate tectonics shaping our continents.</p>
<p>Uranium forms hundreds of minerals. The most common ore is uraninite (also called pitchblende), but it is also found in carnotite, autunite, and coffinite. It can be commercially extracted from ores containing as little as 0.1% uranium, as well as from phosphate rock and monazite sands.</p>
<h3>The Indian Context</h3>
<p>India’s pursuit of uranium has deeply shaped its domestic energy and geopolitical strategies. The foundation of this effort was laid by <strong>Dr. Homi J. Bhabha</strong>, the "father of the Indian nuclear program." Aware of India's originally modest uranium deposits, Dr. Bhabha established the Tata Institute of Fundamental Research (TIFR) and the Bhabha Atomic Research Centre (BARC). He conceptualized India's unique three-stage nuclear power program, a long-term plan designed to start with natural uranium and eventually use it to unlock the energy potential of India's vast thorium reserves.</p>
<p>India's uranium mining journey began at <strong>Jaduguda</strong> in the Singhbhum Shear Zone of Jharkhand. Discovered in 1951 and operating commercially by 1967, it was India's first uranium mine. Managed by the Uranium Corporation of India Limited (UCIL), Jaduguda has been the historical backbone of India's nuclear fuel supply, though it has also sparked important conversations regarding environmental sustainability and the health of local indigenous (Adivasi) communities.</p>
<p>More recently, the discovery of the <strong>Tummalapalle mine</strong> in the YSR Kadapa district of Andhra Pradesh transformed India's nuclear outlook. Commissioned in 2012, Tummalapalle is now recognized as having one of the largest uranium reserves in the world, with estimates suggesting up to 85,000 tonnes. Utilizing modern, automated underground mining techniques, it represents a significant leap forward in India's quest for energy independence and safer extraction practices.</p>"""
})

# Section 3: History
sections.append({
    "title": "History",
    "content": """<p>Uranium was discovered in 1789 by the German chemist Martin Heinrich Klaproth, who found it in a mineral called pitchblende. He named the newly discovered element after the planet Uranus, which had been discovered just eight years earlier. In 1841, the French chemist Eugène-Melchior Péligot became the first person to successfully isolate pure uranium metal.</p>
<p>For over a century, uranium was mostly a curiosity. However, in 1896, Henri Becquerel discovered its radioactive properties. By the 1930s, the world of physics was turned upside down when scientists realized that uranium could undergo nuclear fission. During World War II, the Manhattan Project—led by J. Robert Oppenheimer—harnessed this power to build the first atomic bombs. While the "Trinity test" (the first nuclear explosion) and the bomb dropped on Nagasaki used plutonium, the "Little Boy" bomb dropped on Hiroshima in 1945 relied on just 15 pounds of highly enriched uranium-235.</p>
<p>Following the war, the Cold War sparked a massive nuclear arms race between the United States and the Soviet Union, resulting in tens of thousands of nuclear weapons built from uranium and uranium-derived plutonium. Today, significant international efforts costing billions of dollars are dedicated to dismantling these weapons. Highly enriched weapons-grade uranium is often diluted with uranium-238 so it can be safely used to generate electricity in civilian nuclear reactors. However, dealing with the radioactive waste from spent nuclear fuel—mostly consisting of uranium-238 and other hazardous byproducts—remains a major global challenge.</p>"""
})

# Section 4: Isotopes
sections.append({
    "title": "Isotopes",
    "content": """<p>Uranium, like all elements heavier than lead, has no stable forms. Every isotope of uranium is radioactive. However, some decay so slowly that they have existed since the Earth was formed. These are known as primordial nuclides.</p>
<p>Natural uranium is made up almost entirely of three isotopes: <strong>uranium-238</strong> (99.28%), <strong>uranium-235</strong> (0.71%), and <strong>uranium-234</strong> (0.0054%).</p>
<ul>
<li><strong>Uranium-238</strong> is the most stable and abundant, with a half-life of 4.46 billion years—roughly the age of the Earth. It decays very slowly by emitting alpha particles, eventually turning into stable lead-206. While it cannot easily sustain a chain reaction (it is not fissile), it is highly valuable because reactors can convert it into plutonium-239, which is a potent nuclear fuel.</li>
<li><strong>Uranium-235</strong> is the superstar of the nuclear industry. With a half-life of 704 million years, it is the only naturally occurring isotope on Earth that is fissile. When struck by a slow thermal neutron, it splits apart, releasing the tremendous energy used in power plants and nuclear weapons. Its predictable decay into lead-207 makes it incredibly useful for scientists dating ancient rocks.</li>
<li><strong>Uranium-234</strong> occurs in trace amounts as a decay product of U-238 and has a half-life of 245,500 years.</li>
<li><strong>Uranium-233</strong> does not naturally occur in significant amounts but can be artificially "bred" in reactors from thorium-232. It is highly fissile and is the cornerstone of India's future plans for a thorium-based nuclear fuel cycle. With a half-life of 160,000 years, it represents an alternative to U-235 and plutonium fuels.</li>
</ul>
<p>In total, 28 isotopes of uranium have been discovered, ranging in mass from 214 to 242. Some, like uranium-236 (a byproduct of nuclear reactors), are considered long-lived radioactive waste. Others are extremely short-lived, decaying away in a matter of hours or even nanoseconds. Some select isotopes, such as uranium-230, are even being studied for use in targeted alpha-particle therapies for medical treatments.</p>"""
})

d['sections'] = sections

os.makedirs('src/ptable/data/drafts', exist_ok=True)
with open('src/ptable/data/drafts/Uranium.json', 'w', encoding='utf-8') as f:
    json.dump({ "U": d }, f, indent=2, ensure_ascii=False)

print("Done writing to src/ptable/data/drafts/Uranium.json")
