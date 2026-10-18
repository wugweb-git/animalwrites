"""Rows of the HTML tables in a saved page (stdlib only). tables(path) -> list of tables, each a list of rows of cell text;
mailto: links are added to their cell."""
import re
import sys
from html.parser import HTMLParser
class T(HTMLParser):
    def __init__(s): super().__init__(); s.tables=[]; s.row=None; s.cell=None; s.depth=0
    def handle_starttag(s,t,a):
        if t=='table': s.tables.append([])
        elif t=='tr' and s.tables: s.row=[]
        elif t in('td','th') and s.row is not None: s.cell=[]
        elif t=='br' and s.cell is not None: s.cell.append(' ')
        elif t=='a' and s.cell is not None:
            h=dict(a).get('href','')
            if h.startswith('mailto:'): s.cell.append(' '+h[7:]+' ')
    def handle_endtag(s,t):
        if t in('td','th') and s.cell is not None and s.row is not None: s.row.append(re.sub(r'\s+',' ',''.join(s.cell)).strip()); s.cell=None
        elif t=='tr' and s.row is not None and s.tables: s.tables[-1].append(s.row); s.row=None
    def handle_data(s,d):
        if s.cell is not None: s.cell.append(d)
def tables(path):
    p=T(); p.feed(open(path,encoding='utf-8',errors='ignore').read()); return [t for t in p.tables if len(t)>2]
if __name__=='__main__':
    for f in sys.argv[1:]:
        for i,t in enumerate(tables(f)):
            print('==',f,i,len(t)); [print('  ',r) for r in t[:3]]
