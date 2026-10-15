const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('public/js/nexo-workspace.js','utf8');
const safe=source.slice(source.indexOf('    function safeUrl('),source.indexOf('    const modules ='));
const find=source.slice(source.indexOf('    function findAssignedModule('),source.indexOf('    function activateAssignedModule('));
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
