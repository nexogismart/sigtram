const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('public/js/nexo-workspace.js','utf8');
const avatarScript=source.slice(source.lastIndexOf('(() => {'));
function avatar(complete=false,naturalWidth=0) {
 const fallback={hidden:false},events={};
 const image={complete,naturalWidth,hidden:true,parentElement:{querySelector:()=>fallback},addEventListener:(name,fn)=>events[name]=fn};
 vm.runInNewContext(avatarScript,{document:{querySelectorAll:()=>[image]}});
 return {image,fallback,events};
}
test('initial remains visible while the portrait loads, then yields to the photo',()=>{
 const {image,fallback,events}=avatar();
 assert.equal(fallback.hidden,false);assert.equal(image.hidden,true);
 image.complete=true;image.naturalWidth=120;events.load();
 assert.equal(fallback.hidden,true);assert.equal(image.hidden,false);
});
test('failed portrait preserves the initial',()=>{
 const {image,fallback,events}=avatar();
 image.complete=true;events.error();
 assert.equal(fallback.hidden,false);assert.equal(image.hidden,true);
});
test('an image already loaded before deferred script execution is shown',()=>{
 const {image,fallback}=avatar(true,120);
 assert.equal(fallback.hidden,true);assert.equal(image.hidden,false);
});
