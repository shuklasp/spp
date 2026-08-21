import json
import os

with open('src/ptable/data/master_elements.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

og = data['Og']
og['extract_html'] = """<p><b>Oganesson</b> is a synthetic chemical element with the symbol <b>Og</b> and atomic number <b>118</b>. It holds the record for the highest atomic number and the highest atomic mass of all known elements. Oganesson is a part of group 18 (the noble gases) and period 7 in the periodic table. However, unlike familiar noble gases such as helium or neon, theoretical studies suggest that oganesson would actually be a solid at room temperature and quite chemically reactive, all thanks to complex relativistic effects.</p>
<p>Because it is highly radioactive, oganesson is incredibly difficult to study. Its only known isotope, oganesson-294, has a fleeting half-life of just 0.7 milliseconds before it decays, making any traditional chemical studies impossible. As a result, almost everything we know about this element comes from theoretical predictions rather than direct physical measurement.</p>"""

og['sections'] = {
    'History': """<p>Oganesson was first synthesized in 2002 through a groundbreaking collaboration between scientists at the Joint Institute for Nuclear Research (JINR) in Dubna, Russia, and the Lawrence Livermore National Laboratory (LLNL) in the United States. In December 2015, international scientific bodies officially recognized it as a newly discovered element. It was formally named on November 28, 2016, in honor of the renowned nuclear physicist Yuri Oganessian, who played a leading role in the discovery of the heaviest elements in the periodic table.</p>
<p>While Indian scientists were not directly involved in the initial synthesis of oganesson, India has a rich and ongoing history of contributing to the global pursuit of superheavy elements. Researchers from premier Indian institutions, such as the Saha Institute of Nuclear Physics (SINP) in Kolkata and various Indian Institutes of Technology (IITs), are active participants in international nuclear physics collaborations. For example, Indian physicists have recently contributed to the discovery of new isotopes like Seaborgium-257 at global facilities such as the GSI Helmholtz Centre in Germany. Furthermore, India's theoretical physicists provide essential mathematical frameworks and computing models to predict the properties of fleeting elements like oganesson, helping scientists worldwide chart the elusive "island of stability" in nuclear physics.</p>""",
    'Characteristics': """<p>Other than its basic nuclear properties, none of oganesson's physical or chemical traits have ever been directly measured. This is partly due to the immense difficulty and expense of producing even a single atom, and partly because any atom created decays in a fraction of a millisecond.</p>
<p>All of our current understanding of its characteristics—including predictions that it forms a face-centered cubic crystal structure, that it is metallic in appearance, and that it behaves surprisingly unlike a traditional noble gas—stems entirely from complex theoretical physics and computational models.</p>"""
}

os.makedirs('src/ptable/data/drafts', exist_ok=True)
with open('src/ptable/data/drafts/Oganesson.json', 'w', encoding='utf-8') as f:
    json.dump({'Og': og}, f, indent=2, ensure_ascii=False)

print("Done")
