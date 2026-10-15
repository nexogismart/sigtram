const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('public/js/nexo-workspace.js','utf8');
const normalize=source.slice(source.indexOf('    const normalize ='),source.indexOf('    function safeUrl'));
const functions=source.slice(source.indexOf('    function isHelpdesk('),source.indexOf('    function readStorage'));
const context={URL,data:{context:{area:'OFICINA DE TECNOLOGÍAS DE INFORMACIÓN'}}};
vm.createContext(context);vm.runInContext(normalize+functions,context);
const entry=(values={})=>({sourceId:194,sourceGroup:'OTI',url:'https://sigtram.test/firma',owners:['Oficina de Tecnologías de Información'],sharedWith:[],...values});
test('all helpdesk actions move to the header even for their managing area',()=>{
 for(const sourceId of [134,161]) assert.equal(context.scopeFor(entry({sourceId})),'helpdesk');
 assert.equal(context.scopeFor(entry({sourceId:0,sourceGroup:'MESA DE AYUDA'})),'helpdesk');
 assert.equal(context.scopeFor(entry({sourceId:0,url:'https://sigtram.test/mod_ti/helpdesk/pantalla.blade.php'})),'helpdesk');
});
test('real area aliases classify providers and risk management for every user context',()=>{
 const previousArea=context.data.context.area;
 context.data.areaAliases={
  'oficina de finanzas':'Finanzas',
  'oficina general de administracion y finanzas':'OGAF',
  'gerencia de gestion del riesgo de desastres':'Gerencia de Gestión de Riesgos y Desastres',
 };
 for(const area of ['OFICINA DE FINANZAS','OFICINA GENERAL DE ADMINISTRACIÓN Y FINANZAS']) {
  context.data.context.area=area;
  assert.equal(context.scopeFor(entry({owners:['Finanzas','OGAF'],sharedWith:['Todas las áreas orgánicas']})),'area');
 }
 context.data.context.area='GERENCIA DE GESTION DEL RIESGO DE DESASTRES';
 assert.equal(context.scopeFor(entry({owners:['Gerencia de Gestión de Riesgos y Desastres']})),'area');
 context.data.context.area='NO SIRVE OFICINA DE ATENCIÓN AL CIUDADANO';
 assert.equal(context.scopeFor(entry({owners:['Oficina de Atención al Ciudadano']})),'assigned');
 context.data.context.area=previousArea;delete context.data.areaAliases;
});
test('helpdesk does not leak into any of the three central directories',()=>{
 const list=[entry({sourceId:134}),entry({sourceId:134,sharedWith:['Todas las áreas orgánicas']}),entry()];
 assert.equal(list.filter(e=>context.scopeFor(e)==='area').length,1);
 assert.equal(list.filter(e=>context.scopeFor(e)==='shared').length,0);
 assert.equal(list.filter(e=>context.scopeFor(e)==='assigned').length,0);
 assert.equal(list.filter(context.isHelpdesk).length,2);
});
test('unrelated OTI and transversal services preserve their scope',()=>{
 assert.equal(context.scopeFor(entry()),'area');
 assert.equal(context.scopeFor(entry({sourceId:1,owners:['Todas las áreas orgánicas']})),'shared');
 assert.equal(context.scopeFor(entry({sourceId:22,owners:['Subgerencia de Autorizaciones Municipales']})),'assigned');
});
test('a single received helpdesk action remains a single action',()=>{
 const allowed=[entry({sourceId:134,url:'https://sigtram.test/mod_ti/helpdesk/index.blade.php'})];
 assert.equal(allowed.filter(context.isHelpdesk).length,1);
 assert.equal([].filter(context.isHelpdesk).length,0);
});
test('module grouping preserves distinct source menus even when their consolidated service matches',()=>{
 const entries=[entry({sourceId:99,sourceGroup:'Acc. Información',group:'Información pública',label:'Solicitud'}),entry({sourceId:176,sourceGroup:'Acceso a la Información',group:'Información pública',label:'Bandeja'}),entry({sourceId:99,sourceGroup:'Acc. Información',group:'Información pública',label:'Consulta'})];
 const groups=context.groupModuleEntries(entries);
 assert.equal(groups.length,2);
 assert.equal(groups[0].label,'Acc. Información');
 assert.equal(groups[0].entries.length,2);
 assert.equal(groups[1].entries.length,1);
 assert.strictEqual(groups[0].entries[1],entries[2]);
 assert.equal(groups.flatMap(g=>g.entries).length,entries.length);
});
test('recent dates use Lima calendar days for Today and Yesterday',()=>{
 const now=Date.parse('2026-10-13T02:00:00Z');
 const today=context.recentDateGroup(Date.parse('2026-10-12T23:00:00Z'),now);
 assert.equal(today.key,'2026-10-12');assert.equal(today.label,'Hoy');
 const yesterday=context.recentDateGroup(Date.parse('2026-10-12T03:00:00Z'),now);
 assert.equal(yesterday.key,'2026-10-11');assert.equal(yesterday.label,'Ayer');
 const previous=context.recentDateGroup(Date.parse('2026-10-10T20:00:00Z'),now);
 assert.equal(previous.key,'2026-10-10');assert.match(previous.label,/2026/);
});
