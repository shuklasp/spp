import json
import os

input_path = r'c:\projects\apache\school1\src\ptable\data\master_elements.json'
output_dir = r'c:\projects\apache\school1\src\ptable\data\drafts'
output_path = os.path.join(output_dir, 'Astatine.json')

with open(input_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

at = data['At']

# Rewrite extract_html
at['extract_html'] = """<p><b>Astatine</b> (symbol <b>At</b>, atomic number 85) is the rarest naturally occurring element on Earth. It is a highly radioactive and incredibly elusive element. In fact, scientists estimate that there is less than a single gram of astatine present in the entire Earth's crust at any given moment! Because it decays so quickly, a visible, solid piece of astatine has never been seen—if you were to somehow gather enough of it, the extreme heat from its own radiation would instantly vaporize it.</p>
<p>Astatine sits in the halogen group on the periodic table (alongside fluorine, chlorine, bromine, and iodine), but it also straddles the line between metals and nonmetals. Today, modern scientific facilities—such as India's Bhabha Atomic Research Centre (BARC) and the Variable Energy Cyclotron Centre (VECC)—are extensively researching astatine for breakthrough cancer treatments.</p>"""

# Rewrite sections
at['sections'] = {
    "Characteristics": """<p>Even today, much of what we know about astatine is based on educated guesses. Its extreme radioactivity and short lifespan make it nearly impossible to study in large amounts. However, because of its position on the periodic table, scientists believe it shares traits with its halogen cousins like iodine, while also displaying some characteristics of metals.</p>
<p>If you could hold a chunk of it (which you can't!), it would likely look dark or metallic, and might even conduct electricity like a semiconductor. Chemically, it often acts similarly to iodine but occasionally behaves more like a metal, such as silver.</p>""",

    "History": """<p>The story of astatine is filled with mystery and false starts. When Dmitri Mendeleev created his famous periodic table in 1869, he left a blank space below iodine, knowing an undiscovered element belonged there. He called this missing element "eka-iodine" (using the Sanskrit word "eka," meaning "one").</p>
<p>Because astatine is practically non-existent in nature, early attempts to find it led to many mistaken claims. One notable claim came in 1937 from Indian chemist Rajendralal De, working in Dacca (then British India). De believed he had found the missing element in monazite sand and named it "dakin," after his city. Unfortunately, his claim couldn't be proven; handling real astatine in the amounts he described would have been dangerously radioactive, and the results could not be replicated. Other scientists around the world also claimed to have found it, proposing names like "alabamine," "dor," and "helvetium."</p>
<p>The real breakthrough finally came in 1940. Scientists Dale R. Corson, Kenneth Ross MacKenzie, and Emilio Segrè at the University of California, Berkeley, realized that astatine had to be created artificially. By using a machine called a cyclotron to bombard bismuth with alpha particles, they successfully created the new element. They named it "astatine," derived from the Ancient Greek word <i>ástatos</i>, which appropriately means "unstable."</p>""",

    "Isotopes": """<p>Astatine is the definition of unstable. All of its variations, known as isotopes, are extremely short-lived. The most stable one, astatine-210, survives for a mere 8.1 hours before breaking down into other elements like bismuth, polonium, or radon.</p>
<p>While it's too unstable for everyday use, this short lifespan makes one specific isotope, <b>astatine-211</b>, incredibly valuable to modern medicine. Medical researchers, particularly at major Indian institutions like the Bhabha Atomic Research Centre (BARC) and the Variable Energy Cyclotron Centre (VECC), are actively investigating astatine-211 for "Targeted Alpha Therapy" (TAT). This is a cutting-edge cancer treatment where astatine is attached to molecules that hunt down cancer cells. Because astatine releases powerful alpha particles over a short distance, it can destroy tiny tumors with incredible precision without causing widespread damage to surrounding healthy tissue. It acts as a "Goldilocks" isotope for medicine: potent enough to fight cancer, yet short-lived enough to safely disappear from the patient's body.</p>"""
}

os.makedirs(output_dir, exist_ok=True)
with open(output_path, 'w', encoding='utf-8') as f:
    json.dump({"At": at}, f, indent=4, ensure_ascii=False)

print(f"Successfully wrote Astatine draft to {output_path}")
