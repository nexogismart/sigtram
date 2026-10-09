<!-- Vista del formulario ANEXO 2 -->
<form id="reporteForm">
    <input type="hidden" id="cdgo_reporte" name="cdgo_reporte">
    <input type="hidden" id="modalMode" name="modalMode" value="nuevo">
    
    <!-- SECCIONES 1-6 EN 2 COLUMNAS -->
    <div class="row">
        <!-- COLUMNA IZQUIERDA (1, 2, 3) -->
        <div class="col-md-6">
            <div class="mb-2">
                <label class="form-label mb-1" style="font-size: 0.85rem;"><strong>1. NOMBRE DE LA ENTIDAD</strong></label>
                <input type="text" class="form-control form-control-sm" value="DISTRITO DE PUENTE PIEDRA" readonly style="background-color: #e9ecef;">
            </div>
            
            <div class="mb-2">
                <label class="form-label mb-1" style="font-size: 0.85rem;"><strong>2. NOMBRE DEL TITULAR DEL PLIEGO</strong></label>
                <input type="text" class="form-control form-control-sm" value="RENNÁN ESPINOZA ROSALES" readonly style="background-color: #e9ecef;">
            </div>
            
            <div class="mb-2">
                <label class="form-label mb-1" style="font-size: 0.85rem;"><strong>3. DOMICILIO DE LA ENTIDAD</strong></label>
                <input type="text" class="form-control form-control-sm" value="CALLE 9 DE JUNIO N°100" readonly style="background-color: #e9ecef;">
            </div>
        </div>
        
        <!-- COLUMNA DERECHA (4, 5, 6) -->
        <div class="col-md-6">
            <div class="mb-2">
                <label class="form-label mb-1" style="font-size: 0.85rem;"><strong>4. CASILLA ELECTRÓNICA</strong></label>
                <input type="text" class="form-control form-control-sm" value="07523215" readonly style="background-color: #e9ecef;">
            </div>
            
            <div class="mb-2">
                <label for="ubicacion" class="form-label mb-1" style="font-size: 0.85rem;"><strong>5. DIRECCIÓN O UBICACIÓN DE LA PUBLICIDAD</strong> *</label>
                <textarea class="form-control form-control-sm" id="ubicacion" name="ubicacion" rows="2" style="resize: vertical; white-space: pre-wrap; word-wrap: break-word;"></textarea>
                <small class="text-muted" style="font-size: 0.75rem;">(*) En caso que el medio empleado tenga ubicación física</small>
            </div>
            
            <div class="mb-2">
                <label class="form-label mb-1" style="font-size: 0.85rem;"><strong>6. PERIODO DE LA DIFUSIÓN</strong></label>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="d-flex align-items-center gap-1">
                        <label class="form-label mb-0 me-1" style="font-size: 0.75rem; font-weight: 600;">DEL:</label>
                        <input type="number" class="form-control form-control-sm" id="periodo_difusion_ini_dia" name="periodo_difusion_ini_dia" min="1" max="31" placeholder="Día" style="max-width: 60px;">
                        <span>/</span>
                        <input type="number" class="form-control form-control-sm" id="periodo_difusion_ini_mes" name="periodo_difusion_ini_mes" min="1" max="12" placeholder="Mes" style="max-width: 60px;">
                        <span>/</span>
                        <input type="number" class="form-control form-control-sm" id="periodo_difusion_ini_anio" name="periodo_difusion_ini_anio" min="2020" max="2100" placeholder="Año" style="max-width: 80px;">
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <label class="form-label mb-0 me-1" style="font-size: 0.75rem; font-weight: 600;">AL:</label>
                        <input type="number" class="form-control form-control-sm" id="periodo_difusion_fin_dia" name="periodo_difusion_fin_dia" min="1" max="31" placeholder="Día" style="max-width: 60px;">
                        <span>/</span>
                        <input type="number" class="form-control form-control-sm" id="periodo_difusion_fin_mes" name="periodo_difusion_fin_mes" min="1" max="12" placeholder="Mes" style="max-width: 60px;">
                        <span>/</span>
                        <input type="number" class="form-control form-control-sm" id="periodo_difusion_fin_anio" name="periodo_difusion_fin_anio" min="2020" max="2100" placeholder="Año" style="max-width: 80px;">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SECCIÓN 7 - OCUPA LAS 2 COLUMNAS COMPLETAS -->
    <div class="row mt-2">
        <div class="col-md-12">
            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0" style="font-size: 0.85rem;"><strong>7. INFORMACIÓN DE LA PUBLICIDAD</strong></label>
                    <button type="button" class="btn btn-success btn-sm rounded-pill px-2 py-1" id="btnAddDetalle">
                        <i class="fas fa-plus me-1"></i> Agregar
                    </button>
                </div>
                <small class="text-muted" style="font-size: 0.75rem;">Cuadros de Información</small>
                <div id="detallesContainer" class="mt-2">
                    <!-- Los detalles se agregarán dinámicamente -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- RESTO DE CAMPOS EN 1 COLUMNA -->
    <div class="row mt-2">
        <div class="col-md-12">
            <div class="mb-2">
                <label class="form-label mb-1" style="font-size: 0.85rem;"><strong>8. ANEXO FOTOGRÁFICO</strong></label>
                <input type="file" class="form-control form-control-sm" id="imagenesReporte" name="imagenes[]" multiple accept="image/*" max="6">
                <small class="text-muted" style="font-size: 0.75rem;">Formatos: JPG, JPEG, PNG. Máximo 6 imágenes.</small>
                <div id="previewImagenes" class="mt-2 d-flex flex-wrap gap-2"></div>
            </div>
            
            <div class="mb-2">
                <label for="enlace_link" class="form-label mb-1" style="font-size: 0.85rem;"><strong>Enlace o link donde se ubica el elemento publicitario:</strong></label>
                <input type="url" class="form-control form-control-sm" id="enlace_link" name="enlace_link">
            </div>
            
            <div class="mb-2">
                <label for="fundamento" class="form-label mb-1" style="font-size: 0.85rem;"><strong>FUNDAMENTO DE IMPOSTERGABLE NECESIDAD O UTILIDAD PÚBLICA</strong></label>
                <textarea class="form-control form-control-sm" id="fundamento" name="fundamento" rows="4"></textarea>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
        <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" id="btnCancelModal">
            Cancelar
        </button>
        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">
            Guardar
        </button>
    </div>
</form>
