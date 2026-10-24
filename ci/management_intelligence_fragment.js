function managementIntelligence(){
const user=state.boot?.user||{},caps=user.capabilities||{},d=state.boot?.dashboard||{};
if(!caps.finance_view||!['owner','management'].includes(String(user.role||'')))return '<div class="sl-panel"><div class="sl-notice sl-danger-block">Management Intelligence is restricted to authorized Owner and Management profiles.</div></div>';
const insights=state.dashboardInsights||{};
const asMoney=value=>'ETB '+new Intl.NumberFormat('en-ET',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(value||0));
const sum=(rows,key)=>Array.isArray(rows)?rows.reduce((total,row)=>total+Number(row?.[key]||0),0):0;
const trend=Array.isArray(insights.trend)?insights.trend:[];
const weekSales=sum(trend,'sales');
const monthSales=sum(insights.branches,'sales');
const status=state.dashboardInsightsLoading?'Refreshing authorized figures':(insights.range?'Loaded from the authorized ERP insights source':'Loading authorized data');
const kpi=(label,value,note,tone)=>'<div class="sl-card sl-dashboard-kpi-card sl-dashboard-kpi-'+tone+'"><div class="sl-dashboard-kpi-copy"><div class="label">'+esc(label)+'</div><div class="value" data-mi-kpi="'+esc(label)+'">'+esc(value)+'</div><div class="meta">'+esc(note)+'</div></div></div>';
return '<div class="sl-dashboard-page sl-management-intelligence">'+
 title('Management Intelligence','Decision support from authorized Stockland ERP records. Each metric retains the source module and ERP site-local date meaning.', '<button class="sl-btn" id="refreshManagementIntelligence" type="button">Refresh intelligence</button>')+
 '<div class="sl-dashboard-head-actions"><span class="sl-pill info">'+esc(status)+'</span><span class="sl-sub">'+esc(insights.range?.from||'')+(insights.range?.to?' to '+esc(insights.range.to):'')+' · closed retail sales only</span></div>'+
 '<div class="sl-grid sl-kpi-grid sl-dashboard-kpis">'+
 kpi('Retail sales · last 7 days',insights.range?asMoney(weekSales):'Loading…','Sum of closed retail sales by ERP site-local date','blue')+
 kpi('Retail sales · month to date',insights.range?asMoney(monthSales):'Loading…','Authorized branch sales; wholesale excluded from this metric','green')+
 kpi('Authorized branch rows',Array.isArray(insights.branches)?String(insights.branches.length):'Loading…','Rows returned within your reporting permissions','purple')+
 kpi('Low-stock snapshot',String(d.low_stock??'—'),'ERP bootstrap snapshot, not a substitute for the live reorder report','orange')+
 '</div>'+
 '<div class="sl-dashboard-workspace-grid"><div class="sl-dashboard-main"><div class="sl-dashboard-chart-grid">'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-trend"><div class="sl-block-head"><div><h3>Retail sales trend</h3><small>Last 7 days · ETB</small></div><small>Closed retail sales only</small></div><div id="slDashboardSalesTrend" class="sl-dashboard-chart-body"><div class="sl-empty">Loading posted sales trend…</div></div></section>'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-category"><div class="sl-block-head"><div><h3>Sales by category</h3><small>Month to date</small></div></div><div id="slDashboardCategories" class="sl-dashboard-chart-body"><div class="sl-empty">Loading category totals…</div></div></section>'+
 '</div><section class="sl-block sl-dashboard-card sl-dashboard-branch-stock"><div class="sl-block-head"><div><h3>Branch performance</h3><small>Month to date · authorized branch scope</small></div><button type="button" class="sl-btn sl-btn-sm" data-view="reports">Open Reports</button></div><div id="slDashboardBranches"><div class="sl-empty">Loading branch performance…</div></div></section>'+
 '<section class="sl-block sl-dashboard-card sl-dashboard-transactions"><div class="sl-block-head"><div><h3>Recent posted sales</h3><small>Recent rows in the source window; not a total transaction count</small></div><div class="sl-dashboard-head-actions"><small id="slDashboardInsightsStatus">'+esc(status)+'</small><button type="button" class="sl-btn sl-btn-sm" data-view="reports">Open reports</button></div></div><div id="slDashboardTransactions"><div class="sl-empty">Loading recent posted transactions…</div></div></section>'+
 '</div><aside class="sl-dashboard-side">'+
 '<section class="sl-block sl-dashboard-card"><div class="sl-block-head"><div><h3>Decision queue</h3><small>Review and use the authorized workflow; this page does not post transactions.</small></div></div><div class="sl-dashboard-quick-grid">'+
 '<button type="button" class="sl-dashboard-action" data-view="reorder"><span class="sl-dashboard-action-icon">'+uiIcon('box')+'</span><span><b>Critical stock & reorder</b><small>Review physical, reserved, available and inbound separately</small></span><span class="sl-control-arrow">›</span></button>'+
 '<button type="button" class="sl-dashboard-action" data-view="finance"><span class="sl-dashboard-action-icon">'+uiIcon('chart')+'</span><span><b>Finance & PLEDGER</b><small>Review posted accounting, balances and approvals</small></span><span class="sl-control-arrow">›</span></button>'+
 '<button type="button" class="sl-dashboard-action" data-view="hr"><span class="sl-dashboard-action-icon">'+uiIcon('users')+'</span><span><b>HR & compensation</b><small>Review current compensation and payroll readiness exceptions</small></span><span class="sl-control-arrow">›</span></button>'+
 '<button type="button" class="sl-dashboard-action" data-view="audit"><span class="sl-dashboard-action-icon">'+uiIcon('audit')+'</span><span><b>Audit trail</b><small>Trace posted records and authorized decisions</small></span><span class="sl-control-arrow">›</span></button>'+
 '<button type="button" class="sl-dashboard-action" data-view="health"><span class="sl-dashboard-action-icon">'+uiIcon('health')+'</span><span><b>System Health</b><small>Review schema and integrity exceptions</small></span><span class="sl-control-arrow">›</span></button>'+
 '</div></section><section class="sl-block sl-dashboard-card"><div class="sl-block-head"><div><h3>Metric scope & safeguards</h3></div></div><p class="sl-sub">Sales trend uses closed retail sales from the server-authorized insights endpoint. Branch rows remain location-scoped. Wholesale is excluded from these KPIs because the source does not provide reliable per-location ownership for this view. Low-stock is shown as a bootstrap snapshot only; use Critical Stock & Reorder for live item-level availability. Review the source report before making purchasing, staffing or financial decisions.</p></section>'+
 '</aside></div></div>';
}
function updateManagementIntelligenceKpis(payload){
 if(state.view!=='intelligence')return;
 const trend=Array.isArray(payload?.trend)?payload.trend:[],branches=Array.isArray(payload?.branches)?payload.branches:[];
 const sum=(rows,key)=>rows.reduce((total,row)=>total+Number(row?.[key]||0),0);
 const asMoney=value=>'ETB '+new Intl.NumberFormat('en-ET',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(value||0));
 const values={ 'Retail sales · last 7 days':asMoney(sum(trend,'sales')), 'Retail sales · month to date':asMoney(sum(branches,'sales')), 'Authorized branch rows':String(branches.length) };
 document.querySelectorAll('[data-mi-kpi]').forEach(node=>{const key=node.getAttribute('data-mi-kpi');if(Object.prototype.hasOwnProperty.call(values,key))node.textContent=values[key]});
}
