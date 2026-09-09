/**
 * Luma — order & event capture, customer emails, order status.
 * Google Apps Script bound to (or pointed at) the orders spreadsheet.
 *
 * SETUP
 *  1. Open the sheet: https://docs.google.com/spreadsheets/d/13P2JDXZ4gaX-jOJa7Yhj298o75Bxb5cZviFwd4oqkZc
 *  2. Extensions → Apps Script. Replace all code with this file. Save.
 *  3. Run the function `setup` once (toolbar ▶). Approve permissions (Sheets, Mail, Triggers).
 *  4. Deploy → New deployment → Web app. Execute as: Me. Who has access: Anyone. Deploy.
 *  5. Copy the Web app URL into site/js/site.js → CONFIG.captureEndpoint. Push.
 *  After any edit here: Deploy → Manage deployments → ✎ → Version: New → Deploy.
 *
 * DAILY USE
 *  - New orders land in the "orders" tab with status "awaiting_payment" and you get an email.
 *  - When a Venmo payment arrives, set that row's `status` to `paid`. The customer is emailed automatically.
 *  - Set `shipped` and fill `tracking` when it ships. The customer is emailed the tracking number.
 */
var SHEET_ID     = "13P2JDXZ4gaX-jOJa7Yhj298o75Bxb5cZviFwd4oqkZc";
var SECRET       = "luma-2026-9f3k";               // must match CONFIG.captureKey in site.js
var ORDER_EMAIL  = "info@lumaresearchco.com";       // where new orders and contact forms go
var FROM_NAME    = "Luma Research Co";              // marketing name on outgoing email
var LEGAL_NAME   = "Luma Peptides Co.";             // legal name in email footers
var SITE_URL     = "https://lumaresearchco.com";
var VENMO_HANDLE = "LumaResearchCo";
var ADDRESS      = "30 N Gould St, Sheridan, WY 82801";
var PHONE        = "(385) 521-5259";

function ss_(){ return SpreadsheetApp.openById(SHEET_ID); }
function sheet_(name, header){ var ss=ss_(), sh=ss.getSheetByName(name); if(!sh){ sh=ss.insertSheet(name); sh.appendRow(header); sh.setFrozenRows(1); } return sh; }
function g_(o,path){ return path.split(".").reduce(function(a,k){ return (a&&a[k]!==undefined&&a[k]!==null)?a[k]:""; },o); }
function money_(n){ n=Number(n)||0; return "$"+n.toFixed(n%1?2:0); }

var ORDER_HEADER=["ts","order_id","status","tracking","name","org","email","phone","address1","address2","city","state","zip","shipping","items","subtotal","discount","promo","tax","ship_cost","total","venmo_note","visitor","first_utm_source","first_landing","paid_email_sent","shipped_email_sent"];

function setup(){
  sheet_("events",["ts","event","variant","visitor","session","page","referrer","utm_source","utm_medium","utm_campaign","utm_term","utm_content","gclid","fbclid","first_landing","first_referrer","first_utm_source","mobile","lang","screen","cart_count","cart_subtotal","data_json"]);
  sheet_("orders",ORDER_HEADER);
  sheet_("contacts",["ts","type","email","name","org","phone","topic","message","product","visitor","first_utm_source","first_landing"]);
  ScriptApp.getProjectTriggers().forEach(function(t){ if(t.getHandlerFunction()==="onOrderEdit") ScriptApp.deleteTrigger(t); });
  ScriptApp.newTrigger("onOrderEdit").forSpreadsheet(SHEET_ID).onEdit().create();
  Logger.log("Setup complete. Now deploy as a web app.");
}

/* ---------- Incoming events from the site ---------- */
function doPost(e){
  try{
    var b=JSON.parse(e.postData.contents);
    if(b.key!==SECRET) return ContentService.createTextOutput("forbidden");
    appendEvent_(b);
    if(b.event==="order_placed"){ appendOrder_(b); emailOwnerOrder_(b); emailCustomerOrder_(b); }
    else if(["contact_form","waitlist_join","newsletter_signup","checkout_email","checkout_contact"].indexOf(b.event)>=0){ appendContact_(b); if(b.event==="contact_form") emailOwnerContact_(b); }
    return ContentService.createTextOutput("ok");
  }catch(err){ return ContentService.createTextOutput("error: "+err); }
}

/* ---------- Order status lookup (GET ?order=LP-XXXX&key=...) ---------- */
function doGet(e){
  var p=(e&&e.parameter)||{};
  var out={found:false};
  if(p.order && p.key===SECRET){
    var row=findOrder_(String(p.order).toUpperCase());
    if(row){ out={found:true,id:row.order_id,status:row.status,ts:row.ts,total:row.total,items:row.items,tracking:row.tracking}; }
  } else if(!p.order){ out={ok:true,message:"Luma capture endpoint is live."}; }
  return ContentService.createTextOutput(JSON.stringify(out)).setMimeType(ContentService.MimeType.JSON);
}
function findOrder_(id){
  var sh=sheet_("orders",ORDER_HEADER), vals=sh.getDataRange().getValues(), hdr=vals[0];
  for(var r=1;r<vals.length;r++){ if(String(vals[r][1]).toUpperCase()===id){ var o={}; hdr.forEach(function(h,i){ o[h]=vals[r][i]; }); o._row=r+1; return o; } }
  return null;
}

/* ---------- Sheet writers ---------- */
function appendEvent_(b){
  sheet_("events",[]).appendRow([b.ts,b.event,b.variant,b.visitor,b.session,b.page,b.referrer,g_(b,"utm.utm_source"),g_(b,"utm.utm_medium"),g_(b,"utm.utm_campaign"),g_(b,"utm.utm_term"),g_(b,"utm.utm_content"),g_(b,"utm.gclid"),g_(b,"utm.fbclid"),g_(b,"first_touch.landing"),g_(b,"first_touch.referrer"),g_(b,"first_touch.utm.utm_source"),g_(b,"device.mobile"),g_(b,"device.lang"),g_(b,"device.screen"),g_(b,"cart.count"),g_(b,"cart.subtotal"),JSON.stringify(b.data||{})]);
}
function itemsText_(o){ return (o.items||[]).map(function(i){ return i.qty+" × "+i.name+" "+i.strength+" @ "+money_(i.price); }).join("\n"); }
function appendOrder_(b){
  var d=b.data, o=d.order||{};
  sheet_("orders",ORDER_HEADER).appendRow([b.ts,o.id,"awaiting_payment","",d.name,d.org,d.email,d.phone,g_(d,"address.line1"),g_(d,"address.line2"),g_(d,"address.city"),g_(d,"address.state"),g_(d,"address.zip"),o.ship,itemsText_(o),g_(o,"totals.sub"),g_(o,"totals.discount"),g_(o,"totals.code"),g_(o,"totals.tax"),g_(o,"totals.ship"),g_(o,"totals.total"),"Order "+o.id,b.visitor,g_(b,"first_touch.utm.utm_source"),g_(b,"first_touch.landing"),"",""]);
}
function appendContact_(b){
  var d=b.data||{};
  sheet_("contacts",[]).appendRow([b.ts,b.event,d.email||"",d.name||((d.first||"")+" "+(d.last||"")).trim(),d.org||"",d.phone||"",d.topic||"",d.message||"",d.id||"",b.visitor,g_(b,"first_touch.utm.utm_source"),g_(b,"first_touch.landing")]);
}

/* ---------- Status changes made in the sheet ---------- */
function onOrderEdit(e){
  try{
    var sh=e.range.getSheet(); if(sh.getName()!=="orders") return;
    var hdr=sh.getRange(1,1,1,sh.getLastColumn()).getValues()[0], col=hdr[e.range.getColumn()-1]; if(col!=="status"&&col!=="tracking") return;
    var r=e.range.getRow(); if(r<2) return;
    var vals=sh.getRange(r,1,1,sh.getLastColumn()).getValues()[0], o={}; hdr.forEach(function(h,i){ o[h]=vals[i]; });
    var status=String(o.status).toLowerCase().trim();
    if(status==="paid" && !o.paid_email_sent){ emailCustomerPaid_(o); sh.getRange(r,hdr.indexOf("paid_email_sent")+1).setValue(new Date().toISOString()); }
    if(status==="shipped" && o.tracking && !o.shipped_email_sent){ emailCustomerShipped_(o); sh.getRange(r,hdr.indexOf("shipped_email_sent")+1).setValue(new Date().toISOString()); }
  }catch(err){ Logger.log(err); }
}

/* ---------- Email ---------- */
function venmoUrl_(id,total){ return "https://venmo.com/"+VENMO_HANDLE+"?txn=pay&amount="+Number(total).toFixed(2)+"&note="+encodeURIComponent("Order "+id); }
function qrUrl_(data){ return "https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data="+encodeURIComponent(data); }

function layout_(title, bodyHtml){
  return '<!doctype html><html><body style="margin:0;background:#F7F2EC;font-family:Helvetica,Arial,sans-serif;color:#2A2523">'
  +'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F7F2EC;padding:32px 12px"><tr><td align="center">'
  +'<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#FDFBF8;border:1px solid #E6DCD2;border-radius:14px;overflow:hidden">'
  +'<tr><td style="padding:28px 36px 8px"><div style="font-family:Georgia,\'Times New Roman\',serif;color:#B4432C;font-size:26px;line-height:.95">luma<br>peptides co.</div></td></tr>'
  +'<tr><td style="padding:8px 36px 0"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#B4432C;font-weight:bold">'+title+'</div></td></tr>'
  +bodyHtml
  +'<tr><td style="padding:24px 36px 28px;border-top:1px solid #E6DCD2;font-size:11px;line-height:1.6;color:#7C736E">For laboratory research use only. Not for human or animal use. Products are not intended to diagnose, treat, cure, or prevent any disease. '+LEGAL_NAME+' · '+ADDRESS+' · '+PHONE+' · <a href="mailto:'+ORDER_EMAIL+'" style="color:#7C736E">'+ORDER_EMAIL+'</a><br><a href="'+SITE_URL+'/privacy.html" style="color:#7C736E">Privacy</a> · <a href="'+SITE_URL+'/terms.html" style="color:#7C736E">Terms</a></td></tr>'
  +'</table></td></tr></table></body></html>';
}
function row_(k,v,bold){ return '<tr><td style="padding:8px 0;border-bottom:1px dashed #E6DCD2;font-size:14px;color:'+(bold?'#2A2523':'#4F4744')+(bold?';font-weight:bold':'')+'">'+k+'</td><td align="right" style="padding:8px 0;border-bottom:1px dashed #E6DCD2;font-size:14px;'+(bold?'font-weight:bold':'')+'">'+v+'</td></tr>'; }
function orderTable_(o){
  var rows=(o.items||[]).map(function(i){ return row_(i.qty+" × "+i.name+' <span style="color:#7C736E">'+i.strength+'</span>',money_(i.price*i.qty)); }).join("");
  rows+=row_("Subtotal",money_(g_(o,"totals.sub")));
  if(Number(g_(o,"totals.discount"))) rows+=row_("Discount","−"+money_(g_(o,"totals.discount")));
  if(Number(g_(o,"totals.tax"))) rows+=row_("Sales tax",money_(g_(o,"totals.tax")));
  rows+=row_("Shipping · "+o.ship, Number(g_(o,"totals.ship"))?money_(g_(o,"totals.ship")):"Free");
  rows+=row_("Total",money_(g_(o,"totals.total")),true);
  return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">'+rows+'</table>';
}
function btn_(href,label){ return '<a href="'+href+'" style="display:inline-block;background:#B4432C;color:#fff;text-decoration:none;font-weight:bold;font-size:13px;letter-spacing:1px;text-transform:uppercase;padding:14px 22px;border-radius:8px">'+label+'</a>'; }

function emailCustomerOrder_(b){
  var d=b.data, o=d.order||{}, total=g_(o,"totals.total"), pay=venmoUrl_(o.id,total);
  var body='<tr><td style="padding:10px 36px 0"><h1 style="font-family:Georgia,serif;font-weight:normal;font-size:30px;margin:0 0 12px;line-height:1.15">Order '+o.id+' is reserved.<br>One step left: pay by Venmo.</h1>'
  +'<p style="font-size:15px;line-height:1.6;color:#4F4744;margin:0 0 20px">Thanks, '+(d.name||"").split(" ")[0]+'. Your order is held in the lab-release queue and ships once the Venmo payment is received, usually within a few hours during business hours. Unpaid orders are cancelled after 48 hours.</p></td></tr>'
  +'<tr><td style="padding:0 36px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff;border:1.5px solid #B4432C;border-radius:12px"><tr>'
  +'<td style="padding:22px 22px 22px 24px;vertical-align:top"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#B4432C;font-weight:bold;margin-bottom:6px">Step 1 · Pay</div><div style="font-size:22px;font-weight:bold;margin-bottom:4px">'+money_(total)+' to @'+VENMO_HANDLE+'</div><div style="font-size:14px;color:#4F4744;line-height:1.5;margin-bottom:16px">Put <b>Order '+o.id+'</b> in the note so the payment matches this order.</div>'+btn_(pay,"Open Venmo · "+money_(total))+'<div style="font-size:12px;color:#7C736E;margin-top:12px">Or search Venmo for <b>@'+VENMO_HANDLE+'</b>.</div></td>'
  +'<td width="150" style="padding:18px 18px 18px 0;vertical-align:top;text-align:center"><img src="'+qrUrl_(pay)+'" width="132" height="132" alt="QR code: pay on Venmo" style="display:block;border:1px solid #E6DCD2;border-radius:8px;margin:0 auto 6px"><div style="font-size:11px;color:#7C736E;line-height:1.4">Reading this on a computer? Scan with your phone.</div></td></tr></table></td></tr>'
  +'<tr><td style="padding:24px 36px 6px"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#7C736E;font-weight:bold;margin-bottom:8px">Order summary</div>'+orderTable_(o)+'</td></tr>'
  +'<tr><td style="padding:12px 36px 0;font-size:14px;line-height:1.6;color:#4F4744"><b style="color:#2A2523">Ships to</b><br>'+(d.name||"")+(d.org?"<br>"+d.org:"")+'<br>'+g_(d,"address.line1")+(g_(d,"address.line2")?", "+g_(d,"address.line2"):"")+'<br>'+g_(d,"address.city")+', '+g_(d,"address.state")+' '+g_(d,"address.zip")+'</td></tr>'
  +'<tr><td style="padding:22px 36px 26px;font-size:13px;color:#7C736E;line-height:1.6">Track this order anytime at <a href="'+SITE_URL+'/status?order='+o.id+'" style="color:#B4432C">'+SITE_URL.replace("https://","")+'/status</a>. Reply to this email with any question about the order. All sales are final once shipped; damaged or incorrect items are replaced when reported within 7 days with photos.</td></tr>';
  MailApp.sendEmail({to:d.email,replyTo:ORDER_EMAIL,name:FROM_NAME,subject:"Order "+o.id+" reserved — pay "+money_(total)+" by Venmo to complete",htmlBody:layout_("Order reserved · payment pending",body)});
}
function emailCustomerPaid_(o){
  var body='<tr><td style="padding:10px 36px 6px"><h1 style="font-family:Georgia,serif;font-weight:normal;font-size:30px;margin:0 0 12px">Payment received. Thank you.</h1><p style="font-size:15px;line-height:1.6;color:#4F4744;margin:0 0 18px">Order <b>'+o.order_id+'</b> is paid and in the lab-release queue. A shipping notice with tracking follows once it leaves.</p><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#7C736E;font-weight:bold;margin-bottom:8px">Items</div><p style="font-size:14px;white-space:pre-line;color:#4F4744;margin:0 0 18px">'+o.items+'</p><p style="font-size:14px;margin:0 0 10px"><b>Total paid:</b> '+money_(o.total)+'</p>'+btn_(SITE_URL+"/status?order="+o.order_id,"View order status")+'</td></tr>';
  MailApp.sendEmail({to:o.email,replyTo:ORDER_EMAIL,name:FROM_NAME,subject:"Payment received for order "+o.order_id,htmlBody:layout_("Payment received",body)});
}
function emailCustomerShipped_(o){
  var body='<tr><td style="padding:10px 36px 6px"><h1 style="font-family:Georgia,serif;font-weight:normal;font-size:30px;margin:0 0 12px">Order '+o.order_id+' has shipped.</h1><p style="font-size:15px;line-height:1.6;color:#4F4744;margin:0 0 18px">Tracking number: <b>'+o.tracking+'</b><br>Shipping method: '+o.shipping+'<br>Plain outer packaging with a cold pack. Store at −20 °C on receipt.</p>'+btn_(SITE_URL+"/status?order="+o.order_id,"View order status")+'</td></tr>';
  MailApp.sendEmail({to:o.email,replyTo:ORDER_EMAIL,name:FROM_NAME,subject:"Order "+o.order_id+" shipped — tracking "+o.tracking,htmlBody:layout_("Shipped",body)});
}
function emailOwnerOrder_(b){
  var d=b.data, o=d.order||{};
  var text="New order "+o.id+"\n\n"+itemsText_(o)+"\n\nSubtotal: "+money_(g_(o,"totals.sub"))+"\nDiscount: "+money_(g_(o,"totals.discount"))+"\nTax: "+money_(g_(o,"totals.tax"))+"\nShipping: "+money_(g_(o,"totals.ship"))+" ("+o.ship+")\nTOTAL: "+money_(g_(o,"totals.total"))+"\n\nExpect Venmo note: \"Order "+o.id+"\"\nWhen it arrives, set status = paid in the orders tab (the customer is emailed automatically).\n\nShip to:\n"+d.name+(d.org?"\n"+d.org:"")+"\n"+g_(d,"address.line1")+(g_(d,"address.line2")?", "+g_(d,"address.line2"):"")+"\n"+g_(d,"address.city")+", "+g_(d,"address.state")+" "+g_(d,"address.zip")+"\n"+d.email+"\n"+d.phone+"\n\nSource: "+g_(b,"first_touch.utm.utm_source")+" · landing "+g_(b,"first_touch.landing")+"\nSheet: https://docs.google.com/spreadsheets/d/"+SHEET_ID;
  MailApp.sendEmail({to:ORDER_EMAIL,name:"Luma orders",subject:"NEW ORDER "+o.id+" — "+money_(g_(o,"totals.total"))+" — "+d.name,body:text});
}
function emailOwnerContact_(b){
  var d=b.data||{};
  MailApp.sendEmail({to:ORDER_EMAIL,name:"Luma website",replyTo:d.email||ORDER_EMAIL,subject:"Contact form: "+(d.topic||"Message")+" — "+(d.name||""),body:"From: "+d.name+" <"+d.email+">\nOrganization: "+(d.org||"")+"\nTopic: "+(d.topic||"")+"\n\n"+(d.message||"")+"\n\nReply to this email to answer."});
}
