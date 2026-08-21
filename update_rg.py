import json
import os

with open("rg_scratch.json", "r", encoding="utf-8") as f:
    data = json.load(f)

data["extract_html"] = """<b>Roentgenium</b> (symbol <b>Rg</b>) is a synthetic, highly radioactive chemical element with atomic number 111. It is a superheavy element that does not exist in nature and can only be created in specialized laboratories. It was first synthesized in 1994 at the GSI Helmholtz Centre for Heavy Ion Research in Darmstadt, Germany. The element is named after Wilhelm R&ouml;ntgen, the pioneering physicist who discovered X-rays in 1895. Interestingly, R&ouml;ntgen's discovery of X-rays quickly reached colonial India, where scientists like Dr. Mahendralal Sircar and Sir Jagadish Chandra Bose conducted some of the earliest experiments with X-ray apparatuses in the late 1890s. Today, Indian researchers, such as teams from IIT Roorkee and the Saha Institute of Nuclear Physics, actively collaborate in international efforts at the GSI Helmholtz Centre&mdash;the very place roentgenium was discovered&mdash;to study superheavy elements and push the boundaries of the periodic table. Roentgenium has no known stable isotopes, and its most stable known isotope has a half-life of just over a few minutes. Because it decays so quickly, roentgenium has no commercial applications and is used strictly for scientific research."""

data["sections"]["History"] = """<p>Roentgenium was first created in 1994 by an international team of scientists led by Sigurd Hofmann at the Gesellschaft f&uuml;r Schwerionenforschung (GSI) in Darmstadt, Germany. They bombarded a target of bismuth-209 with nickel-64 nuclei to produce a few atoms of roentgenium-272. In 2004, the element was officially named roentgenium in honor of the German physicist Wilhelm Conrad R&ouml;ntgen, who discovered X-rays.</p>
<p>While R&ouml;ntgen never visited India, his discovery of X-rays had an almost immediate and profound impact on Indian science. Within months of his 1895 discovery, news reached Calcutta, prompting pioneering Indian scientists like Dr. Mahendralal Sircar and Sir Jagadish Chandra Bose to construct their own X-ray devices and perform experiments. In 1995, India commemorated the centenary of R&ouml;ntgen's groundbreaking discovery by issuing a special postage stamp. Furthermore, Indian scientific contribution continues today at the very facility where roentgenium was discovered. Researchers from institutions like IIT Roorkee and the Saha Institute of Nuclear Physics actively participate in global collaborations at the GSI, exploring new superheavy elements and expanding our understanding of the universe's fundamental building blocks.</p>"""

data["sections"]["Isotopes"] = """<p>Roentgenium does not occur naturally and has no stable isotopes. All of its isotopes are highly radioactive and decay extremely quickly, mostly through alpha decay or spontaneous fission. To date, several isotopes of roentgenium have been created in laboratories, with atomic masses ranging from 272 to 286. The most stable known isotope is roentgenium-282, with a half-life of around 130 seconds. Because these isotopes exist for such a short time before decaying into lighter elements, scientists have only been able to produce and study a few atoms of roentgenium throughout history. This makes understanding its chemical properties extremely challenging, and research remains largely theoretical. Modern research, including theoretical studies by Indian physicists using advanced models like the Coulomb and Proximity Potential Model, continues to investigate the stability and decay of these fleeting superheavy isotopes.</p>"""

out_dir = "src/ptable/data/drafts"
os.makedirs(out_dir, exist_ok=True)

with open(f"{out_dir}/Roentgenium.json", "w", encoding="utf-8") as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print("Successfully written to src/ptable/data/drafts/Roentgenium.json")
