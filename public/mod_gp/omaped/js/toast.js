window.mostrarToast = function(tipo, mensaje, duracion = 2000) {
    console.log("mostrarToast llamado:", tipo, mensaje);
    
    if (!tipo || !mensaje) {
        console.error("mostrarToast: tipo o mensaje faltante", { tipo, mensaje });
        return;
    }
    
    let container = document.getElementById("toastContainer");
    if (!container) {
        console.log("Creando contenedor toast");
        container = document.createElement("div");
        container.id = "toastContainer";
        container.className = "toast-container";
        if (document.body) {
            document.body.appendChild(container);
        } else {
            console.error("document.body no está disponible");
            return;
        }
    }
    
    const toast = document.createElement("div");
    toast.className = `toast toast--${tipo}`;
    
    const icon = document.createElement("span");
    icon.className = "toast__icon";
    icon.textContent = tipo === "success" ? "✓" : "✕";
    
    const message = document.createElement("span");
    message.className = "toast__message";
    message.textContent = mensaje;
    
    toast.appendChild(icon);
    toast.appendChild(message);
    container.appendChild(toast);
    
    console.log("Toast agregado al DOM:", toast);
    
    setTimeout(() => {
        toast.classList.add("toast--fade-out");
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, duracion);
};
