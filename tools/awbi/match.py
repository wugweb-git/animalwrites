import json,collections,re,difflib,sys
S=sys.argv[1]
d=json.load(open(S+'/awo_2026.json')); T=json.load(open(S+'/terms.json')); abc=json.load(open(S+'/abc_pr.json'))
ALIAS_STATE={'andaman & nicobar':'andaman and nicobar islands','chattisgarh':'chhattisgarh','jammu & kashmir':'jammu and kashmir','dadra & nagar haveli':'the dadra and nagar haveli and daman and diu','pondicherry':'puducherry','uttrakhand':'uttarakhand'}
# Common old/alternate district spellings -> LGD names (lower-case).
ALIAS_DIST={'visakapattinam':'visakhapatnam','vishakapatnam':'visakhapatnam','vizag':'visakhapatnam','bangalore':'bengaluru urban','bangalore urban':'bengaluru urban','bangalore rural':'bengaluru rural','bombay':'mumbai','mumbai suburban':'mumbai suburban','gurgaon':'gurugram','mysore':'mysuru','belgaum':'belagavi','bellary':'ballari','gulbarga':'kalaburagi','shimoga':'shivamogga','tumkur':'tumakuru','bijapur':'vijayapura','hubli':'dharwad','calcutta':'kolkata','madras':'chennai','trivandrum':'thiruvananthapuram','cochin':'ernakulam','calicut':'kozhikode','trichy':'tiruchirappalli','tiruchirapalli':'tiruchirappalli','allahabad':'prayagraj','faizabad':'ayodhya','baroda':'vadodara','poona':'pune','nasik':'nashik','ahmednagar':'ahilyanagar','aurangabad':'chhatrapati sambhajinagar','osmanabad':'dharashiv','new delhi':'new delhi','hoshangabad':'narmadapuram','mohali':'sas nagar','sahibzada ajit singh nagar':'sas nagar','kanchipuram':'kancheepuram','tuticorin':'thoothukudi','ooty':'the nilgiris','nilgiris':'the nilgiris','cuddapah':'ysr kadapa','kadapa':'ysr kadapa','nellore':'spsr nellore','rangareddy':'ranga reddy','gauhati':'kamrup metropolitan','guwahati':'kamrup metropolitan','pondicherry':'puducherry','sangli':'sangli','raigad':'raigad','thane':'thane','panchkula':'panchkula','dehradun':'dehradun','sri ganganagar':'ganganagar','sriganganagar':'ganganagar','kutch':'kachchh','kachh':'kachchh','kanpur':'kanpur nagar','anantapur':'ananthapuramu','anantpur':'ananthapuramu','khargaon':'khargone west nimar','khargone':'khargone west nimar','khandwa':'khandwa east nimar','mohindergarh':'mahendragarh','mahendergarh':'mahendragarh','cuttack':'kataka','khurda':'khordha','bhubaneshwar':'khordha','bhubaneswar':'khordha','nellore':'sri potti sriramulu nellore','sant ravidas nagar':'bhadohi','mehsana':'mahesana','noida':'gautam buddha nagar','gautam budh nagar':'gautam buddha nagar','angul':'anugul','agartala':'west tripura','cuddappah':'y s r kadapa','cuddapah':'y s r kadapa','kadapa':'y s r kadapa','balasore':'baleshwar','secunderabad':'hyderabad','vijayawada':'ntr','bardez':'north goa','mangalore':'dakshina kannada','sundergarh':'sundaragada','sundargarh':'sundaragada'}
def norm(s): return re.sub(r'\s+',' ',re.sub(r'[^a-z ]',' ',s.lower().replace('&','and'))).strip()
states={t['name'].lower():t for t in T if t['parent']==0}
bystate=collections.defaultdict(dict)
for t in T:
    if t['parent']: bystate[t['parent']][norm(t['name'])]=t
def norm(s): return re.sub(r'\s+',' ',re.sub(r'[^a-z ]',' ',s.lower().replace('&','and'))).strip()
abc_codes={a['code'].strip().upper() for a in abc if a.get('code')}
out=[];miss=collections.Counter()
for r in d:
    sn=ALIAS_STATE.get(r['state'].strip().lower(),r['state'].strip().lower()); st=states[sn]
    dist=None; raw=norm(r['district'] or '')
    raw=ALIAS_DIST.get(raw,raw)
    names=bystate[st['term_id']]
    if raw in names: dist=names[raw]
    else:
        for cand in [raw, raw.replace(' district',''), raw.split(' ')[0] if raw else '']:
            m=difflib.get_close_matches(cand,list(names),n=1,cutoff=0.84)
            if m: dist=names[m[0]];break
        if not dist:
            # try the address text: any district name that appears in it
            addr=norm(r.get('address',''))
            hits=[n for n in names if len(n)>4 and re.search(r'\b'+re.escape(n)+r'\b',addr)]
            if len(hits)==1: dist=names[hits[0]]
    if not dist: miss[(st['name'],r['district'])]+=1
    nm=r['name']; low=nm.lower()
    types=['animal-welfare-organisation']
    if re.search(r'gau\s?shala|go\s?shala|gow?shala|gaushala|goshala|gosadan|gau sadan|gausewa|gau seva|go seva|gowshala|panjrapole|pinjrapole|panjarapole|pinjarapole|gau raksha|gauraksha|gausala',low): types.append('gaushala')
    if r['code'].strip().upper() in abc_codes: types.append('abc-centre')
    out.append({'code':r['code'].strip(),'name':nm.strip(),'state':st['term_id'],'district':dist['term_id'] if dist else 0,'area':(dist['name'] if dist else (r['district'] or '').strip().title())+', '+st['name'],'types':types,'address':r['address'].strip(),'recognised':r['recognised'],'renewed':r['renewed'],'valid':r['valid']})
json.dump(out,open(S+'/awo_import.json','w'),ensure_ascii=False)
print("rows",len(out),"district matched",sum(1 for o in out if o['district']),"unmatched",sum(miss.values()))
print("gaushala",sum('gaushala' in o['types'] for o in out),"abc",sum('abc-centre' in o['types'] for o in out), "unique codes",len({o['code'] for o in out}))
print(miss.most_common(25))
