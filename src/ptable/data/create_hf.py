import json
import os

draft_path = "c:/projects/apache/school1/src/ptable/data/drafts/Hafnium.json"
os.makedirs(os.path.dirname(draft_path), exist_ok=True)

extract_html = """
<p><strong>Hafnium</strong> (symbol <strong>Hf</strong>, atomic number 72) is a shiny, silvery-gray metal that looks and acts a lot like its periodic table neighbor, zirconium. Because they are so similar, hafnium is almost always found hiding inside zirconium minerals, and separating the two is quite a challenge!</p>
<p>Despite being relatively rare, hafnium plays a massive role in high-tech industries. It is incredibly good at absorbing neutrons and can withstand extreme heat, making it a star player in nuclear reactors and aerospace engineering. In India, hafnium is officially recognized as a critical strategic mineral. It is vital for the nation's advanced space missions and nuclear energy programs, with major research driven by institutions like the Bhabha Atomic Research Centre (BARC) and the Indian Space Research Organisation (ISRO).</p>
"""

sections = [
    {
        "title": "Characteristics",
        "content": """
<p>Hafnium is a tough, heavy transition metal that resists corrosion and handles extreme temperatures with ease. One of its most famous features is its ability to swallow up neutrons, a property that makes it incredibly useful in nuclear science. Curiously, while a solid chunk of hafnium is perfectly safe, hafnium in powder form is pyrophoric—meaning it can spontaneously catch fire in the air!</p>
<p>Chemically, it is like a twin to zirconium. The two elements are so identical in their chemical behaviors that early chemists struggled for years to tell them apart. It wasn't until scientists started using X-rays to look at their atomic structures that they could finally separate the two.</p>
"""
    },
    {
        "title": "Isotopes",
        "content": """
<p>Hafnium has dozens of different versions, known as isotopes, which vary slightly in their weight. Five of these are stable and make up the hafnium we find in nature.</p>
<p>There is also one very famous (and controversial) radioactive form called a "nuclear isomer" (specifically, Hafnium-178m2). For years, scientists debated whether this highly energetic isomer could be triggered to release a massive burst of gamma rays all at once, potentially creating a new kind of weapon. Ultimately, creating this isomer proved far too difficult and expensive, and the idea of a hafnium weapon remains the stuff of science fiction.</p>
"""
    },
    {
        "title": "Occurrence",
        "content": """
<p>You won't find pure hafnium sitting around in nature. It prefers to blend in, naturally occurring inside zirconium-rich minerals like zircon. Extracting it requires processing massive amounts of heavy mineral sands.</p>
<p>In India, these heavy mineral sands are found abundantly along the coastal stretches of states like <strong>Kerala, Odisha, and Tamil Nadu</strong>. Because hafnium is found within these domestic zircon deposits, India's ability to mine and process it locally is a tremendous advantage for its strategic industries.</p>
"""
    },
    {
        "title": "History",
        "content": """
<p>When the famous chemist Dmitri Mendeleev created his early periodic table in 1869, he left a blank space for element 72, predicting that a heavier cousin of titanium and zirconium must exist. For decades, scientists hunted for it. It wasn't until 1923 that Dirk Coster and George de Hevesy finally found it hiding inside a piece of Norwegian zircon.</p>
<p>They made the discovery in Copenhagen, Denmark. To honor the city, they named the new element "Hafnium," derived from <em>Hafnia</em>, the Latin name for Copenhagen.</p>
<p>In modern times, India has made significant historical strides in hafnium technology to ensure national self-reliance. Historically dependent on complex foreign supply chains for this critical metal, India established its first indigenous hafnium metal sponge pilot plant at <strong>C-MET (Centre for Materials for Electronics Technology)</strong> in Hyderabad. This milestone, achieved in collaboration with ISRO's Vikram Sarabhai Space Centre (VSSC), marked a major step forward in India's ability to produce space-grade hafnium domestically.</p>
"""
    },
    {
        "title": "Applications",
        "content": """
<p>Because hafnium is a master at absorbing neutrons, its most crucial job is inside nuclear reactors. It is used to make control rods that regulate the nuclear fission process—acting like the brakes in a nuclear power plant. In India, the <strong>Nuclear Fuel Complex (NFC)</strong> and the <strong>Bhabha Atomic Research Centre (BARC)</strong> have pioneered the complex chemical processes needed to completely separate hafnium from zirconium, ensuring both elements can be used safely in the country's nuclear fuel cycle.</p>
<p>Beyond nuclear energy, hafnium's ability to survive blazing temperatures makes it perfect for aerospace alloys, including rocket engines and spacecraft thrusters. It's also found in everyday technology: if you have a modern smartphone or computer, there's a good chance hafnium oxide is inside its tiny microchips, helping the transistors run faster and more efficiently.</p>
"""
    }
]

output_data = {
    "extract_html": extract_html.strip(),
    "sections": sections
}

with open(draft_path, "w", encoding="utf-8") as f:
    json.dump(output_data, f, indent=2)

print("Saved drafts/Hafnium.json successfully!")
