const API = {
    _headers() {
        const token = window.FIRMA_EMAIL?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
        return token ? { 'X-CSRF-TOKEN': token, Accept: 'application/json' } : { Accept: 'application/json' };
    },

    _url(key) {
        return window.FIRMA_EMAIL?.routes?.[key] || '';
    },

    buscarPorDni: async (dni) => {
        const formData = new FormData();
        formData.append('dni', dni);

        const response = await fetch(API._url('buscar_dni'), {
            method: 'POST',
            headers: API._headers(),
            body: formData,
            credentials: 'same-origin',
        });
        return await response.json();
    },

    generarIndividual: async (data) => {
        const formData = new FormData();
        for (const key in data) {
            formData.append(key, data[key]);
        }

        const response = await fetch(API._url('generar_individual'), {
            method: 'POST',
            headers: API._headers(),
            body: formData,
            credentials: 'same-origin',
        });
        return await response.json();
    },

    generarMasivo: async (file) => {
        const formData = new FormData();
        formData.append('csv', file);

        const response = await fetch(API._url('generar_masivo'), {
            method: 'POST',
            headers: API._headers(),
            body: formData,
            credentials: 'same-origin',
        });
        return await response.json();
    },
};
