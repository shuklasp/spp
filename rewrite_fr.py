import json
import os

# Ensure the drafts directory exists
os.makedirs('src/ptable/data/drafts', exist_ok=True)

# Read the original data
with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    master_data = json.load(f)

fr_data = master_data.get('Fr')

if fr_data:
    # Rewrite extract_html
    fr_data['extract_html'] = "<p><strong>Francium</strong> is an incredibly rare, highly radioactive alkali metal with the symbol <strong>Fr</strong> and atomic number <strong>87</strong>. It holds the fascinating distinction of being the second-rarest naturally occurring element in the Earth's crust, right after astatine. In fact, scientists estimate that there are only about 20 to 30 grams of francium present in the entire Earth's crust at any given time! Due to its extreme radioactivity and incredibly short half-life—its most stable isotope lasts for just 22 minutes—francium is exceedingly difficult to study. If you could somehow gather enough of it to see, it would likely appear as a shiny, silvery-white metal. However, as an alkali metal, it is highly reactive and would vaporize itself almost instantly while reacting explosively with moisture in the air.</p>"
    
    # Rewrite sections
    fr_data['sections'] = {
        'Characteristics': "<p>Francium is the heaviest known alkali metal. Like other members of its group (such as sodium and potassium), it has a single valence electron, making it extremely reactive. While it has never been collected in a large enough quantity to be seen by the naked eye or weighed on a scale, scientists predict it would be a silvery-white solid at room temperature. Its melting point is estimated to be quite low, around 27 °C (81 °F). Chemically, it reacts violently with water and air, much like its lighter cousins, though observing this in a lab is practically impossible because francium decays into other elements far too quickly. Its extreme instability is due to the sheer size of its atomic nucleus, which simply cannot hold itself together.</p>",
        
        'Isotopes': "<p>Francium is notoriously unstable. It has 34 known isotopes, ranging in atomic mass from 199 to 232, and every single one of them is highly radioactive. The most stable isotope is <strong>Francium-223</strong>, but even this form has a half-life of just 21.8 minutes. This means that if you had a lump of Francium-223, half of it would decay into radium or astatine in less than 22 minutes! Most other isotopes of francium have half-lives measured in mere seconds or fractions of a second. This rapid decay makes isolating the element practically impossible and presents a major challenge for scientists attempting to study its atomic properties.</p>",
        
        'Applications': "<p>Because francium is so incredibly rare and vanishes so quickly due to radioactive decay, it has absolutely no commercial, industrial, or medical applications. You will not find it in any everyday products or technologies. However, it holds significant value in the realm of specialized scientific research. Physicists and chemists synthesize tiny amounts of francium in laboratories using particle accelerators to study atomic structure, quantum mechanics, and the weak nuclear force. For instance, advanced spectroscopy experiments on francium help test theories of quantum electrodynamics (QED) and atomic parity violation. In India, like in the rest of the world, francium is mostly a topic of deep academic interest, taught in chemistry curricula (such as in competitive exams like JEE and NEET) as a prime theoretical example of periodic trends and intense radioactivity.</p>",
        
        'History': "<p>The discovery of francium is a story of persistence and national pride. For decades, chemists knew there was a missing alkali metal in the periodic table, right below cesium. Russian chemist Dmitri Mendeleev, the creator of the periodic table, had predicted its existence and temporarily named it 'eka-caesium'. Many scientists claimed to have discovered it in the early 20th century, but all were proven wrong. It wasn't until <strong>1939</strong> that French physicist <strong>Marguerite Perey</strong> at the Curie Institute in Paris finally discovered the elusive element. She found it while purifying a sample of actinium-227, which decays into francium. Perey proudly named the new element 'francium' in honor of her home country, France. Francium also holds a unique place in history: it was the very last chemical element to be discovered first in nature, rather than being artificially synthesized in a lab.</p>",
        
        'Occurrence': "<p>Finding francium in nature is like searching for a needle in a planetary haystack. It occurs naturally only in trace amounts in uranium and thorium ores, specifically as a brief intermediate step in the radioactive decay of actinium-227. At any given moment, the entire crust of the Earth contains no more than 20 to 30 grams (about an ounce) of francium! Because it decays so rapidly, any naturally occurring francium is constantly disappearing, only to be replaced by newly formed francium from actinium decay. To study it today, scientists don't bother mining for it; instead, they artificially create it in laboratories by bombarding radium or gold atoms with other particles in high-energy accelerators.</p>"
    }
    
    with open('src/ptable/data/drafts/Francium.json', 'w', encoding='utf-8') as f:
        json.dump(fr_data, f, indent=2)
        
    print("Successfully wrote rewritten Francium data to src/ptable/data/drafts/Francium.json")
else:
    print("Could not find Francium ('Fr') in master_elements.json")
