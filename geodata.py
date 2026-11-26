import struct, os, json, math

base = r"C:\Users\Henok Petros\Desktop\Automation Projects\Laravel Projects\Hydro\example-app\review_assets"
OUT = r"C:\Users\Henok Petros\Desktop\Automation Projects\Laravel Projects\Hydro\example-app\storage\app\geodata"
os.makedirs(OUT, exist_ok=True)

A = 6378249.145; F = 1/293.465           # Clarke 1880 RGS (Adindan)
E2 = F*(2-F)

def utm_inv(E, N, lon0=39.0, k0=0.9996, FE=500000.0, FN=0.0):
    ep2 = E2/(1-E2)
    x = E - FE; y = N - FN
    M = y/k0
    mu = M/(A*(1 - E2/4 - 3*E2**2/64 - 5*E2**3/256))
    e1 = (1 - math.sqrt(1-E2))/(1 + math.sqrt(1-E2))
    p1 = (mu + (3*e1/2 - 27*e1**3/32)*math.sin(2*mu) + (21*e1**2/16 - 55*e1**4/32)*math.sin(4*mu)
          + (151*e1**3/96)*math.sin(6*mu) + (1097*e1**4/512)*math.sin(8*mu))
    C1 = ep2*math.cos(p1)**2; T1 = math.tan(p1)**2
    N1 = A/math.sqrt(1-E2*math.sin(p1)**2)
    R1 = A*(1-E2)/(1-E2*math.sin(p1)**2)**1.5
    D = x/(N1*k0)
    lat = p1 - (N1*math.tan(p1)/R1)*(D**2/2 - (5+3*T1+10*C1-4*C1**2-9*ep2)*D**4/24
          + (61+90*T1+298*C1+45*T1**2-252*ep2-3*C1**2)*D**6/720)
    lon = math.radians(lon0) + (D - (1+2*T1+C1)*D**3/6 + (5-2*C1+28*T1-3*C1**2+8*ep2+24*T1**2)*D**5/120)/math.cos(p1)
    return math.degrees(lat), math.degrees(lon)

def geodetic_to_ecef(lat, lon):
    lat, lon = math.radians(lat), math.radians(lon)
    N = A/math.sqrt(1-E2*math.sin(lat)**2)
    return ((N)*math.cos(lat)*math.cos(lon), (N)*math.cos(lat)*math.sin(lon), (N*(1-E2))*math.sin(lat))

def ecef_to_geodetic(x, y, z):
    lon = math.atan2(y, x); p = math.hypot(x, y)
    lat = math.atan2(z, p*(1-E2))
    for _ in range(8):
        N = A/math.sqrt(1-E2*math.sin(lat)**2)
        h = p/math.cos(lat) - N
        lat = math.atan2(z, p*(1 - E2*N/(N+h)))
    N = A/math.sqrt(1-E2*math.sin(lat)**2)
    h = p/math.cos(lat) - N
    return math.degrees(lat), math.degrees(lon), h

# Adindan -> WGS84 (EPSG:1108 / common EMA 3-parameter)
DX, DY, DZ = -166.0, -15.0, 204.0

def utm_adindan_to_wgs84(E, N):
    lat, lon = utm_inv(E, N)
    x, y, z = geodetic_to_ecef(lat, lon)
    return ecef_to_geodetic(x + DX, y + DY, z + DZ)

def read_dbf(path):
    b = open(path,'rb').read(); n = struct.unpack("<I", b[4:8])[0]
    hl = struct.unpack("<H", b[8:10])[0]; rl = struct.unpack("<H", b[10:12])[0]
    fields, p = [], 32
    while b[p] != 0x0D:
        fields.append((b[p:p+11].split(b"\x00")[0].decode("latin-1"), chr(b[p+11]), b[p+16])); p += 32
    out = []
    for i in range(n):
        rec = b[hl+i*rl: hl+(i+1)*rl]; q = 1; row = {}
        for f in fields:
            row[f[0]] = rec[q:q+f[2]].decode("latin-1").strip(); q += f[2]
        out.append(row)
    return out

def read_shapes(path):
    b = open(path,'rb').read(); off = 100; shapes = []
    while off < len(b):
        ln = struct.unpack(">i", b[off+4:off+8])[0]*2; o = off+8
        t = struct.unpack("<i", b[o:o+4])[0]
        if t == 1:
            shapes.append(("point", struct.unpack("<2d", b[o+4:o+20])))
        elif t == 5:
            nparts, npts = struct.unpack("<2i", b[o+36:o+44])
            parts = list(struct.unpack("<%di" % nparts, b[o+44:o+44+4*nparts]))
            po = o+44+4*nparts
            co = list(struct.unpack("<%dd" % (npts*2), b[po:po+16*npts]))
            pts = [(co[i], co[i+1]) for i in range(0, len(co), 2)]
            shapes.append(("polygon", [pts[parts[i]: parts[i+1] if i+1 < nparts else npts] for i in range(nparts)]))
        else:
            shapes.append(("other", None))
        off += 8 + ln
    return shapes

def point_in(x, y, rings):
    c = False
    for r in rings:
        for i in range(len(r)):
            x1,y1 = r[i]; x2,y2 = r[(i+1)%len(r)]
            if ((y1 > y) != (y2 > y)) and (x < (x2-x1)*(y-y1)/(y2-y1+1e-18)+x1):
                c = not c
    return c

sd = os.path.join(base, r"Met_stations\Met_stations\Metrology_stations")
sattrs = read_dbf(sd+".dbf"); sgeom = read_shapes(sd+".shp")
bd = os.path.join(base, r"Ethio_basins\Ethio_basins\Ethio_basin")
battrs = read_dbf(bd+".dbf"); bgeom = read_shapes(bd+".shp")

stations = []
for a, g in zip(sattrs, sgeom):
    if g[0] != "point": continue
    E, N = g[1]
    lat, lon, h = utm_adindan_to_wgs84(E, N)
    stations.append({
        "name": a["NAME"], "class": a["Class"], "easting": E, "northing": N,
        "lat": round(lat, 7), "lon": round(lon, 7),
        "altitude": a.get("Altitude", ""), "installed": a.get("Installed", ""),
        "status": a.get("Status", ""), "class_code": a.get("Class_1", ""),
        "availability": {k: a.get(k, "") for k in ["Rainfall","Temprature","Relative_H","Wind_speed","Sun_shine","Pan_Evapor","Pitch_pan"]},
    })

basins = []
for a, g in zip(battrs, bgeom):
    if g[0] != "polygon": continue
    rings = [[tuple(round(v, 7) for v in utm_adindan_to_wgs84(x, y)[:2]) for (x, y) in ring] for ring in g[1]]
    rings = [[(lon, lat) for (lat, lon) in ring] for ring in rings]
    flat = [pt for r in rings for pt in r]
    basins.append({
        "name": a["BASINNAME"], "runoff": float(a["Runoff"]), "area": float(a["AREA"]),
        "perimeter": float(a["PERIMETER"]),
        "min_lng": min(p[0] for p in flat), "min_lat": min(p[1] for p in flat),
        "max_lng": max(p[0] for p in flat), "max_lat": max(p[1] for p in flat),
        "centroid_lat": round(sum(p[1] for p in flat)/len(flat), 7),
        "centroid_lng": round(sum(p[0] for p in flat)/len(flat), 7),
        "rings": [[[round(x, 7), round(y, 7)] for x, y in r] for r in rings],
        "src_rings": g[1],
    })

for s in stations:
    s["basin"] = None
    for b in basins:
        if point_in(s["easting"], s["northing"], b["src_rings"]):
            s["basin"] = b["name"]; break

for b in basins: b.pop("src_rings")

json.dump({"stations": stations, "basins": basins}, open(os.path.join(OUT, "geodata.json"), "w", encoding="utf-8"), ensure_ascii=False)

unassigned = [s["name"] for s in stations if not s["basin"]]
print("stations:", len(stations), "basins:", len(basins), "unassigned:", len(unassigned), unassigned[:6])
print("lat range:", round(min(s["lat"] for s in stations),4), round(max(s["lat"] for s in stations),4))
print("shift sample (Adindan->WGS84, metres approx):", round((stations[0]["easting"]),1), "->", stations[0]["lat"], stations[0]["lon"])
print("with altitude:", sum(1 for s in stations if s["altitude"]), "with installed:", sum(1 for s in stations if s["installed"]))
print("rainfall available:", sum(1 for s in stations if s["availability"]["Rainfall"]), "temp:", sum(1 for s in stations if s["availability"]["Temprature"]))
