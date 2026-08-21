import json
import os

with open("Ir_original.json", "r", encoding="utf-8") as f:
    ir_data = json.load(f)

extract_html = """
<p><b>Iridium (Ir)</b> is a fascinating and incredibly rare metal that belongs to the platinum group. Known for being the most corrosion-resistant material on Earth and the second-densest naturally occurring element, iridium is both tough and beautiful with its silvery-white appearance.</p>
<p>While you won't find iridium mentioned in ancient Indian texts like the Vedas or Upanishads, it plays a massive role in modern Indian science and geology. For instance, geologists study traces of iridium in India's massive ancient volcanic formations—the Deccan Traps—to understand the catastrophic asteroid impact that wiped out the dinosaurs. Today, it also connects remote parts of India and the world through the Iridium satellite network and is used in cutting-edge space technology developed by Indian research institutes like CSIR-NIIST.</p>
"""

sections = {
    "Characteristics": """
<p>Iridium is famous for its extreme durability. It is a very hard, brittle, silvery-white transition metal of the platinum group. Because of its hardness and brittleness, it is notoriously difficult to machine, shape, or work with. However, its refusal to break down or corrode makes it incredibly valuable for specialized tasks.</p>
""",
    "Physical properties": """
<p>Imagine a metal so dense that a block the size of a milk carton would weigh as much as a person! Iridium is the second-densest element (just behind osmium). It has a very high melting point, meaning it can withstand extreme heat without losing its shape or strength. This makes it perfect for environments where most other materials would simply melt away.</p>
""",
    "Chemical properties": """
<p>Chemically, iridium is a superstar when it comes to resisting corrosion. It won't easily react with water, oxygen, or even strong acids. In fact, it is the most corrosion-resistant metal known. It only begins to react under extreme conditions, like with certain molten salts, making it an ultimate protector against chemical wear and tear.</p>
""",
    "History": """
<p>Iridium was discovered in 1803 by the British chemist Smithson Tennant, who found it in the leftover residue from dissolving platinum ores. He named it after the Greek goddess Iris, the personification of the rainbow, because of the strikingly colorful salts it formed.</p>
<p>In the context of India, it's important to dispel a common modern myth: there are scams claiming that ancient Indian temple finials (kalasams) or old copper coins contain magical "iridium" that generates power or brings wealth. In truth, ancient Indian metallurgy, while highly advanced (like the famous Iron Pillar of Delhi), did not utilize iridium. The metal's true "magic" lies in its modern scientific applications, not ancient myths.</p>
""",
    "Isotopes": """
<p>Like many elements, iridium exists in different forms called isotopes. In nature, iridium is found mostly as two stable isotopes: Iridium-191 and Iridium-193. Scientists also use synthetic, radioactive isotopes of iridium for important medical and industrial applications, such as treating certain types of cancer or checking the structural integrity of metal parts.</p>
""",
    "Occurrence": """
<p>Iridium is one of the rarest elements in the Earth's crust. Interestingly, it is much more common in meteorites and asteroids. When scientists find a concentrated layer of iridium in the Earth's rock record, it often points to a massive asteroid impact. A famous example is the K-Pg boundary layer, which marks the extinction of the dinosaurs. In India, researchers study iridium anomalies in the Deccan Traps (huge volcanic rock layers) to piece together the timeline of this global extinction event.</p>
""",
    "Applications": """
<p>Because it is so hard and heat-resistant, iridium is used in products that need to survive extreme conditions. It is used to make the tips of long-lasting spark plugs, high-quality fountain pen nibs, and crucial components for aircraft engines and deep-water pipes.</p>
<p>In modern India, scientific institutes like CSIR-NIIST have developed advanced iridium coatings for carbon-composites used in space exploration. Additionally, the global "Iridium" satellite constellation provides essential communication services, recently authorized for critical maritime and emergency connectivity across India's remote regions.</p>
"""
}

ir_data["extract_html"] = extract_html
ir_data["sections"] = sections

# The prompt says: "Save the updated JSON object for the element (with rewritten extract_html and sections, keeping other fields intact) to src/ptable/data/drafts/[Element Name].json"
# "It should just be the object for that element, or a dict with the element symbol as the key."

output_dict = {
    "Ir": ir_data
}

os.makedirs("src/ptable/data/drafts", exist_ok=True)
with open("src/ptable/data/drafts/Iridium.json", "w", encoding="utf-8") as f:
    json.dump(output_dict, f, indent=4)

print("Rewrite complete and saved.")
