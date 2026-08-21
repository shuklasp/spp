import json
import os

data_dir = r"c:\projects\apache\school1\src\ptable\data"
merged = {}

for part in ["part1.json", "part2.json", "part3.json"]:
    with open(os.path.join(data_dir, part), "r", encoding="utf-8") as f:
        data = json.load(f)
        merged.update(data)

with open(os.path.join(data_dir, "compound_targets.json"), "w", encoding="utf-8") as f:
    json.dump(merged, f, indent=2)

print(f"Merged {len(merged)} elements.")
for k, v in merged.items():
    if len(v) != 30:
        print(f"Warning: {k} has {len(v)} compounds")
