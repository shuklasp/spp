import json
import os

input_file = r'c:\projects\apache\school1\ac_data.json'
with open(input_file, 'r', encoding='utf-8') as f:
    data = json.load(f)

data['extract_html'] = '<p><b>Actinium</b> (symbol <b>Ac</b>, atomic number 89) is a highly radioactive and incredibly rare metal that gives its name to the actinide series of the periodic table. Born from the decay of heavier elements like uranium and thorium, actinium is so intensely radioactive that it literally glows with an eerie pale blue light in the dark! Its name is derived from the ancient Greek word <i>aktis</i>, meaning "beam" or "ray," which perfectly captures its radiant nature. It is about 150 times more radioactive than radium. Because it is so scarce in nature, scientists today produce it synthetically in nuclear reactors. While it has no everyday consumer uses, actinium is stepping into the spotlight as a powerful tool in modern medicine, offering promising new ways to target and destroy cancer cells.</p>'

data['sections'] = {
    'History': '<p>The story of <b>Actinium</b> begins in 1899 with French chemist André-Louis Debierne. While working with pitchblende (a uranium-rich ore) left over after Marie and Pierre Curie had extracted radium, Debierne noticed a new, mysterious radioactive substance. Independent of Debierne, German chemist Friedrich Oskar Giesel discovered the same element in 1902. Giesel initially named it "emanium" because it emanated such strong radioactivity, but Debierne\'s earlier discovery was eventually recognized, and the name "actinium" became official. Historically, actinium was incredibly difficult to study because its chemical behavior is almost identical to the rare-earth element lanthanum, making the two notoriously hard to separate. It wasn\'t until 1955 that completely pure actinium metal was finally isolated.</p>',
    'Isotopes': '<p>Actinium has absolutely no stable isotopes; every natural or synthetic form of it is radioactive. The most common and longest-lived isotope found in nature is <b>Actinium-227</b>, which has a half-life of about 21.77 years. It decays over time, primarily emitting beta particles as it transforms into lighter elements. However, the true star of modern research is <b>Actinium-225</b>. With a much shorter half-life of just 10 days, this isotope decays by rapidly firing off powerful alpha particles. In the scientific world, creating and studying these isotopes requires specialized nuclear reactors and particle accelerators, as natural actinium is far too rare to mine directly.</p>',
    'Applications': '<p>Because of its extreme rarity, high cost, and dangerous radioactivity, <b>actinium</b> has no commercial or industrial uses. However, it is a superstar in the field of <b>Targeted Alpha Therapy (TAT)</b> for cancer treatment. Scientists attach the isotope Actinium-225 to special targeting molecules (like antibodies) that seek out specific cancer cells. Once attached to the tumor, the actinium acts like a microscopic smart-bomb, blasting the cancer cells with high-energy alpha particles while leaving surrounding healthy tissue largely unharmed! Beyond medicine, Actinium-227 is sometimes combined with beryllium to create reliable neutron sources for scientific experiments and oil well logging. In India, institutions under the Department of Atomic Energy (DAE) are actively researching such cutting-edge radiopharmaceuticals, aligning with global efforts to use advanced nuclear technology for life-saving medical treatments and deep radiochemical research.</p>'
}

output_dir = r'c:\projects\apache\school1\src\ptable\data\drafts'
os.makedirs(output_dir, exist_ok=True)
output_file = os.path.join(output_dir, 'Actinium.json')

with open(output_file, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2)

print('Saved successfully to', output_file)
