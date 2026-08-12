/* ── Calendario: estado de marcaciones (día/semana/mes) ────────────
   Seed y configuración inyectados por el servidor (window.D0 / window.CAL_CONFIG). */
var D0 = window.D0;
var BASE_APP  = window.CAL_CONFIG.baseApp;
var BASE_CAL  = window.CAL_CONFIG.base;
var EDITAR_BASE = window.CAL_CONFIG.editarBase;

var S  = {
    mes: D0.mes, fecha: D0.fechaSel, modo: D0.modo,
    dpto: D0.filtros.dpto, estado: D0.filtros.estado, q: D0.filtros.q,
    ausencias: false,
    pagina: 1 /* NUEVO: Estado de paginación para el modo Mes */
};
var cur = null;

/* ── DOM ──────────────────────────────────────────────────── */
var elTitle  = document.getElementById('month-title');
var elCal    = document.getElementById('cal');
var elStats  = document.getElementById('stats');
var elCard   = document.getElementById('dcard');
var elBtnD   = document.getElementById('btn-dia');
var elBtnSem = document.getElementById('btn-semana');
var elBtnMes = document.getElementById('btn-mes');
var elDpto   = document.getElementById('f-dpto');
var elEstado = document.getElementById('f-estado');
var elQ      = document.getElementById('f-q');

/* ── Helpers ──────────────────────────────────────────────── */
function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function t5(s){ return (s && String(s).length>=5) ? String(s).substring(0,5) : (s||'—'); }
function badgeCls(e){ return e==='OK'?'bok':e==='OBSERVADO'?'bobs':e==='INCOMPLETO'?'binc':'berr'; }
function cellCls(e){  return e==='OK'?'mok':e==='OBSERVADO'?'mob':e==='INCOMPLETO'?'mic':'mer'; }
function loading(on){ elCard.classList.toggle('loading',on); }

var MESES_CORTOS=['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
var MESES_FULL=['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function formatFechaCorta(fechaStr){
    var p=fechaStr.split('-');
    return parseInt(p[2])+' '+MESES_CORTOS[parseInt(p[1])-1];
}
function formatFechaLarga(fechaStr){
    var p=fechaStr.split('-');
    var dias=['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    var d=new Date(fechaStr+'T12:00:00');
    return dias[d.getDay()]+' '+parseInt(p[2])+' '+MESES_CORTOS[parseInt(p[1])-1]+' '+p[0];
}

function url(mes,fecha,modo,dpto,estado,q){
    var u=BASE_CAL+'?mes='+mes+'&fecha='+fecha+'&modo='+modo;
    if(dpto)   u+='&dpto='+encodeURIComponent(dpto);
    if(estado) u+='&estado='+encodeURIComponent(estado);
    if(q)      u+='&q='+encodeURIComponent(q);
    return u;
}

/* ── Export Modal ─────────────────────────────────────────── */
var _exp = { period: 'dia', fecha: '', mes: '', soloFaltas: false };

function openExportModal(){
    _exp.period = S.modo === 'semana' ? 'semana' : S.modo === 'mes' ? 'mes' : 'dia';
    _exp.fecha  = S.fecha;
    _exp.mes    = S.mes;
    _exp.soloFaltas = false;

    document.getElementById('exp-faltas-toggle').checked = false;
    document.getElementById('export-modal').classList.add('open');

    document.querySelectorAll('.exp-period-btn').forEach(function(b){
        b.classList.toggle('active', b.dataset.period === _exp.period);
    });

    renderExpPicker();
    renderExpPreview();
}

function closeExportModal(){
    document.getElementById('export-modal').classList.remove('open');
}

/* ── Period picker ────────────────────────────────────────── */
function renderExpPicker(){
    var el = document.getElementById('exp-picker-controls');
    var p = _exp.period;

    if(p === 'dia'){
        el.innerHTML = '<input type="date" id="exp-date-input" class="exp-date-input" value="' + _exp.fecha + '">';
        document.getElementById('exp-date-input').addEventListener('change', function(e){
            _exp.fecha = e.target.value;
            var partes = _exp.fecha.split('-');
            _exp.mes = partes[0] + '-' + partes[1];
            renderExpPreview();
        });
    } else if(p === 'semana'){
        var info = getWeekInfo(0);
        _exp.fecha = info.lunes;
        el.innerHTML =
            '<div class="exp-week-nav">' +
                '<button class="ib exp-week-btn" id="exp-week-prev">&#8249;</button>' +
                '<div class="exp-week-info">' +
                    '<span class="exp-week-num">Semana ' + info.num + '</span>' +
                    '<span class="exp-week-range">' + formatFechaLarga(info.lunes) + '  &rarr;  ' + formatFechaLarga(info.domingo) + '</span>' +
                '</div>' +
                '<button class="ib exp-week-btn" id="exp-week-next">&#8250;</button>' +
            '</div>';
        document.getElementById('exp-week-prev').addEventListener('click', function(){
            _exp.fecha = getWeekInfo(-1, _exp.fecha).lunes;
            _exp.mes = _exp.fecha.substring(0, 7);
            renderExpPicker();
            renderExpPreview();
        });
        document.getElementById('exp-week-next').addEventListener('click', function(){
            _exp.fecha = getWeekInfo(1, _exp.fecha).lunes;
            _exp.mes = _exp.fecha.substring(0, 7);
            renderExpPicker();
            renderExpPreview();
        });
    } else {
        el.innerHTML = '<input type="month" id="exp-month-input" class="exp-month-input" value="' + _exp.mes + '">';
        document.getElementById('exp-month-input').addEventListener('change', function(e){
            _exp.mes = e.target.value;
            renderExpPreview();
        });
    }
}

function getWeekInfo(offset, baseFecha){
    var fecha = baseFecha || _exp.fecha;
    var base = new Date(fecha + 'T12:00:00');
    var dow = base.getDay();
    var diff = dow === 0 ? -6 : 1 - dow;
    var monday = new Date(base);
    monday.setDate(monday.getDate() + diff + (offset * 7));

    var sunday = new Date(monday);
    sunday.setDate(sunday.getDate() + 6);

    var pad = function(n){ return n < 10 ? '0' + n : '' + n; };
    var lunes    = monday.getFullYear() + '-' + pad(monday.getMonth()+1) + '-' + pad(monday.getDate());
    var domingo  = sunday.getFullYear() + '-' + pad(sunday.getMonth()+1) + '-' + pad(sunday.getDate());

    var d4 = new Date(monday.getTime());
    d4.setUTCDate(d4.getUTCDate() + 4 - (d4.getUTCDay()||7));
    var ys = new Date(Date.UTC(d4.getUTCFullYear(),0,1));
    var wk = Math.ceil((((d4-ys)/86400000)+1)/7);

    return { num: wk, lunes: lunes, domingo: domingo };
}

/* ── Preview ──────────────────────────────────────────────── */
function renderExpPreview(){
    var el = document.getElementById('exp-preview');
    var p = _exp.period;
    var texto = '';

    if(p === 'dia'){
        texto = formatFechaLarga(_exp.fecha);
    } else if(p === 'semana'){
        var info = getWeekInfo(0, _exp.fecha);
        texto = 'Semana ' + info.num + ' — ' + formatFechaCorta(info.lunes) + ' al ' + formatFechaCorta(info.domingo);
    } else {
        var partes = _exp.mes.split('-');
        texto = MESES_FULL[parseInt(partes[1])-1] + ' ' + partes[0];
    }

    el.innerHTML =
        '<div class="exp-preview-row">' +
            '<div class="exp-preview-icon"><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>' +
            '<div class="exp-preview-text"><strong>' + esc(texto) + '</strong></div>' +
        '</div>';
}

/* ── URL builder ──────────────────────────────────────────── */
function buildExportUrl(){
    var p = _exp.period;
    var url = BASE_APP + '/exportar/inasistencias?rango=' + p;

    if(p === 'dia'){
        url += '&mes=' + _exp.mes + '&fecha=' + _exp.fecha;
    } else if(p === 'semana'){
        url += '&mes=' + _exp.mes + '&fecha=' + _exp.fecha;
    } else {
        url += '&mes=' + _exp.mes + '&fecha=' + _exp.mes + '-01';
    }

    if(_exp.soloFaltas) url += '&faltas=1';
    return url;
}

/* ── Event listeners ──────────────────────────────────────── */
document.getElementById('btn-export-toggle').addEventListener('click', openExportModal);

document.getElementById('modal-confirm').addEventListener('click', function(){
    var u = buildExportUrl();
    closeExportModal();
    if(u) window.open(u, '_blank');
});
document.getElementById('modal-cancel-btn').addEventListener('click', closeExportModal);
document.getElementById('modal-cancel').addEventListener('click', closeExportModal);
document.getElementById('export-modal').addEventListener('click', function(e){
    if(e.target === this) closeExportModal();
});

document.getElementById('exp-period-group').addEventListener('click', function(e){
    var btn = e.target.closest('.exp-period-btn');
    if(!btn || btn.classList.contains('active')) return;

    _exp.period = btn.dataset.period;
    document.querySelectorAll('.exp-period-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');

    if(_exp.period === 'semana'){
        var info = getWeekInfo(0);
        _exp.fecha = info.lunes;
        _exp.mes = info.lunes.substring(0, 7);
    } else if(_exp.period === 'mes'){
        _exp.mes = S.mes;
    } else {
        _exp.fecha = S.fecha;
        _exp.mes = S.fecha.substring(0, 7);
    }

    renderExpPicker();
    renderExpPreview();
});

document.getElementById('exp-faltas-toggle').addEventListener('change', function(e){
    _exp.soloFaltas = e.target.checked;
    renderExpPreview();
});

/* ── Render: header ───────────────────────────────────────── */
function renderHdr(d){
    var title = d.mesLabel;
    if(d.modo === 'semana'){
        var sem = d.semana;
        title = 'Semana '+d.numSemana+' · '+d.mesLabel;
    } else if(d.modo === 'dia'){
        title = formatFechaLarga(d.fechaSel);
    }
    elTitle.textContent = title;

    elBtnD.classList.toggle('on',   d.modo==='dia');
    elBtnSem.classList.toggle('on', d.modo==='semana');
    elBtnMes.classList.toggle('on', d.modo==='mes');

    if(elDpto.options.length <= 1){
        d.dptos.forEach(function(dp){
            var opt=document.createElement('option');
            opt.value=dp; opt.textContent=dp;
            elDpto.appendChild(opt);
        });
    }
    elDpto.value=d.filtros.dpto;
    elEstado.value=d.filtros.estado;
    if(document.activeElement !== elQ) elQ.value=d.filtros.q;

    var elToggle=document.getElementById('lbl-ausencias');
    var elToggleInput=document.getElementById('f-ausencias');
    elToggleInput.checked=S.ausencias;
    elToggle.classList.toggle('active', S.ausencias);
}

/* ── Render: calendar ─────────────────────────────────────── */
function renderCal(d){
    var weekDates = {};
    if(d.modo === 'semana'){
        d.semana.forEach(function(w){ weekDates[w.fecha] = true; });
    }

    var h='', labels=['Lu','Ma','Mi','Ju','Vi','Sá','Do'];
    labels.forEach(function(l,i){ h+='<div class="cdh'+(i>=5?' wk':'')+'">'+l+'</div>'; });
    for(var i=1;i<d.primerDOW;i++) h+='<div class="cd empty"></div>';
    var yy=+d.mes.split('-')[0], mm=+d.mes.split('-')[1];

    for(var day=1;day<=d.diasEnMes;day++){
        var pad = day<10?'0'+day:''+day;
        var f   = d.mes+'-'+pad;
        var dt  = new Date(yy,mm-1,day);
        var dow = dt.getDay(); var dowM = dow===0?7:dow;
        var wk  = dowM>=6;

        var esModoMes = (d.modo === 'mes');
        var sel = (f === d.fechaSel) && !esModoMes;
        var hoy = (f === d.hoy);
        var inW = !!weekDates[f] || esModoMes;

        var dot = d.dots[f]||null;
        var cls = 'cd'+(wk?' wk':'')+(sel?' sel':'')+(hoy?' hoy':'')+(inW&&!sel?' in-week':'');

        h+='<div class="'+cls+'" data-fecha="'+f+'">';
        h+='<span class="dn">'+day+'</span>';
        if(dot){
            h+='<div class="dr">';
            if(+dot.ok_cnt>0)  h+='<span class="dot dok"></span>';
            if(+dot.inc_cnt>0) h+='<span class="dot dis"></span>';
            if(+dot.obs_cnt>0) h+='<span class="dot dobs"></span>';
            if(+dot.err_cnt>0) h+='<span class="dot derr"></span>';
            h+='</div><span class="dc">'+dot.total+'</span>';
        }
        h+='</div>';
    }
    elCal.innerHTML=h;
}

/* ── Render: stats ────────────────────────────────────────── */
function renderStats(d){
    var s=d.stats;
    elStats.innerHTML=
        '<div class="sc"><div class="sv">'+(s.empleados||0)+'</div><div class="sl">Empleados</div></div>'+
        '<div class="sc"><div class="sv">'+(s.dias_datos||0)+'</div><div class="sl">Días c/ datos</div></div>'+
        '<div class="sc"><div class="sv" style="color:var(--grn)">'+(s.ok_total||0)+'</div><div class="sl">OK</div></div>'+
        '<div class="sc"><div class="sv" style="color:var(--amb)">'+(s.inc_total||0)+'</div><div class="sl">Incidencias</div></div>'+
        '<div class="sc"><div class="sv" style="color:var(--sky)">'+(s.obs_total||0)+'</div><div class="sl">Observados</div></div>'+
        '<div class="sc"><div class="sv" style="color:var(--red)">'+(s.err_total||0)+'</div><div class="sl">Errores</div></div>';
}

/* ── Render: day view ─────────────────────────────────────── */
function renderDay(d){
    var p=d.presentes, a=d.ausentes, h='';
    var tabActiva='tp';
    if(S.ausencias){ p=[]; tabActiva='ta'; }

    h+='<div class="tabs">';
    h+='<button class="tab '+(tabActiva==='tp'?'on':'')+'" data-tab="tp">Presentes ('+p.length+')</button>';
    h+='<button class="tab '+(tabActiva==='ta'?'on':'')+'" data-tab="ta">Ausentes ('+a.length+')</button>';
    h+='</div><div class="dcard-body">';

    h+='<div id="tp" style="'+(tabActiva==='tp'?'':'display:none')+'">';
    if(!p.length){
        h+='<div class="empty">Sin marcaciones para este dia o restringido por el filtro.</div>';
    } else {
        h+='<div class="tw"><table class="simple-table"><thead><tr><th>Nombre</th><th class="tc">#</th><th>Entrada</th><th>Salida</th><th>Total</th><th>Estado</th><th></th></tr></thead><tbody>';
        p.forEach(function(r){
            h+='<tr>';
            h+='<td class="tn">'+esc(r.nombre)+(+r.editado_manual?'<span class="edot" title="Editado manualmente"></span>':'')+'</td>';
            h+='<td class="tc tm">'+r.cantidad_marcaciones+'</td>';
            h+='<td class="tm">'+t5(r.entrada)+'</td>';
            h+='<td class="tm">'+t5(r.salida)+'</td>';
            h+='<td class="tt">'+t5(r.total_horas)+'</td>';
            h+='<td><span class="badge '+badgeCls(r.estado)+'">'+esc(r.estado)+'</span></td>';
            h+='<td><a href="'+EDITAR_BASE+'id='+r.id+'" class="be">Editar</a></td>';
            h+='</tr>';
        });
        h+='</tbody></table></div>';
    }
    h+='</div>';

    h+='<div id="ta" style="'+(tabActiva==='ta'?'':'display:none')+'">';
    if(!a.length){
        h+='<div class="empty">'+(d.presentes.length?'Sin ausencias detectadas o restringido por filtro.':'Sin datos para comparar.')+'</div>';
    } else {
        h+='<div class="aug">';
        a.forEach(function(r){
            h+='<div class="auc"><div class="aun">'+esc(r.nombre)+'</div><div class="aum">'+esc(r.dpto)+' · '+esc(r.numero)+'</div></div>';
        });
        h+='</div>';
    }
    h+='</div></div>';
    elCard.innerHTML=h;
    bindTabs();
}

/* ── Render: week matrix ──────────────────────────────────── */
function renderSemana(d){
    var mdata=d.matrizData, aus=d.ausentesSemana, sem=d.semana, h='';
    var emps=d.matrizEmps;
    if(S.ausencias){
        emps=emps.filter(function(emp){
            for(var i=0;i<5;i++){
                var w=sem[i]; if(!w) continue;
                if(!mdata[emp.rut]||!mdata[emp.rut][w.fecha]) return true;
            }
            return false;
        });
    }

    var totalPresencias = 0;
    var totalFaltas = 0;
    emps.forEach(function(emp){
        sem.forEach(function(w){
            if(w.fecha > d.hoy) return; // No sumar los días futuros
            var c = mdata[emp.rut] && mdata[emp.rut][w.fecha];
            if(c){
                totalPresencias++;
            } else if(!w.fin){
                totalFaltas++; // Solo contamos faltas de Lunes a Viernes (!w.fin)
            }
        });
    });

    if(!emps.length && !aus.length){
        h+='<div class="empty">Sin registros que coincidan con los filtros.</div>';
    } else {
        h+='<div class="dcard-body">';
        h+='<div class="mw"><table class="mt"><thead><tr><th>Empleado</th>';
        sem.forEach(function(w){
            var cls='thd'+(w.fin?' wk':'')+(w.hoy?' hoy':'');
            h+='<th><div class="'+cls+'" data-fecha="'+w.fecha+'"><span class="thl">'+w.label+'</span><span class="thn">'+w.num+'</span></div></th>';
        });
        h+='</tr></thead><tbody>';

        var iconEdit = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Editar';

        emps.forEach(function(emp){
            var rd=mdata[emp.rut]||{};
            h+='<tr>';
            h+='<td><div class="men">'+esc(emp.nombre)+'</div><div class="med">'+esc(emp.dpto)+'</div></td>';
            sem.forEach(function(w,i){
                var c=rd[w.fecha]||null;
                if(c){
                    var cc=cellCls(c.estado);
                    var eu=EDITAR_BASE+'id='+c.id;
                    h+='<td class="mcl '+cc+' td-interactive">';
                    h+='<div class="normal-content" data-fecha="'+w.fecha+'">';
                    h+='<span class="ct">'+t5(c.entrada)+'</span>';
                    h+='<span class="cs">a</span>';
                    h+='<span class="ct">'+t5(c.salida)+'</span>';
                    if(c.total_horas) h+='<span class="ctot">'+t5(c.total_horas)+'</span>';
                    h+='</div>';
                    h+='<a class="hover-overlay" href="'+eu+'">'+iconEdit+'</a>';
                    h+='</td>';
                } else {
                    var editUrl=EDITAR_BASE+'rut='+emp.rut+'&fecha='+w.fecha;
                    if(i<5&&S.ausencias){
                        h+='<td class="mcl mer td-interactive" style="background:var(--rdg);">';
                        h+='<div class="normal-content" data-fecha="'+w.fecha+'"><span class="ct" style="color:var(--red);">FALTÓ</span></div>';
                    } else {
                        h+='<td class="mcl mem td-interactive">';
                        h+='<div class="normal-content" data-fecha="'+w.fecha+'"><span class="ct">—</span></div>';
                    }
                    h+='<a class="hover-overlay" href="'+editUrl+'">'+iconEdit+'</a></td>';
                }
            });
            h+='</tr>';
        });
        h+='</tbody></table></div>';
        if(aus.length){
            h+='<div class="ass"><div class="asttl">Faltaron toda la semana ('+aus.length+')</div><div class="asg">';
            aus.forEach(function(r){
                h+='<div class="asi"><div class="n">'+esc(r.nombre)+'</div><div class="m">'+esc(r.dpto)+' · '+esc(r.numero)+'</div></div>';
            });
            h+='</div></div>';
        }
        h+='</div>';
    }
    elCard.innerHTML=h;
}

/* ── Render: mes completo con PAGINACIÓN ──────────────────── */
function renderMes(d){
    var dias  = d.diasHabilesListaMes || [];
    var mdata = d.matrizDataMes  || {};
    var aus   = d.ausentesMes    || [];
    var dh    = d.diasHabilesDelMes || 0;
    var h     = '';

    var emps = d.matrizEmpsMes || [];

    var semanas = [];
    dias.forEach(function(dia){
        var dt = new Date(dia.fecha+'T12:00:00');
        var d4 = new Date(dt.getTime()); d4.setUTCDate(d4.getUTCDate() + 4 - (d4.getUTCDay()||7));
        var ys = new Date(Date.UTC(d4.getUTCFullYear(),0,1));
        var wk = Math.ceil((((d4-ys)/86400000)+1)/7);

        var lastSem = semanas[semanas.length-1];
        if(!lastSem || lastSem.num !== wk){ semanas.push({ num: wk, dias: [] }); }
        semanas[semanas.length-1].dias.push(dia);
    });

    // Filtro de ausencias (lun-vie)
    if(S.ausencias){
        emps = emps.filter(function(emp){
            for(var i=0; i<dias.length; i++){
                var dia = dias[i];
                if(dia.fecha > d.hoy) continue;
                if(!dia.esHabil) continue;
                if(!mdata[emp.rut] || !mdata[emp.rut][dia.fecha]) return true;
            }
            return false;
        });
    }

    var totalPresencias=0, totalFaltas=0;
    emps.forEach(function(emp){
        dias.forEach(function(dia){
            if(dia.fecha > d.hoy) return;
            var c = mdata[emp.rut] && mdata[emp.rut][dia.fecha];
            if(c){ totalPresencias++; }
            else if(dia.esHabil){ totalFaltas++; }
        });
    });

    var limit = 25; // Mostrar 25 empleados por página
    var totalEmps = emps.length;
    var totalPages = Math.ceil(totalEmps / limit) || 1;

    if (S.pagina > totalPages) S.pagina = totalPages;
    if (S.pagina < 1) S.pagina = 1;

    var startIndex = (S.pagina - 1) * limit;
    var empsPagina = emps.slice(startIndex, startIndex + limit);

    if (totalPages > 1) {
        h += '<div style="display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:8px;">';
        h += '<button class="ib" id="btn-prev-page" style="width:24px;height:24px;font-size:13px;" '+(S.pagina===1?'disabled':'')+'>&#8249;</button>';
        h += '<span style="font-size:11px; font-weight:600; color:var(--t2);">Pág. '+S.pagina+' de '+totalPages+'</span>';
        h += '<button class="ib" id="btn-next-page" data-total="'+totalPages+'" style="width:24px;height:24px;font-size:13px;" '+(S.pagina===totalPages?'disabled':'')+'>&#8250;</button>';
        h += '</div>';
    }

    if(!totalEmps && !aus.length){
        elCard.innerHTML = h + '<div class="empty">No hay empleados que presenten inasistencias de Lunes a Viernes este mes.</div>';
        return;
    }

    h += '<div class="dcard-body" style="padding:0;">';
    h += '<table class="mt" style="min-width:100%; table-layout:fixed;">';
    h += '<thead><tr>';
    h += '<th style="text-align:left; width:200px;">Empleado</th>';
    var diasNombres = ['LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES','SÁBADO','DOMINGO'];
    for(var i=0; i<7; i++){
        h += '<th style="text-align:center;">'+diasNombres[i]+'</th>';
    }
    h += '</tr></thead><tbody>';

    var iconEdit = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Editar';

    empsPagina.forEach(function(emp){
        var rd = mdata[emp.rut] || {};

        semanas.forEach(function(sem, wIdx){
            var isLastWeek = (wIdx === semanas.length - 1);
            var rowBorder = isLastWeek ? 'border-bottom: 2px solid var(--b1);' : 'border-bottom: 1px dashed var(--b0);';

            h += '<tr>';

            if(wIdx === 0){
                h += '<td rowspan="'+semanas.length+'" style="vertical-align:top; border-bottom: 2px solid var(--b1); background:var(--s1);">';
                h += '<div class="men">'+esc(emp.nombre)+'</div>';
                h += '<div class="med">'+esc(emp.dpto)+' · '+esc(emp.numero)+'</div>';
                h += '</td>';
            }

            var dowMap = {};
            sem.dias.forEach(function(d_obj){
                var dt = new Date(d_obj.fecha+'T12:00:00');
                var num = dt.getDay() || 7;
                dowMap[num] = d_obj;
            });

            for(var i=1; i<=7; i++){
                var dia = dowMap[i];
                if(dia){
                    var c = rd[dia.fecha] || null;
                    var editUrl = c ? EDITAR_BASE+'id='+c.id : EDITAR_BASE+'rut='+emp.rut+'&fecha='+dia.fecha;
                    var esDiaHabil = dia.esHabil;

                    var tdClass = 'mcl td-interactive ';
                    if(c) tdClass += cellCls(c.estado);
                    else if(S.ausencias && esDiaHabil) tdClass += 'mer';
                    else tdClass += 'mem';

                    h += '<td class="'+tdClass+'" style="'+rowBorder+'">';
                    h += '<div class="normal-content" data-fecha="'+dia.fecha+'">';
                    h += '<span class="mday-num">'+dia.num+'</span>';

                    if(c){
                        h += '<span class="ct">'+t5(c.entrada)+'</span>';
                        h += '<span class="cs">a</span>';
                        h += '<span class="ct">'+t5(c.salida)+'</span>';
                        if(c.total_horas) h += '<span class="ctot">'+t5(c.total_horas)+'</span>';
                    } else {
                        if(S.ausencias && esDiaHabil) h += '<span class="ct" style="color:var(--red);">FALTÓ</span>';
                        else h += '<span class="ct">—</span>';
                    }
                    h += '</div>';
                    h += '<a class="hover-overlay" href="'+editUrl+'">'+iconEdit+'</a>';
                    h += '</td>';
                } else {
                    h += '<td style="'+rowBorder+' background:var(--s2); opacity:0.5;"></td>';
                }
            }
            h += '</tr>';
        });
    });

    h += '</tbody></table>';

    if(aus.length){
        h += '<div class="ass">';
        h += '<div class="asttl">Empleados Inactivos este mes ('+aus.length+')</div>';
        h += '<div class="asg">';
        aus.forEach(function(r){
            h += '<div class="asi" style="border-left-color:var(--b2);"><div class="n">'+esc(r.nombre)+'</div><div class="m">'+esc(r.dpto)+' · '+esc(r.numero)+'</div></div>';
        });
        h += '</div></div>';
    }

    h += '</div>';
    elCard.innerHTML = h;
}

/* ── Tabs ─────────────────────────────────────────────────── */
function bindTabs(){
    var tabs=elCard.querySelectorAll('.tab');
    tabs.forEach(function(t){
        t.addEventListener('click',function(){
            tabs.forEach(function(x){ x.classList.remove('on'); });
            var tp=document.getElementById('tp'), ta=document.getElementById('ta');
            if(tp) tp.style.display='none';
            if(ta) ta.style.display='none';
            t.classList.add('on');
            var el=document.getElementById(t.dataset.tab);
            if(el) el.style.display='';
        });
    });
}

/* ── Render all ───────────────────────────────────────────── */
function renderAll(d){
    cur = d;
    renderHdr(d);
    renderCal(d);
    renderStats(d);
    if(d.modo==='semana')     renderSemana(d);
    else if(d.modo==='mes')   renderMes(d);
    else                      renderDay(d);
}

/* ── Navigate ─────────────────────────────────────────────── */
function navigate(push){
    var u=url(S.mes,S.fecha,S.modo,S.dpto,S.estado,S.q);
    if(push!==false) history.pushState(S,'',u);
    loading(true);
    fetch(u+'&json=1',{credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
            setTimeout(function() {
                renderAll(d);
                loading(false);
            }, 10);
        })
        .catch(function(){ loading(false); });
}

window.addEventListener('popstate',function(e){
    if(e.state){ S=e.state; navigate(false); }
});

/* ── Event delegation ─────────────────────────────────────── */
document.getElementById('app').addEventListener('click',function(e){
    // Eventos de paginación Cliente
    if (e.target.closest('#btn-prev-page')) {
        var btn = e.target.closest('#btn-prev-page');
        if(!btn.disabled && S.pagina > 1){
            S.pagina--;
            renderMes(cur); // Renderiza instantáneo sin petición al server
        }
        return;
    }
    if (e.target.closest('#btn-next-page')) {
        var btn = e.target.closest('#btn-next-page');
        var total = parseInt(btn.dataset.total);
        if(!btn.disabled && S.pagina < total){
            S.pagina++;
            renderMes(cur); // Renderiza instantáneo sin petición al server
        }
        return;
    }

    var cd=e.target.closest('.cd:not(.empty)');
    if(cd){
        if(S.modo==='mes'){ S.fecha=cd.dataset.fecha; S.modo='dia'; S.pagina=1; navigate(); return; }
        S.fecha=cd.dataset.fecha; S.pagina=1; navigate(); return;
    }
    var wd=e.target.closest('.wd:not(.out)');
    if(wd){ S.fecha=wd.dataset.fecha; S.pagina=1; navigate(); return; }
    var thd=e.target.closest('.thd[data-fecha]');
    if(thd){ S.fecha=thd.dataset.fecha; S.modo='dia'; S.pagina=1; navigate(); return; }

    var cellBg=e.target.closest('.normal-content[data-fecha]');
    if(cellBg){ e.preventDefault(); S.fecha=cellBg.dataset.fecha; S.modo='dia'; S.pagina=1; navigate(); return; }

    if(e.target.closest('#btn-prev')){
        if(cur){ S.mes=cur.mesPrev; S.fecha=cur.mesPrev+'-01'; S.pagina=1; navigate(); } return;
    }
    if(e.target.closest('#btn-next')){
        if(cur){ S.mes=cur.mesNext; S.fecha=cur.mesNext+'-01'; S.pagina=1; navigate(); } return;
    }
    if(e.target.closest('#btn-today')){
        S.mes=D0.hoy.substring(0,7); S.fecha=D0.hoy; S.modo='dia'; S.pagina=1; navigate(); return;
    }
    var mb=e.target.closest('[data-modo]');
    if(mb){ S.modo=mb.dataset.modo; S.pagina=1; navigate(); return; }
});

/* ── Filtros ──────────────────────────────────────────────── */
elDpto.addEventListener('change',  function(e){ S.dpto=e.target.value; S.pagina=1; navigate(); });
elEstado.addEventListener('change',function(e){ S.estado=e.target.value; S.pagina=1; navigate(); });
document.getElementById('f-ausencias').addEventListener('change',function(e){
    S.ausencias=e.target.checked;
    S.pagina = 1;
    renderAll(cur); // Re-render local para aplicar filtro local
});

/* ── Search debounce ─────────────────────────────────────────*/
var typingTimer;
elQ.addEventListener('input',function(e){
    clearTimeout(typingTimer);
    S.q=e.target.value;
    S.pagina = 1;
    typingTimer=setTimeout(function(){ navigate(); },400);
});

/* ── Init ─────────────────────────────────────────────────── */
renderAll(D0);
history.replaceState(S,'',url(S.mes,S.fecha,S.modo,S.dpto,S.estado,S.q));
