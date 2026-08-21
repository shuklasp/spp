import json
import os

EXTRACT_HTML = """<p><b><a href="/school1/ptable/element/I" class="wiki-link" title="Iodine (I)">Iodine</a></b> is a fascinating chemical element, known for being a lustrous, purple-black solid that turns into a beautiful violet gas when heated. Sitting at number 53 on the periodic table, it's the heaviest stable member of the halogen family. While it was officially discovered in 1811 by a French chemist, its impact on human health has been observed for thousands of years. In ancient India, medical texts like the <i>Charaka Samhita</i> and <i>Sushruta Samhita</i> documented treatments for neck swellings known as <i>Galaganda</i> (goiter) using seaweed and burnt sponge—materials we now know are packed with iodine! Today, iodine is famous for its role in keeping our thyroid glands healthy. It's so vital that a lack of it is the world's leading preventable cause of intellectual disabilities, which is why you often see "iodized salt" in your grocery store.</p>"""

HISTORY_HTML = """<p>The official story of iodine's discovery begins in France in 1811. During the Napoleonic Wars, French chemist Bernard Courtois was working with seaweed ashes to make saltpetre for gunpowder. When he accidentally added too much sulfuric acid to his mixture, a stunning cloud of violet vapor arose and crystallized into dark crystals on cold surfaces. He had discovered a new element! The name "iodine" comes from the ancient Greek word <i>iodēs</i>, which means "violet," perfectly describing the color of its gas.</p><p>However, humanity's relationship with iodine stretches back much further. Long before it was identified as an element, ancient civilizations recognized the health problems caused by its absence. In ancient India, the medical condition we now call goiter (an enlarged thyroid gland) was known as <i>Galaganda</i>. Ancient Ayurvedic texts like the <i>Charaka Samhita</i> and <i>Sushruta Samhita</i> recommended treating it with substances like seaweed, which are naturally rich in iodine. Fast forward to the modern era, India played a pivotal role in the global fight against iodine deficiency. In the 1950s, Indian nutrition scientist <b>Dr. Vulimiri Ramalingaswami</b> conducted the famous "Kangra Valley Experiment" in Himachal Pradesh. His groundbreaking research proved that goiter was caused by iodine deficiency and could be prevented by simply adding it to salt. Thanks to this, India became the first South Asian country to implement Universal Salt Iodization, with public health leaders like <b>Dr. Chandrakant S. Pandav</b> (often called the "Iodine Man of India") ensuring its success and saving millions from preventable health issues.</p>"""

ISOTOPES_HTML = """<p>Isotopes are different versions of the same element that have different weights. In nature, almost all iodine exists as a single, stable version called Iodine-127. However, scientists have discovered many radioactive versions, which can be incredibly useful—or sometimes dangerous.</p><p>The longest-living radioactive version is iodine-129, which has a half-life of over 16 million years! Most of the iodine-129 on Earth today comes from human nuclear activities. Other radioactive versions, like iodine-123 and iodine-125, have much shorter lives but are everyday superheroes in hospitals. Because our bodies naturally send all the iodine we consume straight to our thyroid gland, doctors can use these slightly radioactive isotopes to take special pictures of the thyroid or to treat conditions like thyroid cancer.</p><p>There is also iodine-131, which is often released during nuclear accidents. Because the thyroid quickly absorbs it, it can cause damage and increase the risk of cancer. That's why people living near nuclear accidents are sometimes given regular (stable) iodine tablets—if the thyroid is already full of safe iodine, it won't absorb the dangerous radioactive kind!</p>"""

APPLICATIONS_HTML = """<p>While you might know iodine best from the small bottle of brown liquid used to clean scrapes and cuts, it has many other jobs! About half of all the iodine produced in the world is used to make special chemical compounds for industry. It's a key ingredient in animal feed, food stabilizers, dyes, and even in photography. It's also an essential part of the "radiocontrast" dyes that doctors inject into the body to make X-ray and CT scans clearer. Because it's generally safe and absorbs X-rays well, it's perfect for helping doctors see inside us. Minor uses include helping clear smog and even "cloud seeding" to encourage rain.</p>"""

BIOLOGICAL_ROLE_HTML = """<p>Iodine is absolutely essential for life. In fact, it is the heaviest element that living organisms commonly need! Your body uses iodine to make special thyroid hormones that regulate your growth, metabolism, and development.</p><p>If a person doesn't get enough iodine from their diet, their thyroid gland works overtime trying to capture whatever little iodine is available, causing it to swell up into a condition known as a goiter. Historically, India had a famous "goiter belt" along the sub-Himalayan and Terai regions because heavy rainfall and flooding washed the natural iodine out of the soil. Later research showed that this soil depletion affected almost all regions of India, making it a nationwide challenge.</p><p>Because iodine is so crucial for brain development in babies and children, health organizations worldwide focus on making sure everyone gets enough. This is mostly done by adding a tiny amount of iodine to everyday table salt—a simple trick that has drastically improved global health.</p>"""

def main():
    with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    iodine = data['I']
    iodine['extract_html'] = EXTRACT_HTML
    
    # Overwrite sections
    iodine['sections'] = {
        "History": HISTORY_HTML,
        "Isotopes": ISOTOPES_HTML,
        "Everyday Uses": APPLICATIONS_HTML,
        "Why Our Bodies Need It": BIOLOGICAL_ROLE_HTML
    }
    
    out_path = os.path.join('src', 'ptable', 'data', 'drafts', 'Iodine.json')
    with open(out_path, 'w', encoding='utf-8') as f:
        json.dump(iodine, f, indent=4)
        
    print(f"Successfully wrote to {out_path}")

if __name__ == '__main__':
    main()
