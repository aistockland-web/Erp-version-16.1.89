#!/usr/bin/env python3
"""Apply the read-only, role-scoped report CSV enhancement to ERP app.js."""

def patch_app_js(source: str) -> str:
    helpers = r'''function reportCsvField(value){const numeric=typeof value==='number'&&Number.isFinite(value);let text=value===null||value===undefined?'':String(value);if(!numeric&&/^\s*[=+\-@]/.test(text))text="'"+text;if(/[",\r\n]/.test(text))text='"'+text.replace(/"/g,'""')+'"';return text}
function buildPeriodSummaryCsv(report,user){const d=report&&typeof report==='object'?report:{},t=d.totals||{},ret=d.returns||{},canProfit=!!(user&&user.can_cost);const n=v=>{const x=Number(v);return Number.isFinite(x)?x:0};const rows=[['Stockland Ethiopia ERP — Period Summary'],['From',d.from||''],['To',d.to||''],['Scope','Current user authorized ERP scope'],[],['Summary','Metric','Value'],['Summary','Gross sales',n(t.sales)],['Summary','Net sales excluding VAT',n(t.net)],['Summary','Recorded VAT',n(t.vat)],['Summary','Units sold',n(t.units)],['Summary','Return units',n(ret.units)],['Summary','Return rows',n(ret.rows)]];if(canProfit)rows.push(['Summary','Gross profit',n(t.profit)]);rows.push([],['Branch performance','Branch','Units sold','Sales value',...(canProfit?['Gross profit']:[])]);for(const b of Array.isArray(d.branches)?d.branches:[]){const row=['Branch',b.location_name||'',n(b.retail_units),n(b.retail_sales)];if(canProfit)row.push(n(b.retail_profit));rows.push(row)}rows.push([],['Top products','SKU','Product','Units sold','Sales value',...(canProfit?['Gross profit']:[])]);for(const x of Array.isArray(d.top_products)?d.top_products:[]){const row=['Product',x.sku||'',x.product_name||'',n(x.units),n(x.sales)];if(canProfit)row.push(n(x.profit));rows.push(row)}rows.push([],['Transfers','Transfer ID','From location','To location','SKU','Quantity','Received quantity','Status']);for(const x of Array.isArray(d.transfers)?d.transfers:[])rows.push(['Transfer',x.transfer_id||'',x.from_location_name||'',x.to_location_name||'',x.sku||'',n(x.quantity),n(x.received_qty),x.status||'']);rows.push([],['Transfer variances','Transfer ID','SKU','Product','From location','To location','Sent quantity','Received quantity','Variance']);for(const x of Array.isArray(d.variances)?d.variances:[])rows.push(['Variance',x.transfer_id||'',x.sku||'',x.product_name||'',x.from_location_name||'',x.to_location_name||'',n(x.dispatched_qty),n(x.received_qty),n(x.variance_qty)]);rows.push([],['Physical counts','Batch ID','Location','Status','Lines','Variance lines','Counted units','System units','Variance units']);for(const x of Array.isArray(d.physical_counts)?d.physical_counts:[])rows.push(['Physical count',x.batch_id||'',x.location_name||'',x.status||'',n(x.lines),n(x.variance_lines),n(x.counted_qty),n(x.system_qty),n(x.variance_qty)]);return '\uFEFF'+rows.map(row=>row.map(reportCsvField).join(',')).join('\r\n')+'\r\n'}
function reportPresetRange(period){const [year,month,day]=localDate().split('-').map(Number);const end=new Date(year,month-1,day);const start=new Date(end);if(period==='weekly')start.setDate(start.getDate()-6);else if(period==='monthly')start.setDate(1);const fmt=x=>x.getFullYear()+'-'+String(x.getMonth()+1).padStart(2,'0')+'-'+String(x.getDate()).padStart(2,'0');return{from:fmt(start),to:fmt(end)}}
function downloadPeriodSummaryCsv(){const d=state.report;if(!d||!d.totals){toast('Run a report before downloading CSV.',true);return}const csv=buildPeriodSummaryCsv(d,state.boot?.user||{});const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});const url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;link.download='stockland-summary-'+String(d.from||'start')+'-to-'+String(d.to||'end')+'.csv';link.style.display='none';document.body.appendChild(link);link.click();link.remove();window.setTimeout(()=>URL.revokeObjectURL(url),1000)}
'''
    if source.count('function reports(){') != 1:
        raise ValueError('Expected one reports() UI function.')
    if 'function reportCsvField(value)' in source:
        raise ValueError('Report CSV helpers already exist; review before applying the patch again.')
    source = source.replace('function reports(){', helpers + 'function reports(){', 1)

    start = source.index('function reports(){')
    end = source.index('\nasync function loadReport(', start)
    replacement = r'''function reports(){

  const reportActions='<button type="button" class="sl-btn" id="printOpsReport">Print</button><button type="button" class="sl-btn primary" id="downloadOpsReportCsv" disabled>DOWNLOAD CSV</button>';
  const presets='<div class="sl-actions sl-report-period-actions" aria-label="Summary period presets"><button type="button" class="sl-btn" data-report-preset="daily">DAILY SUMMARY</button><button type="button" class="sl-btn" data-report-preset="weekly">WEEKLY SUMMARY</button><button type="button" class="sl-btn" data-report-preset="monthly">MONTHLY SUMMARY</button></div><div class="sl-sub">CSV summaries are read-only exports of posted ERP records. Daily POS CSV imports keep their existing preview, approval, duplicate checks and day lock; weekly/monthly summary totals are never posted as sales.</div>';
  return title('Reports & Analytics','Date-scoped operational reports from the authorized live ERP data. Financial figures and profit remain governed by server-side permissions.',reportActions)+
    '<div class="sl-panel sl-report-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">REPORT FILTER</div><h3>Choose reporting period</h3></div><small>Authorized location scope is applied by the server</small></div><form id="reportForm" class="sl-form sl-report-filter"><div class="sl-field"><label for="reportFrom">From</label><input id="reportFrom" name="from" type="date" value="'+esc(localMonthStart())+'" required></div><div class="sl-field"><label for="reportTo">To</label><input id="reportTo" name="to" type="date" value="'+esc(localDate())+'" required></div><div class="sl-field full"><button type="submit" class="sl-btn primary" id="runOpsReport">RUN REPORT</button></div></form>'+presets+'</div><div id="reportResult"><div class="sl-empty">Loading report data…</div></div>';
}'''
    source = source[:start] + replacement + source[end:]

    old = """  const run=document.getElementById('runOpsReport');if(run){run.disabled=true;run.setAttribute('aria-busy','true');run.textContent='LOADING…';}
  el.innerHTML='<div class=\"sl-empty\">Loading authorized report data…</div>';
"""
    new = """  const run=document.getElementById('runOpsReport');if(run){run.disabled=true;run.setAttribute('aria-busy','true');run.textContent='LOADING…';}
  state.report=null;const csvButton=document.getElementById('downloadOpsReportCsv');if(csvButton)csvButton.disabled=true;
  el.innerHTML='<div class=\"sl-empty\">Loading authorized report data…</div>';
"""
    if source.count(old) != 1:
        raise ValueError('Could not locate unique loadReport start anchor.')
    source = source.replace(old, new, 1)

    old = """    const d=await api('/app/reports/summary?from='+encodeURIComponent(from)+'&to='+encodeURIComponent(to));state.report=d||{};
    const t=d.totals||{},returnInfo=d.returns||{},branches=Array.isArray(d.branches)?d.branches:[],tops=Array.isArray(d.top_products)?d.top_products:[],vars=Array.isArray(d.variances)?d.variances:[],transfers=Array.isArray(d.transfers)?d.transfers:[],counts=Array.isArray(d.physical_counts)?d.physical_counts:[],people=Array.isArray(d.people)?d.people:[],stock=Array.isArray(d.stock)?d.stock:[];
"""
    new = """    const d=await api('/app/reports/summary?from='+encodeURIComponent(from)+'&to='+encodeURIComponent(to));state.report=d||{};const exportButton=document.getElementById('downloadOpsReportCsv');if(exportButton)exportButton.disabled=!(d&&d.totals);
    const t=d.totals||{},returnInfo=d.returns||{},branches=Array.isArray(d.branches)?d.branches:[],tops=Array.isArray(d.top_products)?d.top_products:[],vars=Array.isArray(d.variances)?d.variances:[],transfers=Array.isArray(d.transfers)?d.transfers:[],counts=Array.isArray(d.physical_counts)?d.physical_counts:[],people=Array.isArray(d.people)?d.people:[],stock=Array.isArray(d.stock)?d.stock:[];
"""
    if source.count(old) != 1:
        raise ValueError('Could not locate unique loadReport result anchor.')
    source = source.replace(old, new, 1)

    old = """if($('#reportForm')){const rf=$('#reportForm');rf.onsubmit=async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.currentTarget));try{await loadReport(d.from,d.to)}catch(err){toast(err.message,true)}};loadReport(rf.elements.from.value,rf.elements.to.value).catch(()=>{});}
"""
    new = """if($('#reportForm')){const rf=$('#reportForm');rf.onsubmit=async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.currentTarget));try{await loadReport(d.from,d.to)}catch(err){toast(err.message,true)}};loadReport(rf.elements.from.value,rf.elements.to.value).catch(()=>{});}
document.querySelectorAll('[data-report-preset]').forEach(b=>{b.onclick=()=>{const range=reportPresetRange(b.dataset.reportPreset);const f=$('#reportForm');if(f){f.elements.from.value=range.from;f.elements.to.value=range.to;}loadReport(range.from,range.to).catch(err=>toast(err.message,true));}});
if($('#downloadOpsReportCsv'))$('#downloadOpsReportCsv').onclick=downloadPeriodSummaryCsv;

"""
    if source.count(old) != 1:
        raise ValueError('Could not locate unique report-form binding anchor.')
    source = source.replace(old, new, 1)

    replacements = [
        ('Sales Employee / Branch Manager. Upload the actual external POS end-of-day Excel, then review the staged evidence.',
         'Sales Employee / Branch Manager. Upload transaction-level external POS end-of-day CSV or Excel evidence, then review the staged rows.'),
        ('1 • EXCEL UPLOAD</b><span>Use the end-of-day workbook exported from the separate branch POS.',
         '1 • CSV / EXCEL UPLOAD</b><span>Use the transaction-level end-of-day CSV or workbook exported from the separate branch POS.'),
        ('Upload the actual external POS Excel workbook.',
         'Upload the actual external POS CSV or Excel source file.'),
        ('upload the actual branch POS workbook and complete validation/staging only.',
         'upload the actual branch POS CSV or workbook and complete validation/staging only.'),
        ('<div class="sl-eyebrow">EXTERNAL POS • EXCEL EVIDENCE</div><h3>Upload Daily POS Excel</h3>',
         '<div class="sl-eyebrow">EXTERNAL POS • CSV / EXCEL EVIDENCE</div><h3>Upload Daily POS CSV or Excel</h3>'),
        ('Upload the actual end-of-day workbook. The ERP validates and stages it. Nothing posts at upload time and there is no alternate POS/cart workflow here.',
         'Upload the actual end-of-day CSV or Excel file. The ERP validates and stages it. Nothing posts at upload time and there is no alternate POS/cart workflow here.'),
        ('<label>Daily POS Excel (.xlsx)</label><div class="sl-file-picker" data-file-picker><input id="dailyPosFile" name="file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><label class="sl-file-button" for="dailyPosFile"><span class="sl-file-button-main">Choose Excel file</span>',
         '<label>Daily POS CSV or Excel (.csv / .xlsx)</label><div class="sl-file-picker" data-file-picker><input id="dailyPosFile" name="file" type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><label class="sl-file-button" for="dailyPosFile"><span class="sl-file-button-main">Choose CSV or Excel file</span>'),
        ("card('Entry method','Excel only','External POS end-of-day workbook')",
         "card('Entry method','CSV or Excel','External POS end-of-day source file')"),
        ('external branch POS can still be reconciled through Daily POS Excel.',
         'external branch POS can still be reconciled through Daily POS CSV or Excel.'),
    ]
    for old_text, new_text in replacements:
        if source.count(old_text) != 1:
            raise ValueError('Expected unique Daily POS UI anchor: ' + old_text[:100])
        source = source.replace(old_text, new_text, 1)
    return source
