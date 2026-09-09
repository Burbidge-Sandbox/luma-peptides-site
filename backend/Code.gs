/**
 * Luma Peptides Co. — order & event capture (Google Apps Script)
 * Receives JSON POSTs from the storefront, appends every event to a
 * Google Sheet, and emails new orders to ORDER_EMAIL.
 *
 * SETUP (about 5 minutes):
 *  1. Create a new Google Sheet. Rename the first tab "events".
 *  2. Extensions → Apps Script. Delete the sample code, paste this file, Save.
 *  3. Deploy → New deployment → type "Web app".
 *       Execute as: Me.   Who has access: Anyone.   → Deploy.
 *     Authorize when prompted (it needs Sheets + Mail).
 *  4. Copy the Web app URL. Paste it into site/js/site.js (and
 *     site/compliant/js/site.js) as CONFIG.captureEndpoint. Push.
 *  Any later code change here needs Deploy → Manage deployments → Edit → New version.
 */
var ORDER_EMAIL = "info@lumaresearchco.com";

function doPost(e) {
  try {
    var body = JSON.parse(e.postData.contents);
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    appendEvent_(ss, body);
    if (body.event === "order_placed") { appendOrder_(ss, body); emailOrder_(body); }
    if (["contact_form","waitlist_join","newsletter_signup"].indexOf(body.event) >= 0) { appendContact_(ss, body); }
    return ContentService.createTextOutput("ok");
  } catch (err) {
    return ContentService.createTextOutput("error: " + err);
  }
}
function doGet() { return ContentService.createTextOutput("Luma capture endpoint is live."); }

function sheet_(ss, name, header) {
  var sh = ss.getSheetByName(name);
  if (!sh) { sh = ss.insertSheet(name); sh.appendRow(header); sh.setFrozenRows(1); }
  return sh;
}
function g_(o, path) { return path.split(".").reduce(function(a, k){ return (a && a[k] !== undefined) ? a[k] : ""; }, o); }

function appendEvent_(ss, b) {
  var sh = sheet_(ss, "events", ["ts","event","variant","visitor","session","page","referrer","utm_source","utm_medium","utm_campaign","utm_term","utm_content","gclid","fbclid","first_landing","first_referrer","first_utm_source","mobile","lang","screen","cart_count","cart_subtotal","data_json"]);
  sh.appendRow([b.ts,b.event,b.variant,b.visitor,b.session,b.page,b.referrer,g_(b,"utm.utm_source"),g_(b,"utm.utm_medium"),g_(b,"utm.utm_campaign"),g_(b,"utm.utm_term"),g_(b,"utm.utm_content"),g_(b,"utm.gclid"),g_(b,"utm.fbclid"),g_(b,"first_touch.landing"),g_(b,"first_touch.referrer"),g_(b,"first_touch.utm.utm_source"),g_(b,"device.mobile"),g_(b,"device.lang"),g_(b,"device.screen"),g_(b,"cart.count"),g_(b,"cart.subtotal"),JSON.stringify(b.data||{})]);
}
function appendOrder_(ss, b) {
  var d = b.data, o = d.order || {};
  var items = (o.items||[]).map(function(i){ return i.qty+" x "+i.name+" "+i.strength+" @ $"+i.price; }).join("\n");
  var sh = sheet_(ss, "orders", ["ts","order_id","status","variant","name","org","email","phone","address1","address2","city","state","zip","shipping","items","subtotal","discount","promo","ship_cost","total","payment","venmo_note","visitor","first_utm_source","first_landing"]);
  sh.appendRow([b.ts,o.id,"awaiting_payment",b.variant,d.name,d.org,d.email,d.phone,g_(d,"address.line1"),g_(d,"address.line2"),g_(d,"address.city"),g_(d,"address.state"),g_(d,"address.zip"),o.ship,items,g_(o,"totals.sub"),g_(o,"totals.discount"),g_(o,"totals.code"),g_(o,"totals.ship"),g_(o,"totals.total"),"Venmo @"+g_(o,"payment.handle"),"Order "+o.id,b.visitor,g_(b,"first_touch.utm.utm_source"),g_(b,"first_touch.landing")]);
}
function appendContact_(ss, b) {
  var d = b.data;
  var sh = sheet_(ss, "contacts", ["ts","type","email","name","org","topic","message","product","variant","visitor","first_utm_source","first_landing"]);
  sh.appendRow([b.ts,b.event,d.email||"",d.name||"",d.org||"",d.topic||"",d.message||"",d.name && b.event==="waitlist_join" ? d.name : (d.id||""),b.variant,b.visitor,g_(b,"first_touch.utm.utm_source"),g_(b,"first_touch.landing")]);
}
function emailOrder_(b) {
  var d = b.data, o = d.order || {};
  var items = (o.items||[]).map(function(i){ return "  "+i.qty+" x "+i.name+" "+i.strength+" @ $"+i.price; }).join("\n");
  var text = "New order "+o.id+" ("+b.variant+")\n\n"+items+"\n\nSubtotal: $"+g_(o,"totals.sub")+"\nDiscount: $"+g_(o,"totals.discount")+"\nShipping: $"+g_(o,"totals.ship")+" ("+o.ship+")\nTOTAL: $"+g_(o,"totals.total")+"\n\nPayment: Venmo — expect note \"Order "+o.id+"\"\n\nShip to:\n"+d.name+(d.org?"\n"+d.org:"")+"\n"+g_(d,"address.line1")+(g_(d,"address.line2")?", "+g_(d,"address.line2"):"")+"\n"+g_(d,"address.city")+", "+g_(d,"address.state")+" "+g_(d,"address.zip")+"\n"+d.email+"\n"+d.phone+"\n\nVisitor "+b.visitor+" · first touch "+g_(b,"first_touch.landing")+" · "+g_(b,"first_touch.utm.utm_source");
  MailApp.sendEmail(ORDER_EMAIL, "Order "+o.id+" — $"+g_(o,"totals.total")+" — "+d.name, text);
}
