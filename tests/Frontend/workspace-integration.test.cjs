const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('public/js/nexo-workspace.js','utf8');
const safe=source.slice(source.indexOf('    function safeUrl('),source.indexOf('    const modules ='));
const find=source.slice(source.indexOf('    function modulePath('),source.indexOf('    function activateAssignedModule('));
const assigned={url:'http://sigtram.test/mod_gene/mod_tram_inter/correo-interno',popup:false};
const context={URL,location:{href:'http://sigtram.test/inicio',origin:'http://sigtram.test'},modules:[assigned,{url:'http://sigtram.test/popup',popup:true}]};
vm.createContext(context);vm.runInContext(safe+find,context);
test('iframe callbacks resolve only assigned destinations on the current origin',()=>{
 assert.equal(context.findAssignedModule(assigned.url),assigned);
 assert.equal(context.findAssignedModule(assigned.url+'?bandeja=entrada'),assigned);
 assert.equal(context.findAssignedModule('https://other.test/mod_gene/mod_tram_inter/correo-interno'),null);
 assert.equal(context.findAssignedModule('/not-assigned'),null);
 assert.equal(context.findAssignedModule('/popup'),null);
 assert.equal(context.findAssignedModule('javascript:alert(1)'),null);
});
test('legacy menu extensions and verified internal route aliases resolve to the assigned entry',()=>{
 assert.equal(context.findAssignedModule('/mod_gene/mod_tram_inter/correo_interno'),assigned);
 assert.equal(context.findAssignedModule('/mod_gene/mod_tram_inter/correo-interno.blade.php'),assigned);
 assert.equal(context.findAssignedModule('/mod_gene/mod_tram_inter/correo-interno.blade.php/'),assigned);
 assert.equal(context.findAssignedModule('/mod_gene/mod_tram_inter/correo_interno?bandeja=salida'),assigned);
 assert.equal(context.findAssignedModule('/mod_gene/mod_tram_inter/correo_interno/recepcionar'),null);
 assert.equal(context.findAssignedModule('/mod_gene/mod_tram_inter/buscar_tramite'),null);
});
test('backend iframe callbacks keep the workspace open and never navigate to an unassigned action',()=>{
 const nodes=new Map();
 const calls=[];
 const context={URL,location:{href:'http://sigtram.test/inicio',origin:'http://sigtram.test'},modules:[{...assigned,label:'Bandeja',group:'Trámites internos'}],
  window:{scrollTo(){}},document:{},explorer:{open:true,close(){this.open=false;}},
  recordAccess:entry=>calls.push(entry),notify(){},
  $:selector=>{if(!nodes.has(selector)) nodes.set(selector,{classList:{remove(){}},removeAttribute(){},focus(){}});return nodes.get(selector);}
 };
 vm.createContext(context);
 const bridge=source.slice(source.indexOf('    function modulePath('),source.indexOf('    document.addEventListener("click",'));
 vm.runInContext(safe+bridge,context);
 const target='http://sigtram.test/mod_gene/mod_tram_inter/correo_interno?bandeja=salida';
 assert.equal(context.window.activarModuloPorUrl(target),true);
 assert.equal(nodes.get('#contentFrame').src,target);
 assert.equal(nodes.get('#dashboardHome').hidden,true);
 assert.equal(nodes.get('#modulePanel').hidden,false);
 assert.equal(calls.length,1);
 assert.equal(context.window.loadContentInPanel('/mod_gene/mod_tram_inter/correo_interno/recepcionar'),false);
 assert.equal(nodes.get('#contentFrame').src,target);
 assert.equal(calls.length,1);
});
