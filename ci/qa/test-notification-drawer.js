'use strict';
const fs = require('fs');
const assert = require('assert');
const source = fs.readFileSync(process.argv[2], 'utf8');
const start = source.indexOf('function notificationDrawer(rows){');
const end = source.indexOf('async function loadNotifications(){', start);
assert(start >= 0 && end > start, 'notificationDrawer boundaries found');
const fragment = source.slice(start, end);
const esc = value => String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
const uiIcon = name => '<svg data-icon="' + esc(name) + '"></svg>';
const draw = new Function('esc', 'uiIcon', fragment + '; return notificationDrawer;')(esc, uiIcon);
const rows = [
 {id:11,entity_type:'requisition',entity_id:'REQ-0007',title:'Requisition submitted',message:'Requisition submitted',created_at:'2026-10-17 10:00',location_name:'Mexico',severity:'info'},
 {id:12,entity_type:'purchase_order',entity_id:74,title:'Purchase order approved',message:'Open PO #74 to receive accepted quantities.',created_at:'2026-10-17 10:03',location_name:'Central Warehouse',severity:'success'},
 {id:13,entity_type:'other',entity_id:'',title:'Count discrepancy',message:'Variance needs review: <script>alert(1)</script>',created_at:'2026-10-17 10:06',location_name:'Gerji',severity:'warning'},
 {id:14,entity_type:'other',entity_id:'',title:'WARNING',message:'warning',created_at:'',location_name:'',severity:'danger'}
];
const html = draw(rows);
const checks = [
 [html.includes('data-review-notification="REQ-0007"'), 'requisition review hook retained'],
 [html.includes('data-review-notification-id="11"'), 'requisition notification ID hook retained'],
 [html.includes('data-review-purchase-order="74"'), 'purchase-order action hook retained'],
 [(html.match(/data-read-notification=/g)||[]).length===4, 'mark-read hook rendered for each item'],
 [(html.match(/Requisition submitted/g)||[]).length===1, 'case-insensitive duplicate title/body suppressed'],
 [html.includes('Open PO #74 to receive accepted quantities.'), 'unique notification body retained'],
 [html.includes('&lt;script&gt;alert(1)&lt;/script&gt;')&&!html.includes('<script>alert(1)</script>'), 'untrusted notification HTML escaped'],
 [html.includes('Newest first'), 'newest-first summary retained'],
 [!html.includes('Live workflow notifications'), 'redundant static summary removed'],
 [html.includes('<article class="sl-notification-card'), 'semantic article markup used'],
 [(html.match(/type="button"/g)||[]).length>=5, 'buttons explicitly declare type=button'],
 [!html.includes('>warning</div>'), 'case-only duplicate body suppressed']
];
const empty = draw([]);
checks.push([empty.includes('No unread operational alerts.'), 'empty state present']);
checks.push([empty.includes('role="status"'), 'empty state has status semantics']);
checks.push([empty.includes('id="closeNotificationDrawer"'), 'close action preserved']);
let failed = 0;
for (const [ok, name] of checks) { console.log((ok?'PASS ':'FAIL ') + name); if (!ok) failed++; }
console.log('Notification renderer: ' + (checks.length-failed) + '/' + checks.length + ' PASS');
if (failed) process.exit(1);
