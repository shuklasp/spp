import json
import os

input_path = 'c:/projects/apache/school1/scratch_no.json'
output_dir = 'c:/projects/apache/school1/src/ptable/data/drafts'
output_path = os.path.join(output_dir, 'Nobelium.json')

with open(input_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

data['extract_html'] = "Nobelium (symbol No, atomic number 102) is a synthetic, highly radioactive element in the actinide series. It does not exist naturally on Earth and is created artificially in particle accelerators by bombarding lighter elements like curium or californium with heavy ions. Discovered in the 1960s after a period of intense international competition, nobelium was named after Alfred Nobel. Due to its extreme instability—its longest-lived isotope has a half-life of only about 58 minutes—nobelium is incredibly difficult to study and has no commercial applications, making it exclusively a subject of high-level scientific research."

data['sections'] = {
    'Characteristics': "### Physical and Chemical Properties\nNobelium is a heavy, radioactive metal that belongs to the actinide series on the periodic table. While its short half-life makes it challenging to study in large quantities, scientists predict it to be a solid at room temperature with a stable +2 oxidation state. Because it decays so quickly, its chemical behavior is mostly explored \"one atom at a time.\" Modern experimental techniques allow researchers to better understand how it fits into the heavy-element region of the periodic table.\n\n### Discovery and History\nThe discovery of nobelium was highly competitive. In 1957, scientists at the Nobel Institute of Physics in Sweden claimed to have created element 102 and proposed the name 'nobelium'. However, their results couldn't be replicated. Over the next decade, teams at the University of California, Berkeley, and the Joint Institute for Nuclear Research in Dubna, Russia, engaged in a priority dispute. Ultimately, the international scientific community credited the Soviet team for the definitive synthesis of the element, though the name nobelium was kept to honor Alfred Nobel.\n\n### Modern Uses and Global Context\nNobelium has no commercial, industrial, or medical applications. Its only use is in fundamental scientific research, particularly in nuclear physics and atomic theory, where it helps scientists test the limits of atomic stability. While nobelium is produced in only a few highly specialized accelerator facilities worldwide (such as in the U.S., Germany, Japan, and Russia), the theoretical physics and chemistry behind such heavy elements are studied globally. In India, premier research institutions like the Indian Institute of Science (IISc) contribute to the theoretical models of nuclear physics and heavy-element chemistry. Although India is not directly involved in producing nobelium, the element shares its namesake's legacy with several Indian Nobel laureates who have made landmark contributions to global science.",
    
    'Isotopes': "Fourteen isotopes of nobelium have been identified so far, with mass numbers ranging from 248 to 260, as well as 262. Every single one of them is highly radioactive. Additionally, scientists have discovered seventeen nuclear isomers. \n\nThe longest-lived isotope is **nobelium-259**, which has a half-life of just 58 minutes. However, in chemical experiments, researchers often use the shorter-lived **nobelium-255** (half-life of 3.52 minutes) because it can be produced more reliably and in larger quantities by bombarding californium-249 with carbon-12 ions. \n\nMost nobelium isotopes decay extremely rapidly. After nobelium-259 and nobelium-255, the half-lives drop significantly: nobelium-253 lasts for 1.57 minutes, and the rest decay in a matter of seconds or even fractions of a second. The shortest-lived known isotope, nobelium-248, vanishes in less than 2 microseconds. As researchers push to create heavier nobelium isotopes, they find that the intense mutual repulsion of protons makes the nucleus highly prone to spontaneous fission, setting a natural limit on the stability of these super-heavy actinide elements. Still, scientists predict that an undiscovered isotope, nobelium-261, might have an even longer half-life of up to 3 hours, offering an exciting frontier for future research."
}

os.makedirs(output_dir, exist_ok=True)
with open(output_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2)

print('Success')
