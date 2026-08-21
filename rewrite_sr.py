import json
import os

with open('sr_data.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

extract_html = """<p><b><a href="/school1/ptable/element/Sr" class="wiki-link" title="Strontium (Sr)">Strontium</a></b> is a soft, silvery metal with a pale yellow tint. If you've ever watched a spectacular red firework light up the night sky, you've seen strontium in action! In its pure form, this element is highly reactive and must be stored in mineral oil to keep it from turning into a powder when it meets the air.</p>
<p>Beyond making fireworks and emergency flares burn crimson red, strontium has some amazing connections to our world and history. In India, it was recently declared one of the nation's 30 "critical minerals." Exciting new deposits were just discovered in the coal mines of Telangana! It also helps us solve ancient mysteries: Indian archaeologists use "strontium isotope analysis" on ancient teeth and bones to trace the movement of early humans and trade goods\u2014like proving that ancient cotton found in Arabia originally came from India.</p>
<p>Strontium even has a place in medicine. Because it is chemically similar to calcium, our bodies absorb it into our bones. In modern Indian healthcare and homeopathy (where it's known as <i>Strontium carbonicum</i>), specialized strontium compounds are used to treat bone pain and manage osteoporosis. From dazzling fireworks to cutting-edge science and ancient history, strontium is truly a remarkable element!</p>"""

sections = {
    "Characteristics": "Strontium is a soft, shiny metal with a yellowish hue. It is highly reactive, meaning it easily undergoes chemical changes when exposed to things like air or water. Because it reacts so quickly with oxygen and moisture, it must be stored in special liquids like mineral oil. In nature, you won't find pure strontium lying around; it's always bonded to other elements.",
    "Isotopes": "An atom's identity is determined by how many protons it has, but the number of neutrons can vary. These variations are called \"isotopes.\" Strontium has four naturally occurring isotopes. Scientists can look at the ratios of these isotopes\u2014especially in a technique called \"strontium isotope analysis.\" In India, archaeologists use this fascinating method on ancient teeth and artifacts to trace the ancient trade routes and movements of early human populations.",
    "History": "Strontium's story began in 1790 near a Scottish village called Strontian, where it was first identified in a mineral. It was later isolated as a pure element by Sir Humphry Davy in 1808. Today, its historical significance has expanded. For example, recent discoveries of strontium deposits in the coal mines of Telangana mark an important chapter for modern India's resource independence, leading to its classification as a critical mineral for the nation.",
    "Occurrence": "You typically find strontium in minerals like celestine and strontianite. While major deposits have traditionally been found in places like China and Spain, India has recently made significant strides by discovering strontium within coal mining waste in the Telangana region. Recognizing its value for modern industries, India now lists strontium as one of its 30 critical minerals, aiming to tap into these domestic sources.",
    "Applications": "If you enjoy the bright red bursts in fireworks or the red glow of emergency road flares, you are admiring strontium at work! Besides its starring role in pyrotechnics, strontium is used to make special types of glass and magnets. In modern times, it is also being researched for new industrial applications as part of India's push to secure essential resources for future technologies.",
    "Biological role": "Strontium behaves a lot like calcium in the human body, meaning our bodies will happily absorb it into our bones. This unique trait makes it very useful in medicine. In Indian healthcare and homeopathy (known as <i>Strontium carbonicum</i>), specific strontium compounds are used to treat bone pain and help manage diseases like osteoporosis by making bones stronger. However, radioactive forms of strontium can be dangerous because they can also settle into bones and cause harm."
}

data['extract_html'] = extract_html
data['sections'] = sections

out_path = 'c:/projects/apache/school1/src/ptable/data/drafts/Strontium.json'
os.makedirs(os.path.dirname(out_path), exist_ok=True)
with open(out_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=4)
