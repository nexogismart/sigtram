const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = {module:{exports:{}}};
vm.createContext(context);
vm.runInContext(fs.readFileSync('public/js/sigtram-helpdesk-writer.js','utf8'), context);
const {hasDescription} = context.module.exports;

test('empty or guide-only descriptions cannot be submitted', () => {
    assert.equal(hasDescription('  \n\u00a0\u200b'), false);
    assert.equal(hasDescription('Qué ocurreDónde y desde cuándoCómo afecta tu trabajo'), false);
    assert.equal(hasDescription('Qué cambio necesitasDónde se debe aplicarQué resultado esperas'), false);
    assert.equal(hasDescription('Qué necesitas saberEn qué tarea estásQué revisaste'), false);
    assert.equal(hasDescription('Qué ocurre: ...'), false);
});
test('free text and completed guides are valid, including accented Spanish', () => {
    assert.equal(hasDescription('No puedo adjuntar un documento en SIGTRAM.'), true);
    assert.equal(hasDescription('Qué ocurreLa impresora muestra un error.Dónde y desde cuándoLogística.Cómo afecta tu trabajoNo puedo imprimir.'), true);
});
