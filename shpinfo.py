import struct, os
base = r"C:\Users\Henok Petros\Desktop\Automation Projects\Laravel Projects\Hydro\example-app\review_assets"
for folder, name in [(r"Met_stations\Met_stations", "Metrology_stations"), (r"Ethio_basins\Ethio_basins", "Ethio_basin")]:
    d = os.path.join(base, folder)
    print("="*20, name)
    prj = os.path.join(d, name + ".prj")
    if os.path.exists(prj):
        print("PRJ:", open(prj, encoding="utf-8", errors="replace").read()[:300])
    shp = open(os.path.join(d, name + ".shp"), "rb").read(100)
    stype = struct.unpack("<i", shp[32:36])[0]
    bbox = struct.unpack("<4d", shp[36:68])
    print("ShapeType:", stype, "BBox:", [round(v, 4) for v in bbox])
    dbf = open(os.path.join(d, name + ".dbf"), "rb").read()
    nrec = struct.unpack("<I", dbf[4:8])[0]
    hlen = struct.unpack("<H", dbf[8:10])[0]
    rlen = struct.unpack("<H", dbf[10:12])[0]
    fields, pos = [], 32
    while dbf[pos] != 0x0D:
        fname = dbf[pos:pos+11].split(b"\x00")[0].decode("latin-1")
        ftype = chr(dbf[pos+11]); flen = dbf[pos+16]; fdec = dbf[pos+17]
        fields.append((fname, ftype, flen, fdec)); pos += 32
    print("Records:", nrec, "Fields:", fields)
    for i in range(min(3, nrec)):
        rec = dbf[hlen + i*rlen: hlen + (i+1)*rlen]
        p = 1; vals = []
        for f in fields:
            vals.append(rec[p:p+f[2]].decode("latin-1").strip()); p += f[2]
        print(" rec", i+1, vals)
