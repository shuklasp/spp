import json

extract_html = """<p><b>Barium</b> is a soft, silvery-white metal that you almost never see in its pure form because it reacts so easily with air! In the modern world, it's famous for giving fireworks a brilliant green color and for its crucial role in the oil and gas industry. Although it wasn't isolated until 1808 by Sir Humphry Davy, Barium holds a massive significance in <b>India's geological landscape</b>. The <b>Mangampet deposit</b> in Andhra Pradesh, India, is the single largest bedded deposit of baryte (barium sulfate) in the entire world! Today, Barium is a hidden hero in everything from drilling fluids to medical imaging.</p>"""

sections = {
    "Characteristics & Physical Properties": "<p>Imagine a metal so soft you can cut it with a knife, yet so reactive it must be stored under oil to prevent it from rapidly tarnishing in the air. That's Barium! When pure, it's a shiny, silvery-white alkaline earth metal, but it eagerly binds with oxygen and water. Because it's so reactive, it never occurs in nature as a free element. Instead, you'll find it locked away in minerals like baryte (barium sulfate) and witherite (barium carbonate).</p>",
    "Geological Significance in India": "<p>While ancient Indian texts like the Ayurveda heavily documented metals like gold and iron, Barium was quietly waiting beneath the earth. Today, India is a powerhouse of Barium production! The <b>Mangampet deposit</b> in the Kadapa district of Andhra Pradesh is globally renowned. Discovered in the 1960s, it's the <b>world's largest single deposit of bedded barytes</b>. Formed millions of years ago by ancient volcanic activity, this site is so crucial that it was declared a National Geological Monument in 1982. The massive reserves here make India one of the leading global suppliers of this vital mineral.</p>",
    "History": "<p>The story of Barium begins in the 17th century when alchemists in Italy were fascinated by \"Bologna stones\" that glowed in the dark after being heated. However, the elemental form wasn't discovered until 1808, when the brilliant English chemist Sir Humphry Davy managed to isolate it using electricity. As modern science grew in India, pioneering chemists like <b>Prafulla Chandra Ray</b> (often called the father of Indian chemistry) studied various complex chemical compounds, connecting traditional Indian metallurgical knowledge with the rigorous modern study of elements like Barium.</p>",
    "Applications": "<p>Barium is a busy element! Most of the Barium mined in places like Mangampet, India, is used as a \"weighting agent\" in the oil and gas industry to help drill deep wells safely. But Barium has a flashy side too\u2014barium compounds are the secret ingredient that gives fireworks their spectacular green explosions. In the medical world, a chalky \"barium swallow\" drink helps doctors take clear X-ray images of the digestive system. It's also used to remove unwanted gases from vacuum tubes, and in creating special kinds of glass and ceramics.</p>"
}

with open("c:/projects/apache/school1/barium_raw.json", "r", encoding="utf-8") as f:
    barium_data = json.load(f)

barium_data["extract_html"] = extract_html
barium_data["sections"] = sections

with open("c:/projects/apache/school1/src/ptable/data/drafts/Barium.json", "w", encoding="utf-8") as out:
    json.dump(barium_data, out, indent=4)

print("Saved Barium.json successfully!")
