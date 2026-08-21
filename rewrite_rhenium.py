import json
import os

input_path = r"src\ptable\data\master_elements.json"
output_path = r"src\ptable\data\drafts\Rhenium.json"

with open(input_path, 'r', encoding='utf-8') as f:
    master_data = json.load(f)

re_data = master_data['Re']

re_data['extract_html'] = """<p>Rhenium is a rare, silvery-white metal that holds the record for one of the highest melting points of all elements. Though predicted much earlier by the legendary chemist Dmitri Mendeleev, it was finally discovered in Germany in 1925—making it one of the very last naturally occurring elements to be found! Although India does not have its own domestic reserves of Rhenium and relies on imports, the Indian government recognizes it as a critical strategic mineral. This is because Rhenium is the secret ingredient in super-tough alloys used for jet engines and rockets. Institutions like India's Defence Metallurgical Research Laboratory (DMRL) rely on such advanced materials to power the nation's aerospace and defense manufacturing.</p>"""

re_data['sections']['History'] = """<p>Scientists knew Rhenium existed long before they actually found it. Dmitri Mendeleev, the creator of the periodic table, noticed a gap and predicted its existence, calling it "dvi-manganese." It wasn't until 1925 that German chemists Walter Noddack, Ida Tacke, and Otto Berg finally pinpointed the elusive element. They named it after the river Rhine in Europe (from its Latin name, Rhenus). It was the last stable, naturally occurring element to be discovered. While its historical roots are in Europe, today it plays a crucial role globally. In India, recognizing the historical significance of securing rare materials, the Ministry of Mines officially lists Rhenium as one of the 30 critical minerals essential for the country's future growth and self-reliance.</p>"""

re_data['sections']['Characteristics'] = """<p>Rhenium is an incredible powerhouse of a metal. It is silvery-gray, extremely dense, and famously resistant to heat. In fact, it has the third-highest melting point of any element (a blistering 3,186 &deg;C or 5,767 &deg;F!) and the highest boiling point of all. This means it can withstand conditions that would turn other metals to liquid. When mixed with other metals, like nickel, it creates "superalloys" that don't warp or melt under extreme pressure or heat. In modern Indian science and industry, materials with these kinds of characteristics are highly prized for developing indigenous technologies, especially for high-temperature turbine blades and space exploration hardware.</p>"""

re_data['sections']['Isotopes'] = """<p>In nature, Rhenium exists mostly as two forms, or "isotopes": Rhenium-185 and Rhenium-187. Rhenium-185 is stable, while Rhenium-187 is actually slightly radioactive, but it decays incredibly slowly&mdash;so slowly that its half-life is older than the universe! Because of this unique slow decay, scientists use Rhenium-187 to date ancient ores and meteorites, helping us understand the history of our solar system. In environmental science, researchers at institutions like India's Physical Research Laboratory study Rhenium isotopes and concentrations in major Indian river estuaries (like the Narmada and Hooghly). By tracking these isotopes, Indian scientists can learn about how the Earth's surface changes over time and monitor the health of marine environments.</p>"""

re_data['sections']['Occurrence'] = """<p>You won't find Rhenium just lying around; it's one of the rarest elements in the Earth's crust! It doesn't usually form its own minerals. Instead, it hides out in small amounts within copper and molybdenum ores. The world's biggest producers are countries like Chile, the United States, and Poland. In India, there are no commercially viable deposits of Rhenium, meaning the country depends entirely on importing this precious metal. Because it's so vital for high-tech industries, mapping global occurrence and ensuring a steady supply chain has become a major strategic priority for the Indian government's mineral security planning.</p>"""

re_data['sections']['Applications'] = """<p>Almost all the Rhenium mined today goes into making nickel-based superalloys. These alloys are the backbone of modern aviation, used to build the incredibly tough turbine blades inside jet engines and rocket thrusters. Without Rhenium, our planes wouldn't fly as efficiently or safely! It is also used as a catalyst in the petroleum industry to help produce high-octane, lead-free gasoline. In India, the applications of Rhenium are directly tied to the nation's ambitious defense and space goals. Scientists at Indian defense laboratories use Rhenium-enhanced alloys to build advanced aerospace components, ensuring that India's rockets and military aircraft can withstand the most extreme temperatures on Earth and beyond.</p>"""

os.makedirs(os.path.dirname(output_path), exist_ok=True)
with open(output_path, 'w', encoding='utf-8') as f:
    json.dump({"Re": re_data}, f, indent=2)

print(f"Successfully wrote {output_path}")
