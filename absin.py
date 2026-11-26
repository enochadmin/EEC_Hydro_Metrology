import json
d = json.load(open(r"C:\Users\Henok Petros\Desktop\Automation Projects\Laravel Projects\Hydro\example-app\storage\app\geodata\geodata.json", encoding="utf-8"))
hits = [s["name"] for s in d["stations"] if "absin" in s["name"].lower()]
print("shapefile candidates:", hits)
