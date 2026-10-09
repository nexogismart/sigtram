const toTitleCase = (str) => {
    if (!str) return "";
    return str.replace(/\w\S*/g, (txt) => {
        return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
    });
};

const LAYOUT = {
    canvas: { w: 484, h: 153 },
    colors: {
        white: "#ffffff",
        black: "#131414",
        red: "#e60000"
    },
    posX: 135,
    iconSize: 14,
    yMap: {
        nombre: 35,
        cargo: 51,
        municipio: 69,
        tel: 83,
        mail: 101,
        web: 119
    }
};

const UI = {
    canvas: document.getElementById('firma-canvas'),
    ctx: null,
    resources: {
        logo: new Image(),
        tel: new Image(),
        mail: new Image(),
        web: new Image()
    },

    init: function() {
        this.ctx = this.canvas.getContext('2d');
        const base = (window.FIRMA_EMAIL?.assetBase || '').replace(/\/$/, '');
        this.resources.logo.src = base + '/images/logo.png';
        this.resources.tel.src = base + '/images/icono/telefono.png';
        this.resources.mail.src = base + '/images/icono/correo-electronico.png';
        this.resources.web.src = base + '/images/icono/web.png';

        this.createLoader();
        this.setupDragAndDrop();

        Object.values(this.resources).forEach(img => {
            img.onload = () => this.updatePreview(this.getLastData());
        });
    },

    setupDragAndDrop: function() {
        const dropzone = document.querySelector('.massive-dropzone');
        if (!dropzone) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
        });

        dropzone.addEventListener('drop', e => {
            const dt = e.dataTransfer;
            const file = dt.files[0];
            if (file && file.name.toLowerCase().endsWith('.csv')) {
                procesarMasivo(file);
            } else {
                alert('Por favor, suelta un archivo CSV válido.');
            }
        }, false);
    },

    getLastData: function() {
        return {
            titulo: document.getElementById('titulo')?.value,
            nombre: document.getElementById('nombre')?.value,
            cargo: document.getElementById('cargo')?.value,
            correo: document.getElementById('correo')?.value,
            celular: document.getElementById('celular')?.value,
            anexo: document.getElementById('anexo')?.value
        };
    },

    fillTextWrapped: function(text, x, y, maxWidth, lineHeight) {
        const words = text.split(' ');
        let line = '';
        let linesRendered = 0;

        for (let n = 0; n < words.length; n++) {
            let testLine = line + words[n] + ' ';
            let metrics = this.ctx.measureText(testLine);
            let testWidth = metrics.width;

            if (testWidth > maxWidth && n > 0) {
                this.ctx.fillText(line.trim(), x, y + (linesRendered * lineHeight));
                line = words[n] + ' ';
                linesRendered++;
            } else {
                line = testLine;
            }
        }
        this.ctx.fillText(line.trim(), x, y + (linesRendered * lineHeight));
        return linesRendered + 1;
    },

    updatePreview: function(data = {}) {
        if (!this.ctx) return;

        const { colors, yMap, posX, iconSize } = LAYOUT;
        const maxWidth = LAYOUT.canvas.w - posX - 15;

        this.ctx.fillStyle = colors.white;
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        this.ctx.drawImage(this.resources.logo, 2, 14, 125, 125);
        this.ctx.strokeStyle = colors.red;
        this.ctx.lineWidth = 2;
        this.ctx.beginPath();
        this.ctx.moveTo(122, 17); this.ctx.lineTo(122, 136);
        this.ctx.stroke();

        const fullNombre = (data.titulo ? toTitleCase(data.titulo) + ' ' : '') + toTitleCase(data.nombre || '');

        this.ctx.fillStyle = colors.black;
        this.ctx.font = "bold 15px Roboto, sans-serif";
        this.ctx.fillText(fullNombre, posX, yMap.nombre);

        this.ctx.font = "13px Arial, sans-serif";
        const totalLinesCargo = this.fillTextWrapped(toTitleCase(data.cargo || ''), posX, yMap.cargo, maxWidth, 14);

        const cargoExtraHeight = (totalLinesCargo - 1) * 14;

        this.ctx.fillStyle = colors.red;
        this.ctx.font = "bold 13px Roboto, sans-serif";
        const yMunicipio = yMap.municipio + cargoExtraHeight;
        this.ctx.fillText("Municipalidad de Puente Piedra", posX, yMunicipio);

        this.ctx.fillStyle = colors.black;
        this.ctx.font = "12px Arial, sans-serif";

        const telText = `(01) 219-6200${data.anexo ? ' Anexo: ' + data.anexo : ''}${data.celular ? ' | ' + data.celular : ''}`;

        const yOffset = cargoExtraHeight;
        const contactos = [
            { img: this.resources.tel, y: yMap.tel + yOffset, txt: telText },
            { img: this.resources.mail, y: yMap.mail + yOffset, txt: data.correo || '' },
            { img: this.resources.web, y: yMap.web + yOffset, txt: "web.munipuentepiedra.gob.pe" }
        ];

        contactos.forEach(c => {
            this.ctx.drawImage(c.img, posX, c.y, iconSize, iconSize);
            this.ctx.fillText(c.txt, 155, c.y + 12);
        });
    },

    setTab: function(tabId, btn) {
        document.querySelectorAll('.tab-content, .tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        if (btn) btn.classList.add('active');
    },

    createLoader: function() {
        if (!document.getElementById('ui-loader')) {
            const loader = document.createElement('div');
            loader.id = 'ui-loader';
            loader.className = 'loader-overlay';
            loader.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(loader);
        }
    },

    showLoading: function(show) {
        const loader = document.getElementById('ui-loader');
        if (loader) {
            if (show) loader.classList.add('visible');
            else loader.classList.remove('visible');
        }
    }
};
