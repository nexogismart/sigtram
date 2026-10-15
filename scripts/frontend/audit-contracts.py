from pathlib import Path
import json,re,hashlib,sys
base=Path(__file__).resolve().parents[2]
contracts=json.loads((base/'docs/frontend/contratos-vistas-originales.json').read_text(encoding='utf-8'))
results=[]
def fields(tags):
 out=[]
 for tag in tags:
  tag=re.sub(r'\s+(?:class|style|placeholder)\s*=\s*(?:"[^"]*"|\x27[^\x27]*\x27)','',tag)
  out.append(re.sub(r'\s+',' ',tag).strip())
 return sorted(out)
for key,old in contracts.items():
 if key=='welcome':continue
 p=base/'resources/views'/Path(key.replace('.','/')+'.blade.php')
 if not p.exists():raise RuntimeError(str(p))
 s=p.read_text(encoding='utf-8-sig')
 scripts=[hashlib.sha256(t.encode()).hexdigest() for t in re.findall(r'<script\b[^>]*>.*?</script>',s,re.S|re.I)]
 f=re.findall(r'<(?:form|input|select|textarea)\b[^>]*',s,re.S|re.I)
 events=re.findall(r'(?:\b(?:onclick|onchange|onsubmit|onkeydown|onkeyup)|@[\w.:-]+|x-(?:data|model|init|on:[\w.:-]+))\s*=\s*(?:"[^"]*"|\x27[^\x27]*\x27)',s)
 row={'view':key,'scripts_unchanged':scripts==old['scripts'],'forms_unchanged':fields(f)==fields(old['form_contracts']),'events_unchanged':sorted(events)==sorted(old['events'])}
 results.append(row)
report={'views':len(results),'results':results}
(base/'docs/frontend/auditoria-contratos.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
fail=[x for x in results if not all(x[k] for k in ('scripts_unchanged','forms_unchanged','events_unchanged'))]
print(json.dumps({'checked':len(results),'failures':fail},ensure_ascii=False,indent=2))
sys.exit(bool(fail))
