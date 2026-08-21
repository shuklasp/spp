import json
from bs4 import BeautifulSoup

# Load original data
with open('hs_temp.json', 'r', encoding='utf-8') as f:
    d = json.load(f)

# The new HTML content
new_extract_html = """
<p><b>Hassium</b> is a synthetic chemical element with the symbol <b>Hs</b> and atomic number 108. It is a highly radioactive superheavy element, meaning it does not exist in nature and can only be created in a laboratory by smashing heavy atomic nuclei together with lighter ones. Hassium is extremely unstable; its most stable known isotopes only last for about ten seconds before decaying. However, one specific isotope, hassium-270, is uniquely stable because it possesses "magic numbers" of protons and neutrons, which help hold its deformed nucleus together against spontaneous fission.</p>
<p>In the periodic table, hassium is located in period 7 and group 8, making it a transactinide element and part of the transition metals. Scientists have conducted chemistry experiments showing that hassium behaves very much like its lighter cousin, osmium. For example, it reacts readily with oxygen to form a highly volatile compound called hassium tetroxide. While much of its chemistry remains a mystery due to how hard it is to produce, it aligns well with other group 8 elements.</p>
<p>The creation of hassium was made possible by an innovative technique known as "cold fusion" (not to be confused with the hypothetical energy source). In this method, the fused nuclei are closer in mass, which creates less excitation energy and prevents the newly formed superheavy nucleus from immediately blowing apart. This technique was first tested at the Joint Institute for Nuclear Research (JINR) in Dubna, Soviet Union, in 1974. After several attempts, JINR claimed to have produced element 108 in 1984. Later that same year, researchers at the Gesellschaft für Schwerionenforschung (GSI) in Darmstadt, West Germany, also successfully synthesized it. A global scientific committee later credited the German team with the conclusive discovery. In 1992, the GSI team proposed naming the element "hassium" after the Latin word <i>Hassia</i>, which means Hesse, the German state where their facility is located. The name was officially accepted in 1997.</p>
<p>Although hassium is not physically produced in India—since doing so requires highly specialized heavy-ion accelerators found in a few international facilities—India has a strong connection to the science of superheavy elements. Indian researchers at premier institutions like the Bhabha Atomic Research Centre (BARC) in Mumbai, the Tata Institute of Fundamental Research (TIFR), and the Variable Energy Cyclotron Centre (VECC) in Kolkata have made significant theoretical contributions. Indian physicists actively run complex computations to understand the nuclear structure, stability, and decay modes of superheavy elements like hassium. Furthermore, Indian scientific institutions collaborate heavily in global efforts, such as the Facility for Antiproton and Ion Research (FAIR) in Germany, continuing a rich modern tradition of exploring the very limits of the periodic table.</p>
"""

new_isotopes_html = """
<p>Hassium has no stable or naturally occurring isotopes. Every atom of hassium has to be artificially created in a laboratory, either by directly fusing two lighter atoms together or by watching heavier elements decay into hassium. Because it is so difficult to make, only a few hundred atoms of hassium have ever been produced since its discovery.</p>
<p>Scientists have identified thirteen different isotopes of hassium, ranging in mass from 263 to 277. Six of these are known to have "metastable states," which are slightly different energy configurations of the same nucleus. Almost all hassium isotopes decay through a process called alpha decay, where they spit out an alpha particle (two protons and two neutrons) to become a lighter element. The only known exception is hassium-277, which breaks apart entirely through spontaneous fission. Generally, the lighter isotopes of hassium are created through direct fusion, while the heavier ones are observed as the "daughters" of even heavier elements breaking down.</p>
<p>The stability of superheavy elements like hassium is a fascinating puzzle. Inside an atom, protons and neutrons are organized into "shells." When an isotope has a "magic number" of protons or neutrons that perfectly fills a shell, it becomes significantly more stable. The highest known magic numbers for spherical nuclei are 82 for protons and 126 for neutrons. For a long time, scientists using the "liquid drop model" believed that superheavy elements beyond 103 protons couldn't exist because they would fission apart instantly. However, the later "nuclear shell model" predicted an "island of stability"—a theoretical region where superheavy elements would be much more stable and live longer due to these magic numbers.</p>
<p>Interestingly, nuclei in this region aren't perfectly spherical; they are deformed, like a football. In 1991, physicists Zygmunt Patyk and Adam Sobiczewski calculated that for these deformed nuclei, 108 is a magic number for protons and 162 is a magic number for neutrons. This means hassium-270 (which has 108 protons and 162 neutrons) is a "doubly magic" deformed nucleus, giving it a surprisingly long lifespan compared to its neighbors. Later, in 1997, physicist Robert Smolańczuk predicted that hassium-292 might be the most stable superheavy nucleus of all, thanks to a predicted spherical magic number of 184 neutrons.</p>
<p>Theoretical physicists in India have been deeply involved in unraveling these nuclear mysteries. Scientists at institutions like the Variable Energy Cyclotron Centre (VECC) and the Bhabha Atomic Research Centre (BARC) use advanced computational models to study the "island of stability" and the precise shell effects that keep elements like hassium together. Their research helps predict the fission barriers—the energy required for a nucleus to split apart—of undiscovered hassium isotopes. This modern Indian scientific contribution is vital for mapping the uncharted territories of the periodic table, helping global researchers know exactly where to look for new, stable superheavy isotopes.</p>
"""

def replace_paragraphs(original_html, new_html):
    soup = BeautifulSoup(original_html, 'html.parser')
    
    paras = soup.find_all('p', recursive=True)
    for p in paras:
        if p.get_text(strip=True):
            p.decompose()

    root = soup.find('div', class_='mw-parser-output')
    if not root:
        root = soup

    new_soup = BeautifulSoup(new_html, 'html.parser')
    for elem in list(new_soup.children):
        root.append(elem)

    return str(soup)

d['extract_html'] = replace_paragraphs(d['extract_html'], new_extract_html)
if 'Isotopes' in d.get('sections', {}):
    d['sections']['Isotopes'] = replace_paragraphs(d['sections']['Isotopes'], new_isotopes_html)

import os
os.makedirs('src/ptable/data/drafts', exist_ok=True)
with open('src/ptable/data/drafts/Hassium.json', 'w', encoding='utf-8') as f:
    json.dump(d, f, indent=2)

print("Saved successfully!")
