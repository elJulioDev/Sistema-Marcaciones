/* ── Importación de marcaciones (streaming NDJSON) ───────────
   Config inyectada por el servidor (window.IMPORT_CONFIG). */
(function(){
'use strict';

var IMPORT_URL = window.IMPORT_CONFIG.importUrl;

/* ── DOM ──────────────────────────────────────────────────── */
var dropzone   = document.getElementById('dropzone');
var fileInput  = document.getElementById('file-input');
var btnImport  = document.getElementById('btn-import');
var btnImportTxt = document.getElementById('btn-import-text');
var alertErr   = document.getElementById('alert-err');
var alertMsg   = document.getElementById('alert-err-msg');

var uploadCard = document.getElementById('upload-card');
var progCard   = document.getElementById('prog-card');
var resCard    = document.getElementById('res-card');

var progSpinner = document.getElementById('prog-spinner');
var progTitle   = document.getElementById('prog-title');
var progSub     = document.getElementById('prog-sub');
var pbarFill    = document.getElementById('pbar-fill');
var pbarMsg     = document.getElementById('pbar-msg');
var pbarPct     = document.getElementById('pbar-pct');

var selectedFile = null;

/* ── Utilidades ───────────────────────────────────────────── */
function fmtBytes(b){
    if(b<1024) return b+' B';
    if(b<1048576) return (b/1024).toFixed(1)+' KB';
    return (b/1048576).toFixed(2)+' MB';
}
function fmtNum(n){ return parseInt(n).toLocaleString('es-CL'); }

/* Función de Animación de Números (Count-Up) */
function animateValue(id, start, end, duration) {
    var obj = document.getElementById(id);
    if (!obj) return;
    var startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        // Función de suavizado (easeOutQuart) para frenar suavemente al final
        const easeOut = 1 - Math.pow(1 - progress, 4);
        obj.innerHTML = fmtNum(Math.floor(easeOut * (end - start) + start));
        if (progress < 1) {
            window.requestAnimationFrame(step);
        } else {
            obj.innerHTML = fmtNum(end); // Asegurar el número final exacto
        }
    };
    window.requestAnimationFrame(step);
}

/* ── Alertas ──────────────────────────────────────────────── */
function showAlert(msg){ alertMsg.textContent=msg; alertErr.classList.add('show'); }
function hideAlert(){ alertErr.classList.remove('show'); }

/* ── Dropzone ─────────────────────────────────────────────── */
function setFile(file){
    if(!file) return;
    var ext = file.name.split('.').pop().toLowerCase();
    if(ext!=='txt' && ext!=='csv'){
        showAlert('Solo se permiten archivos .txt o .csv'); return;
    }
    hideAlert();
    selectedFile = file;
    dropzone.classList.add('has-file');
    document.getElementById('dz-title').textContent = file.name;
    document.getElementById('dz-sub').textContent   = 'Archivo listo para importar';
    document.getElementById('dz-chips').style.display = 'flex';
    document.getElementById('dz-chip-name').textContent = '📄 '+file.name;
    document.getElementById('dz-chip-size').textContent = '💾 '+fmtBytes(file.size);
    document.getElementById('dz-svg').innerHTML =
        '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>';
    btnImport.disabled = false;
    btnImportTxt.textContent = 'Importar  ' + file.name;
}

function resetDropzone(){
    selectedFile = null;
    fileInput.value = '';
    dropzone.classList.remove('has-file');
    document.getElementById('dz-title').textContent = 'Arrastra tu archivo aquí';
    document.getElementById('dz-sub').textContent   = 'o haz clic para seleccionar · .TXT o .CSV';
    document.getElementById('dz-chips').style.display = 'none';
    document.getElementById('dz-svg').innerHTML =
        '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>';
    btnImport.disabled = true;
    btnImportTxt.textContent = 'Selecciona un archivo para continuar';
}

fileInput.addEventListener('change', function(){
    if(this.files && this.files[0]) setFile(this.files[0]);
});
dropzone.addEventListener('dragover', function(e){
    e.preventDefault(); this.classList.add('drag-over');
});
dropzone.addEventListener('dragleave', function(){
    this.classList.remove('drag-over');
});
dropzone.addEventListener('drop', function(e){
    e.preventDefault(); this.classList.remove('drag-over');
    if(e.dataTransfer.files && e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]);
});

/* ── Steps ────────────────────────────────────────────────── */
function setStep(n, state, badge){
    var el  = document.getElementById('step-'+n);
    var num = el.querySelector('.step-num');
    var bdg = document.getElementById('badge-'+n);
    el.className = 'step'+(state?' '+state:'');
    if(state==='done')     num.innerHTML = '&#10003;';
    else if(state==='err') num.innerHTML = '&#10007;';
    else                   num.textContent = num.dataset.n;
    if(badge && bdg){ bdg.textContent = badge; bdg.style.display='inline'; }
}

/* ── Progress bar ─────────────────────────────────────────── */
function setProg(pct, msg, state){
    pct = Math.max(0, Math.min(100, pct));
    pbarFill.style.width = pct+'%';
    pbarPct.textContent  = pct+' %';
    if(msg) pbarMsg.textContent = msg;
    if(state==='done'){ pbarFill.className='pbar-fill done'; }
    else if(state==='err'){ pbarFill.className='pbar-fill err'; }
    else { pbarFill.className='pbar-fill'; }
}

function resetProgress(){
    setStep(1,''); setStep(2,''); setStep(3,'');
    setProg(0,'Iniciando...');
    progSpinner.className = 'prog-spinner';
    progSpinner.innerHTML = '';
    progTitle.textContent = 'Procesando importación...';
    progSub.textContent   = 'Esto puede tardar algunos segundos.';
    document.getElementById('badge-1').style.display = 'none';
    document.getElementById('badge-2').style.display = 'none';
    document.getElementById('badge-3').style.display = 'none';
}

/* ── Event handler ────────────────────────────────────────── */
function handleEvent(ev){
    switch(ev.phase){

        case 'parsing':
            setStep(1,'active');
            setProg(ev.progress, ev.message||'Leyendo archivo...');
            break;

        case 'parsed':
            setStep(1,'done', fmtNum(ev.validos)+' válidos · '+fmtNum(ev.invalidos)+' inv.');
            setStep(2,'active');
            setProg(ev.progress, ev.message||'Preparando inserción...');
            progSub.textContent = fmtNum(ev.validos)+' registros válidos para insertar.';
            break;

        case 'dedup': 
            setStep(2,'active');
            setProg(ev.progress, ev.message||'Consultando duplicados...');
            if(ev.predup > 0){
                progSub.textContent = fmtNum(ev.predup) + ' duplicados detectados y omitidos antes de insertar.';
            } else if(ev.nuevos !== undefined){
                progSub.textContent = fmtNum(ev.nuevos) + ' registros nuevos listos para insertar en BD.';
            }
            break;

        case 'inserting':
            setStep(2,'active');
            var info = 'Lote '+ev.batch+' de '+ev.totalBatches+
                       ' — '+fmtNum(ev.inserted)+' insertados, '+fmtNum(ev.duplicated)+' duplicados';
            if(ev.rps && ev.rps > 0) info += ' · ' + fmtNum(ev.rps) + ' reg/s';
            setProg(ev.progress, info);
            
            document.getElementById('badge-2').textContent =
                fmtNum(ev.inserted)+' ins. · '+fmtNum(ev.duplicated)+' dup.';
            document.getElementById('badge-2').style.display = 'inline';
            progSub.textContent = 'Lote '+ev.batch+'/'+ev.totalBatches+
                ' — '+fmtNum(ev.inserted)+' registros nuevos en BD.';
            break;

        case 'resumen':
            setStep(2,'done');
            setStep(3,'active');
            setProg(ev.progress, ev.message||'Calculando resúmenes...');
            if(ev.pairs){
                document.getElementById('badge-3').textContent = ev.pairs+' pares';
                document.getElementById('badge-3').style.display = 'inline';
            }
            progSub.textContent = 'Actualizando tabla de resúmenes de asistencia...';
            break;

        case 'done':
            setStep(3,'done');
            setProg(100,'¡Importación completada exitosamente!','done');
            progTitle.textContent = '¡Completado!';
            progSub.textContent   = 'Todos los datos fueron procesados.';
            progSpinner.className = 'prog-spinner done';
            progSpinner.innerHTML =
                '<svg width="18" height="18" fill="none" stroke="var(--grn)" stroke-width="3"'+
                ' stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">'+
                '<polyline points="20 6 9 17 4 12"/></svg>';
            setTimeout(function(){ showResults(ev.result); }, 700);
            break;

        case 'error':
            setProg(pbarFill.style.width ? parseInt(pbarFill.style.width) : 0,
                    'Error en la importación.', 'err');
            progTitle.textContent = 'Error al importar';
            progSub.textContent   = ev.message||'Ocurrió un error inesperado.';
            progSpinner.className = 'prog-spinner err';
            progSpinner.innerHTML =
                '<svg width="18" height="18" fill="none" stroke="var(--red)" stroke-width="3"'+
                ' stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">'+
                '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
            setTimeout(function(){
                progCard.classList.remove('show');
                uploadCard.style.display = '';
                showAlert(ev.message||'Error desconocido al importar.');
            }, 2000);
            break;
    }
}

/* ── Results (Con Animación) ──────────────────────────────── */
function showResults(r){
    progCard.classList.remove('show');
    var grid = document.getElementById('res-grid');
    
    // Función de ayuda que ahora asigna IDs únicos para poder animarlos
    function stat(lbl, cls, id){
        return '<div class="res-stat '+cls+'">'+
               '<div class="res-val" id="count-'+id+'">0</div>'+
               '<div class="res-lbl">'+lbl+'</div></div>';
    }
    
    grid.innerHTML =
        stat('Líneas leídas',          'slt', 'leidos') +
        stat('Nuevos registros',       'grn', 'insertados') +
        stat('Duplicados omitidos',    r.duplicados>0?'amb':'slt', 'duplicados') +
        stat('Líneas inválidas',       r.invalidos>0?'red':'slt', 'invalidos') +
        stat('Resúmenes recalculados', 'blu', 'resumen');
        
    resCard.classList.add('show');
    
    // Disparamos las animaciones (1200ms de duración)
    animateValue('count-leidos', 0, r.leidos, 1200);
    animateValue('count-insertados', 0, r.insertados, 1200);
    animateValue('count-duplicados', 0, r.duplicados, 1200);
    animateValue('count-invalidos', 0, r.invalidos, 1200);
    animateValue('count-resumen', 0, r.resumen, 1200);
}

/* ── Import trigger ───────────────────────────────────────── */
btnImport.addEventListener('click', function(){
    if(!selectedFile || btnImport.disabled) return;
    hideAlert();

    var fd = new FormData();
    fd.append('archivo',    selectedFile);
    fd.append('periodo',    document.getElementById('periodo').value);
    fd.append('observacion',document.getElementById('observacion').value);

    // Mostrar progreso, ocultar formulario
    uploadCard.style.display = 'none';
    resCard.classList.remove('show');
    progCard.classList.add('show');
    resetProgress();

    // Fetch con streaming NDJSON
    fetch(IMPORT_URL, {method:'POST', body:fd, credentials:'same-origin'})
    .then(function(response){
        if(!response.ok) throw new Error('HTTP '+response.status);

        // Fallback para navegadores sin ReadableStream
        if(!response.body){
            return response.text().then(function(text){
                text.split('\n').forEach(function(line){
                    if(line.trim()){ try{ handleEvent(JSON.parse(line)); }catch(e){} }
                });
            });
        }

        // Lectura en streaming
        var reader  = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer  = '';

        function read(){
            return reader.read().then(function(chunk){
                if(chunk.done){
                    if(buffer.trim()){ try{ handleEvent(JSON.parse(buffer)); }catch(e){} }
                    return;
                }
                buffer += decoder.decode(chunk.value, {stream:true});
                var lines = buffer.split('\n');
                buffer = lines.pop();
                lines.forEach(function(line){
                    if(line.trim()){ try{ handleEvent(JSON.parse(line)); }catch(e){} }
                });
                return read();
            });
        }
        return read();
    })
    .catch(function(err){
        handleEvent({phase:'error', message:'Error de conexión: '+err.message});
    });
});

/* ── Nueva importación ────────────────────────────────────── */
document.getElementById('btn-again').addEventListener('click', function(){
    resCard.classList.remove('show');
    resetDropzone();
    hideAlert();
    uploadCard.style.display = '';
});

}());
