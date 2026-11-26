import struct, os, openpyxl

base = r"C:\Users\Henok Petros\Desktop\Automation Projects\Laravel Projects\Hydro\example-app\review_assets"

def read_dbf(path):
    b = open(path,'rb').read()
    n = struct.unpack("<I", b[4:8])[0]; hl = struct.unpack("<H", b[8:10])[0]; rl = struct.unpack("<H", b[10:12])[0]
    fields, p = [], 32
    while b[p] != 0x0D:
        name = b[p:p+11].split(b"\x00")[0].decode("latin-1")
        fields.append((name, chr(b[p+11]), b[p+16])); p += 32
    out = []
    for i in range(n):
        rec = b[hl+i*rl: hl+(i+1)*rl]; q = 1; row = {}
        for f in fields:
            row[f[0]] = rec[q:q+f[2]].decode("latin-1").strip(); q += f[2]
        out.append(row)
    return out

def read_points(path):
    b = open(path,'rb').read(); off = 100; pts = []
    while off < len(b):
        ln = struct.unpack(">i", b[off+4:off+8])[0]*2
        t = struct.unpack("<i", b[off+8:off+12])[0]
        if t == 1:
            x, y = struct.unpack("<2d", b[off+12:off+28]); pts.append((x,y))
        else:
            pts.append(None)
        off += 8 + ln
    return pts

def read_polygons(path):
    b = open(path,'rb').read(); off = 100; polys = []
    while off < len(b):
        ln = struct.unpack(">i", b[off+4:off+8])[0]*2
        base_o = off + 8
        t = struct.unpack("<i", b[base_o:base_o+4])[0]
        if t == 5:
            nparts, npts = struct.unpack("<2i", b[base_o+36:base_o+44])
            parts = list(struct.unpack("<%di" % nparts, b[base_o+44: base_o+44+4*nparts]))
            po = base_o+44+4*nparts
            coords = list(struct.unpack("<%dd" % (npts*2), b[po: po+16*npts]))
            pts = [(coords[i], coords[i+1]) for i in range(0, len(coords), 2)]
            rings = [pts[parts[i]: parts[i+1] if i+1 < nparts else npts] for i in range(nparts)]
            polys.append(rings)
        off += 8 + ln
    return polys

def inside(x, y, rings):
    # even-odd across all rings
    c = False
    for r in rings:
        for i in range(len(r)):
            x1,y1 = r[i]; x2,y2 = r[(i+1)%len(r)]
            if ((y1 > y) != (y2 > y)) and (x < (x2-x1)*(y-y1)/(y2-y1+1e-18)+x1):
                c = not c
    return c

sd = os.path.join(base, r"Met_stations\Met_stations\Metrology_stations")
srows = read_dbf(sd + ".dbf"); spts = read_points(sd + ".shp")
shp = {}
for r, p in zip(srows, spts):
    if p: shp[r["NAME"].upper()] = (p[0], p[1], r)

wb = openpyxl.load_workbook(r"C:\Users\Henok Petros\Downloads\Telegram Desktop\Met stations.xlsx", data_only=True)
ws = wb["Sheet1"]
rows = [r for r in ws.iter_rows(min_row=2, values_only=True) if r[1]]
print("excel rows:", len(rows), "shapefile points:", len(spts))

matched = miss = 0; maxdev = 0.0; worst = None
for r in rows:
    key = str(r[1]).strip().upper()
    if key in shp:
        matched += 1
        d = max(abs(float(r[2]) - shp[key][0]), abs(float(r[3]) - shp[key][1]))
        if d > maxdev: maxdev, worst = d, key
    else:
        miss += 1
print("name matched:", matched, "unmatched:", miss, "max coord deviation (m):", round(maxdev, 4), worst or "")

bd = os.path.join(base, r"Ethio_basins\Ethio_basins\Ethio_basin")
brows = read_dbf(bd + ".dbf"); polys = read_polygons(bd + ".shp")
print("basins:", [(r["BASINNAME"], round(float(r["Runoff"]),2)) for r in brows])

counts = {r["BASINNAME"]: 0 for r in brows}; outside = []
for r in rows:
    x, y = float(r[2]), float(r[3]); hit = None
    for meta, rings in zip(brows, polys):
        if inside(x, y, rings): hit = meta["BASINNAME"]; break
    if hit: counts[hit] += 1
    else: outside.append(str(r[1]))
print("stations inside a basin polygon:", sum(counts.values()), "outside:", len(outside))
print(counts)
print("outside sample:", outside[:12])
