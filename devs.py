import struct, os, openpyxl
base = r"C:\Users\Henok Petros\Desktop\Automation Projects\Laravel Projects\Hydro\example-app\review_assets"
def read_dbf(path):
    b = open(path,'rb').read(); n = struct.unpack("<I", b[4:8])[0]; hl = struct.unpack("<H", b[8:10])[0]; rl = struct.unpack("<H", b[10:12])[0]
    f, p = [], 32
    while b[p] != 0x0D:
        f.append((b[p:p+11].split(b"\x00")[0].decode("latin-1"), chr(b[p+11]), b[p+16])); p += 32
    out=[]
    for i in range(n):
        rec=b[hl+i*rl: hl+(i+1)*rl]; q=1; row={}
        for x in f: row[x[0]]=rec[q:q+x[2]].decode("latin-1").strip(); q+=x[2]
        out.append(row)
    return out
def read_points(path):
    b=open(path,'rb').read(); off=100; pts=[]
    while off < len(b):
        ln=struct.unpack(">i", b[off+4:off+8])[0]*2; t=struct.unpack("<i", b[off+8:off+12])[0]
        pts.append(struct.unpack("<2d", b[off+12:off+28]) if t==1 else None); off+=8+ln
    return pts
sd = os.path.join(base, r"Met_stations\Met_stations\Metrology_stations")
rows = read_dbf(sd+".dbf"); pts = read_points(sd+".shp")
shp = {r["NAME"].upper(): (p, r) for r,p in zip(rows,pts) if p}
wb = openpyxl.load_workbook(r"C:\Users\Henok Petros\Downloads\Telegram Desktop\Met stations.xlsx", data_only=True)
xl = {str(r[1]).strip().upper(): r for r in wb["Sheet1"].iter_rows(min_row=2, values_only=True) if r[1]}
devs = []
for k,(p,r) in shp.items():
    e = xl[k]
    d = max(abs(float(e[2])-p[0]), abs(float(e[3])-p[1]))
    devs.append((d,k,float(e[2]),float(e[3]),p[0],p[1]))
devs.sort(reverse=True)
print("top deviations (name | excel E,N | shp E,N | metres):")
for d,k,x1,y1,x2,y2 in devs[:6]:
    print(f"{k:28s} {x1:12.1f},{y1:12.1f}  {x2:12.1f},{y2:12.1f}  {d:10.1f}")
print("count >1000m:", sum(1 for d in devs if d[0]>1000), " >100m:", sum(1 for d in devs if d[0]>100))
for k in ["BURE","FAFFEN","AWBARE(TEFERI BER)"]:
    p,r = shp[k]; e = xl[k]
    print("\n", k, "\n  excel:", e[2], e[3], "\n  shp:  ", p[0], p[1], "\n  attrs:", {kk:r[kk] for kk in ["Class","Altitude","Installed","Status"]})
