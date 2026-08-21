import json
import os

input_file = r"c:\projects\apache\school1\src\ptable\data\master_elements.json"
output_dir = r"c:\projects\apache\school1\src\ptable\data\drafts"
output_file = os.path.join(output_dir, "Moscovium.json")

with open(input_file, "r", encoding="utf-8") as f:
    data = json.load(f)

mc_data = data["Mc"]

mc_data["extract_html"] = """
<p><strong>Moscovium</strong> (symbol <strong>Mc</strong>) is a fascinating, human-made element with the atomic number 115. It was first synthesized in 2003 through a collaboration between Russian and American scientists at the Joint Institute for Nuclear Research (JINR) in Dubna, Russia. In 2016, it was officially named after the Moscow region, paying tribute to the location where it was discovered.</p>

<p>While no Indian scientists were directly involved in the discovery of Moscovium, India has a deep and vibrant legacy in nuclear physics and chemistry. Pioneers like Dr. Homi J. Bhabha, the architect of India's nuclear program, and Nobel laureate Sir C.V. Raman established a strong foundation for advanced scientific research. Today, Indian institutions like IIT Roorkee actively contribute to international efforts in discovering and studying heavy elements, reflecting India's ongoing commitment to exploring the building blocks of the universe.</p>

<p>Moscovium is highly radioactive and incredibly unstable. Its most stable form, known as moscovium-290, lasts for only about 0.65 seconds before it breaks apart into other elements. Because it disappears almost instantly, scientists have only ever observed around a hundred atoms of it! Sitting in group 15 of the periodic table, right below bismuth, scientists predict that Moscovium behaves somewhat like a heavy metal. However, its massive size means it might have unique and surprising chemical traits compared to lighter elements in its family. Currently, because it is so difficult to create and maintain, Moscovium has no practical uses outside of fundamental scientific research.</p>
"""

mc_data["sections"]["History"] = """
<figure class="mw-default-size mw-halign-right" typeof="mw:File/Thumb">
  <img src="/school1/ptable/theme-assets/default/images/inline/0dbf0723f9fc4250e9148c08f22a0739.jpg" decoding="async" width="350" height="232" class="mw-file-element mw-file-upright" />
  <figcaption>The Red Square in Moscow. Moscovium was named to honor the Moscow region, the "ancient Russian land" where the Joint Institute for Nuclear Research is located.</figcaption>
</figure>
<p>The journey to discovering Moscovium began in the early 2000s, when a joint team of Russian and American scientists at the Joint Institute for Nuclear Research (JINR) in Dubna, Russia, first synthesized it in 2003. After years of experiments and verification, it was officially recognized as a new element in December 2015 by international scientific bodies (IUPAC and IUPAP). On November 28, 2016, the element was officially named <strong>Moscovium</strong> to honor the Moscow region, acknowledging the monumental scientific efforts of the researchers there. This discovery stands as a testament to global scientific cooperation, inspiring nuclear research programs worldwide, including India's growing initiatives in the study of superheavy elements.</p>
"""

os.makedirs(output_dir, exist_ok=True)

with open(output_file, "w", encoding="utf-8") as f:
    json.dict_to_save = {"Mc": mc_data}
    json.dump(json.dict_to_save, f, indent=4, ensure_ascii=False)

print("Saved to", output_file)
