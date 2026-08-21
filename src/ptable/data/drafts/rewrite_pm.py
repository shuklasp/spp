import json

def run():
    # Load Promethium JSON
    with open("c:/projects/apache/school1/src/ptable/data/drafts/Pm.json", "r", encoding="utf-8") as f:
        pm_data = json.load(f)

    # Rewrite extract_html
    pm_data["extract_html"] = """
    <h2>The Stolen Fire of the Atomic Age</h2>
    <p><strong>Promethium</strong> (atomic number 61) is a rare and highly radioactive metal. In Greek mythology, Prometheus stole fire from the gods to give to humanity. Similarly, promethium offers a kind of "nuclear fire" that provides long-lasting power. In a beautiful cross-cultural parallel, the ancient Indian traditions revere <em>Agni</em>, the god of fire, who represents the spark of life and the transformative energy of the universe. Just as ancient Vedic texts describe Agni as an eternal flame, the steady radioactive glow of promethium serves as an enduring source of energy for modern technology.</p>
    <p>Because it is so radioactive, promethium does not naturally occur on Earth in large amounts—it is constantly glowing and decaying. Instead, it was discovered in the fallout of nuclear reactions in 1945. Today, scientists at institutions like India's Bhabha Atomic Research Centre (BARC) study its unique properties, while space agencies around the world, including the Indian Space Research Organisation (ISRO), explore similar radioisotope technologies to keep satellites and space probes powered in the freezing darkness of deep space.</p>
    """

    # Rewrite sections
    pm_data["sections"]["Physical properties"] = """
    <h3>A Glowing Metal</h3>
    <p>If you could safely hold a piece of promethium, you would see a silvery-white metal. But you wouldn't want to hold it for long! It is intensely radioactive. The radiation it emits causes the air around it to glow with a pale blue or greenish light in the dark. It is a solid metal at room temperature, but it melts at about 1,042 °C (1,908 °F).</p>
    <p>Like the eternal fires described in ancient Indian <em>Vedas</em>, promethium produces its own steady warmth and light as its atoms decay over time.</p>
    """

    pm_data["sections"]["Isotopes"] = """
    <h3>Unstable Atoms</h3>
    <p>Every element is made of atoms, and atoms have different variations called <em>isotopes</em>. Most elements have at least one stable, unchanging isotope. Promethium is an exception—<strong>none of its isotopes are stable</strong>. Every single atom of promethium will eventually break apart (decay) into other elements, releasing energy.</p>
    <p>Its longest-lived isotope, Promethium-145, lasts for about 17.7 years before half of it disappears. The most useful isotope, Promethium-147, has a half-life of just 2.6 years. This fleeting, temporary nature means promethium is always transforming, echoing the ancient philosophical concepts of impermanence found in early Buddhist and Hindu thought across the Indian subcontinent.</p>
    """

    pm_data["sections"]["Occurrence"] = """
    <h3>Rarer Than Gold</h3>
    <p>You won't find promethium easily in nature. Because all of it decays so quickly, any promethium that existed when Earth was formed billions of years ago has long since turned into other elements. Today, the only natural promethium on Earth is created in tiny amounts when uranium ores undergo rare natural nuclear reactions.</p>
    <p>Scientists estimate there are less than 600 grams (about 1.3 pounds) of natural promethium in Earth's entire crust at any given moment! Almost all the promethium used in science and industry today is artificially created in nuclear reactors, such as those operated by the Department of Atomic Energy in India and similar organizations globally.</p>
    """

    pm_data["sections"]["History"] = """
    <h3>The Missing Element</h3>
    <p>For decades, scientists knew there had to be an element number 61 to fill a gap in the periodic table, but it was nowhere to be found. Researchers around the world searched for it in minerals and ores, often mistaking other elements for it.</p>
    <p>It wasn't until 1945, during the Manhattan Project in the United States, that chemists finally proved its existence by analyzing the radioactive byproducts of a nuclear reactor. Grace Mary Coryell, the wife of one of the discoverers, suggested the name "promethium" after the Greek titan Prometheus, signifying the brave new era of nuclear energy.</p>
    """

    pm_data["sections"]["Applications"] = """
    <h3>Powering the Future</h3>
    <p>Promethium's steady release of energy makes it incredibly useful. When placed inside a special material, its radiation creates a reliable light source that doesn't need electricity. It is also used to make <strong>atomic batteries</strong>. Unlike the chemical batteries in your phone, atomic batteries can last for years without needing a charge.</p>
    <p>These batteries are perfect for places where it's impossible to change a battery, such as pacemakers inside the human body or remote space probes. In India, modern space exploration spearheaded by ISRO relies on the deep understanding of such nuclear materials to design long-lasting power sources for missions reaching out to the Moon, Mars, and beyond, turning the "stolen fire" of promethium into a tool for cosmic discovery.</p>
    """

    # Save to the requested output file
    with open("c:/projects/apache/school1/src/ptable/data/drafts/Promethium.json", "w", encoding="utf-8") as f:
        json.dump(pm_data, f, indent=2)

if __name__ == "__main__":
    run()
