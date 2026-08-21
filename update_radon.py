import json

def rewrite():
    with open('radon_temp.json', 'r', encoding='utf-8') as f:
        data = json.load(f)
        
    data['extract_html'] = (
        "<p><strong>Radon (Rn)</strong> is a fascinating, invisible, and odorless gas. "
        "It belongs to a group known as the \"noble gases,\" meaning it typically prefers to be left alone and doesn't easily react with other elements. "
        "What makes radon truly unique, however, is that it is radioactive. It's naturally created when heavier elements like uranium and radium slowly break down in the earth's crust.</p>"
        "<p>In the context of India, radon holds significant geographic and scientific interest. The coastal areas of Kerala, particularly places like Panmana, are known as High Background Radiation Areas (HBRAs) due to the presence of radioactive minerals in the soil, which naturally release more radon. "
        "Modern Indian scientists, particularly at the Bhabha Atomic Research Centre (BARC) in Mumbai, have dedicated extensive research to monitoring indoor radon levels across the country to ensure public health and safety. "
        "Furthermore, researchers in the Himalayan region have studied fluctuations in radon gas emissions from the earth as potential early warning signs for earthquakes.</p>"
    )

    data['sections']['Characteristics'] = (
        "Radon is a heavy, invisible gas that you cannot see, smell, or taste. "
        "Because it is much heavier than the air we breathe, it tends to settle in low-lying areas like basements and ground floors of buildings. "
        "It is highly radioactive, meaning it is constantly breaking down and releasing energy. "
        "As it breaks down, it turns into other solid radioactive elements known as 'radon daughters' or 'radon progeny', which can attach to dust particles in the air."
    )

    data['sections']['Physical properties'] = (
        "At room temperature, radon is a colorless gas. However, when it is cooled down to a solid state, it glows with a beautiful, bright yellow color! "
        "If it is cooled even further, that glow changes to an orange-red. "
        "It is one of the heaviest gases known in nature—about nine times heavier than the air we breathe. "
        "It also dissolves in water, which is why it can sometimes be found in groundwater or well water."
    )

    data['sections']['Chemical properties'] = (
        "As a 'noble gas', radon is incredibly stubborn and rarely reacts with other chemicals. "
        "For a long time, scientists believed it was completely unreactive. "
        "However, in modern laboratories, chemists have been able to force it to combine with a few other highly reactive elements, like fluorine, to create new compounds. "
        "Still, in everyday nature, you will almost always find radon completely by itself as a single, independent atom."
    )

    data['sections']['Isotopes'] = (
        "An 'isotope' is a version of an element that has a different weight. Radon has many isotopes, but they are all radioactive and unstable. "
        "The most common and longest-lasting one is called Radon-222. Even then, its 'half-life' (the time it takes for half of it to break down) is only about 3.8 days. "
        "This means radon doesn't stick around for very long; it quickly transforms into other elements like polonium and lead."
    )

    data['sections']['Occurrence'] = (
        "Radon is born from the natural decay of uranium and radium, which are found in rocks and soils all over the world. "
        "It slowly seeps out of the ground and into the air. In the open outdoors, it disperses quickly and is harmless. "
        "However, it can become trapped inside buildings, especially in poorly ventilated basements. "
        "In India, the geographic distribution of radon is very diverse. Areas with uranium-rich soils, such as parts of Punjab, Rajasthan, and the coastal monazite sands of Kerala, show naturally higher levels of radon. "
        "Institutions like Panjab University and BARC have conducted extensive surveys to map these occurrences and help guide safe building practices."
    )

    data['sections']['Applications'] = (
        "Historically, radon was sometimes used in hospitals to treat certain types of cancer through radiation therapy, though safer alternatives are mostly used today. "
        "Today, its most fascinating application is in earth sciences. Because radon is released from rocks, scientists use it to trace how groundwater flows. "
        "In India, measuring radon gas escaping from the ground along the Himalayan fault lines is an active area of research for predicting earthquakes! "
        "When tectonic plates shift and build up pressure, the rocks crack and release trapped radon, providing a potential clue that an earthquake might be coming."
    )

    import os
    os.makedirs('src/ptable/data/drafts', exist_ok=True)
    with open('src/ptable/data/drafts/Radon.json', 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2)

if __name__ == '__main__':
    rewrite()
