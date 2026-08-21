import json
import os

input_file = 'c:/projects/apache/school1/src/ptable/data/drafts/Mc_original.json'
output_file = 'c:/projects/apache/school1/src/ptable/data/drafts/Moscovium.json'

with open(input_file, 'r', encoding='utf-8') as f:
    data = json.load(f)

# Update extract_html
data['extract_html'] = "<p><b>Moscovium</b> (symbol <b>Mc</b>, atomic number 115) is a highly radioactive and synthetic superheavy element. It was first created in a laboratory in 2003 by a team of Russian and American scientists. Due to its extreme instability, Moscovium exists for only a fraction of a second before decaying into other elements. It belongs to the pnictogen group (Group 15) on the periodic table, placing it below elements like nitrogen, phosphorus, and bismuth. While you can't hold a piece of Moscovium, scientists predict it would be a solid, silvery metal if enough of it could ever be produced.</p>"

# Update sections
data['sections'] = {
    "History": "<p>Moscovium was first synthesized in 2003 through a collaboration between scientists at the Joint Institute for Nuclear Research (JINR) in Dubna, Russia, and the Lawrence Livermore National Laboratory in California, USA. The team bombarded targets of americium-243 with calcium-48 ions in a powerful particle accelerator to create a few fleeting atoms of the element.</p><p>Originally known by the placeholder name 'ununpentium' (meaning 'one-one-five'), it was officially named Moscovium in 2016 by the International Union of Pure and Applied Chemistry (IUPAC). The name honors the Moscow Oblast region in Russia, where the JINR facility is located.</p>",
    "Scientific Facts": "<p>Moscovium is a transuranium, p-block element with no stable isotopes. Its most stable known isotope, moscovium-290, has a half-life of just 0.65 seconds. Because it decays so rapidly, primarily into nihonium (element 113), studying its chemical properties is incredibly challenging.</p><p>Scientists rely on complex theoretical models and brief experimental observations to predict its behavior. It is expected to share some characteristics with its lighter group 15 counterparts but may also exhibit unique properties due to relativistic effects caused by its massive, highly charged nucleus.</p>",
    "Modern Uses": "<p>Currently, Moscovium has no practical commercial, industrial, or biological uses. Because it is extraordinarily difficult and expensive to produce—yielding only a few atoms at a time that vanish almost instantly—its applications are strictly limited to fundamental scientific research.</p><p>Scientists study Moscovium to test nuclear physics theories, understand the limits of chemical bonding, and explore the 'island of stability'—a theoretical region of superheavy elements that might have much longer half-lives. Additionally, while the element has been famously associated with science fiction and UFO conspiracy theories, these claims have absolutely no basis in science.</p>",
    "Indian Context": "<p>While the specialized particle accelerators required to synthesize superheavy elements like Moscovium are not currently located in India, the element holds an important place in Indian academia. Indian educational institutions, from schools to advanced research universities, include Moscovium in their modern chemistry curricula.</p><p>Numerous platforms like Aakash Institute and popular science educators produce rich content in both English and Hindi, helping students across India understand the complexities of the p-block, the synthesis of superheavy elements, and the evolving nature of the periodic table.</p>"
}

# Remove any existing Wikipedia markup string remnants if they exist in other places
# (the prompt only requested to rewrite extract_html and sections without Wikipedia markup)
# We will just write the data
with open(output_file, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=4, ensure_ascii=False)

print('Successfully saved Moscovium.json')
