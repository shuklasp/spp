import json

extract_html = """
<p>Samarium is a fascinating silvery-white metal with the symbol Sm and atomic number 62. Though it sounds like it might be from another world, samarium is part of the "rare earth" family (specifically, the lanthanides) and is found right here on Earth. Discovered in 1879 by French chemist Paul-Émile Lecoq de Boisbaudran, it was the very first chemical element to be named after a real person—Vassili Samarsky-Bykhovets, a Russian mine official!</p>
<p>Samarium is famous for making incredibly powerful magnets. When combined with cobalt to make "samarium-cobalt" magnets, it creates a magnetic force that is not only incredibly strong but can survive blistering temperatures—over 700°C (1,292°F)—without losing its power! This makes it a superstar in defense, aerospace, and everyday gadgets.</p>
<p>In the medical world, a radioactive version of samarium (Samarium-153) is a cancer-fighting hero, used in medicines that target and kill cancer cells in bones. Another form (Samarium-149) acts as a safety sponge in nuclear reactors by soaking up stray neutrons.</p>
<p>In India, samarium plays a critical strategic role. Extracted primarily from the rich monazite sands found along the Indian coastline, it is a key focus of India's "National Critical Mineral Mission." Facilities like the Rare Earth Permanent Magnet (REPM) plant in Visakhapatnam are turning domestic samarium into high-tech magnets to build a self-reliant future in advanced technology!</p>
"""

physical_properties = """
<p>If you held a piece of samarium in your hand, it would feel similar to zinc in terms of hardness and weight. When freshly cut, it sparkles with a bright, silvery shine. However, if left out in the open air, it slowly tarnishes and loses its luster.</p>
<p>Samarium loves to change its internal shape depending on the temperature and pressure. At room temperature, its atoms are arranged in one pattern, but if you heat it up to over 700°C (1,300°F), the atoms shift into a new arrangement. Heat it even more, and they shift again!</p>
<p>One of the coolest physical quirks of samarium is its magnetic personality. While the metal itself is paramagnetic (weakly attracted to magnets) at room temperature, it becomes uniquely magnetic when chilled to nearly absolute zero. Furthermore, scientists have discovered that mixing a tiny amount of samarium into certain iron-based materials can turn them into "superconductors"—materials that conduct electricity with zero resistance—at relatively warm temperatures for superconductors!</p>
"""

chemical_properties = """
<p>Samarium is quite a reactive metal. If you leave it exposed to air, it slowly rusts at room temperature. If it gets too hot (around 150°C or 302°F), it can even burst into flames! Because it loves reacting with oxygen and moisture, it has to be stored carefully, often sealed tightly under an inert gas like argon or submerged in mineral oil.</p>
<p>When samarium meets water, a chemical dance begins. It reacts slowly with cold water but much more rapidly with hot water, bubbling up as it releases hydrogen gas and forms samarium hydroxide. If you drop it into certain acids, like dilute sulfuric acid, it dissolves easily and creates a beautiful pale-green to yellow liquid!</p>
"""

isotopes = """
<p>In nature, samarium is a mix of five perfectly stable forms (called isotopes) and two slightly radioactive ones that break down incredibly slowly. The most common form is Samarium-152, making up over a quarter of all natural samarium.</p>
<p>One of its slightly radioactive forms, Samarium-147, decays so slowly—taking over a hundred billion years—that scientists use it as a "geological clock." By measuring how much of it has broken down, geologists can determine the age of ancient rocks and meteorites!</p>
<p>Samarium also has some fascinating artificial forms created in labs. Samarium-149 is famous for its ability to absorb neutrons, making it an essential "safety net" to control the reactions in nuclear power plants. On the medical frontier, Samarium-153, which is highly radioactive but short-lived (with a half-life of less than two days), is used in a drug called Quadramet. When injected into patients, it safely travels to the bones and destroys pain-causing cancer cells.</p>
"""

history = """
<p>The story of samarium is a tale of international discovery. In the late 19th century, scientists across Europe were racing to discover new elements in strange minerals. French chemist Paul-Émile Lecoq de Boisbaudran officially discovered samarium in Paris in 1879. He extracted it from a heavy, dark mineral called "samarskite."</p>
<p>Interestingly, samarskite was named after Vassili Samarsky-Bykhovets, a Russian mine official who had allowed scientists to study the mineral samples from the Ural Mountains. Because Boisbaudran named the element after the mineral, Samarium became the very first element on the periodic table to indirectly honor a real person!</p>
<p>For a long time, samarium didn't have many practical uses. But in the 1950s, new technologies allowed scientists to separate it perfectly, and soon they discovered its amazing magnetic properties and nuclear benefits.</p>
"""

applications = """
<p>Samarium is a true unsung hero of modern technology. Its most famous role is in <strong>Samarium-Cobalt (SmCo) magnets</strong>. Unlike regular magnets, which can lose their power when they get too hot, samarium-cobalt magnets stay incredibly strong even at blistering temperatures up to 700°C (1,292°F). This makes them essential for things that run hot, like jet engines, military radar systems, and precision aerospace instruments.</p>
<p>Beyond magnets, samarium is a lifesaver. The radioactive isotope Samarium-153 is the active ingredient in specialized cancer medicines that target bone tumors. Meanwhile, Samarium-149 is used to build control rods for nuclear reactors because it acts like a sponge, safely absorbing stray neutrons to keep the nuclear reaction stable.</p>
<p><strong>The Indian Context and Modern Research:</strong></p>
<p>In India, samarium holds immense strategic value. The country is home to vast reserves of monazite sands along its coastal regions, which are rich in rare earth elements like samarium. Recognizing its importance for national defense and green technology, the Indian government's <strong>National Critical Mineral Mission</strong> focuses on building a self-reliant rare earth supply chain.</p>
<p>A shining example of this is the dedicated Rare Earth Permanent Magnet (REPM) plant in Visakhapatnam, Andhra Pradesh, operated by IREL (India) Limited. This facility takes domestic samarium and turns it into the high-performance SmCo magnets needed for India's defense and space programs.</p>
<p>Indian scientists are also at the cutting edge of samarium research. Institutes like the Indian Institute of Space Science and Technology (IIST) and the Institute of Chemical Technology (ICT) in Mumbai are actively exploring new uses for the element. Modern Indian research includes creating samarium-doped glasses for specialized radiation shielding and developing samarium-based nanomaterials that could one day be used for advanced biological imaging and disease diagnosis. From ancient coastal sands to futuristic space and medical technologies, samarium is a vital part of India's scientific journey!</p>
"""

with open('c:/projects/apache/school1/samarium_temp.json', 'r', encoding='utf-8') as f:
    d = json.load(f)

d['extract_html'] = extract_html.strip()
d['sections'] = {
    'Physical properties': physical_properties.strip(),
    'Chemical properties': chemical_properties.strip(),
    'Isotopes': isotopes.strip(),
    'History': history.strip(),
    'Applications': applications.strip()
}

import os
os.makedirs('c:/projects/apache/school1/src/ptable/data/drafts', exist_ok=True)

with open('c:/projects/apache/school1/src/ptable/data/drafts/Samarium.json', 'w', encoding='utf-8') as out:
    json.dump(d, out, indent=2)

print("Saved to drafts/Samarium.json")
