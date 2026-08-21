import json
import os

with open(r'C:\projects\apache\school1\src\ptable\data\master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

lr_data = data.get('Lr', {}).copy()

lr_data['extract_html'] = "<p><b>Lawrencium</b> is a synthetic, highly radioactive chemical element with the symbol <b>Lr</b> and atomic number <b>103</b>. It is the final member of the actinide series in the periodic table. Because it is not found naturally on Earth, lawrencium must be created in high-energy physics laboratories by bombarding lighter elements with charged particles. Named after Ernest O. Lawrence, the inventor of the cyclotron particle accelerator, this elusive element exists only for fleeting moments. While we cannot observe it in our daily lives, its creation represents a remarkable triumph of modern nuclear physics.</p>"

lr_data['sections'] = {
    'History and Discovery': '<p>Lawrencium was first synthesized in 1961 at the Lawrence Berkeley National Laboratory in California by a team of scientists including Albert Ghiorso, Torbjørn Sikkeland, Almon E. Larsh, and Robert M. Latimer. The team created the element by bombarding a target containing californium isotopes with boron ions in a particle accelerator. Around the same time, the Joint Institute for Nuclear Research in Dubna, Russia, also conducted experiments to create element 103. In 1992, the International Union of Pure and Applied Chemistry (IUPAC) officially recognized both teams as co-discoverers. The element was proudly named after Ernest O. Lawrence, paying homage to his groundbreaking invention, the cyclotron, which paved the way for discovering many synthetic elements.</p>',
    
    'Scientific Characteristics': '<p>As a transuranic synthetic metal, lawrencium is incredibly difficult to study. Its extremely short half-life and the fact that scientists can only produce a few atoms at a time mean that its bulk physical properties are largely theoretical. Scientists predict that if enough lawrencium could be gathered, it would be a dense, silvery metal. In terms of chemistry, it behaves as a trivalent element, comfortably completing the actinide series with the electron configuration [Rn] 5f<sup>14</sup> 7s<sup>2</sup> 7p<sup>1</sup>.</p>',
    
    'Isotopes': '<p>All known isotopes of lawrencium are radioactive and unstable. Scientists have identified several isotopes, ranging in mass. The most stable one discovered so far is <b>Lawrencium-266</b>, which has a half-life of approximately 11 hours. However, much of the chemical research on this element relies on <b>Lawrencium-260</b> or <b>Lawrencium-262</b> (with a half-life of about 3.6 hours), as they can be produced more reliably in laboratories. The element decays quickly into lighter, more stable elements, leaving researchers with only a brief window to study its fascinating properties.</p>',
    
    'Modern Uses and Global Context': '<p>Due to its extreme radioactivity and fleeting existence, lawrencium currently has no commercial or practical applications outside of basic scientific research. Its primary use is in expanding our understanding of the universe, specifically the properties of superheavy elements and how relativity affects their atomic structures. Worldwide, including in the <b>Indian context</b>, lawrencium is an object of academic fascination. In India, it frequently features in higher education curricula and competitive examinations as a prime example of human ingenuity in the realm of nuclear science and transuranic chemistry, though no dedicated production facilities exist within the country.</p>'
}

os.makedirs(r'C:\projects\apache\school1\src\ptable\data\drafts', exist_ok=True)
with open(r'C:\projects\apache\school1\src\ptable\data\drafts\Lawrencium.json', 'w', encoding='utf-8') as f:
    json.dump(lr_data, f, indent=2)

print("Done!")
