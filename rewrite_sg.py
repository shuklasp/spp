import json
import os

input_path = 'src/ptable/data/master_elements.json'
output_path = 'src/ptable/data/drafts/Seaborgium.json'

with open(input_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

sg_data = data['Sg']

sg_data['extract_html'] = "<p><b>Seaborgium</b> (symbol <b>Sg</b>, atomic number 106) is a synthetic, highly radioactive element in the periodic table. It does not occur naturally and can only be created in laboratories by smashing lighter elements into heavy ones. Named after the legendary American nuclear chemist Glenn T. Seaborg, it holds the distinction of being one of the very few elements named after a living person at the time of its official naming. Because it decays so quickly—its most stable isotope has a half-life of just a few minutes—only tiny amounts of seaborgium have ever been made. It sits in Group 6 of the periodic table, right below tungsten, and scientists predict it behaves as a solid metal at room temperature.</p>"

sg_data['sections'] = {
    "History": "<p>The story of seaborgium's discovery was full of international competition and controversy. It was first synthesized in 1974, almost simultaneously, by two rival teams: one led by Albert Ghiorso at the Lawrence Berkeley National Laboratory in the US, and another at the Joint Institute for Nuclear Research in Dubna, Russia. The American team made it by bombarding a californium-249 target with oxygen-18 ions.</p><p>For over two decades, the discovery sparked a fierce \"Transfermium Wars\" naming dispute between the scientific institutions. It wasn't until 1997 that the International Union of Pure and Applied Chemistry (IUPAC) officially approved the name \"seaborgium,\" honoring Glenn T. Seaborg's monumental contributions to discovering numerous transuranium elements.</p>",
    "Isotopes and Modern Uses": "<p>Seaborgium has no stable isotopes; all known forms are highly radioactive. The most stable known isotope is seaborgium-271, with a half-life of roughly 2.4 minutes, though it decays into lighter elements almost immediately. Because of this extreme instability, seaborgium has no commercial, industrial, or everyday uses. Instead, it is highly prized for fundamental scientific research. Studying seaborgium helps physicists verify the trends in the periodic table, understand relativistic effects on superheavy elements, and search for the theoretical \"Island of Stability\" where certain heavy isotopes might last longer.</p><p>In recent years, Indian scientists have also made significant contributions to the study of superheavy elements. For instance, researchers such as Prof. M. Maiti from IIT Roorkee collaborated with international teams at the GSI Helmholtz Centre for Heavy Ion Research in Germany to discover a new isotope, seaborgium-257. This breakthrough research adds to our global understanding of the limits of nuclear stability and pushes the boundaries of modern physics.</p>"
}

with open(output_path, 'w', encoding='utf-8') as f:
    json.dump(sg_data, f, indent=4)

print("Done writing to", output_path)
