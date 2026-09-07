/* ============================================================
   Product catalog — edit prices, copy, and categories here.
   `subscribe` = monthly subscription price; `once` = one-time price.
   ============================================================ */
window.LUMA_PRODUCTS = [
  {
    id:"tirzepatide-15", stock:"in", name:"Tirzepatide", label:["TIRZEPATIDE","15MG"], strength:"15mg vial",
    category:"weight", subscribe:299, once:349, badge:"Best Seller", rating:4.9, reviews:412,
    tagline:"Our most-loved metabolic support protocol.",
    description:"A dual GIP/GLP-1 receptor agonist studied for appetite regulation and metabolic health. Each Luma vial is lyophilized, sealed, and lot-tested for purity and identity before it ships.",
    inside:"Tirzepatide 15mg, lyophilized. Ships with bacteriostatic water, alcohol prep pads, and insulin syringes.",
    specs:{"Form":"Lyophilized powder","Vial size":"3ml, 15mg","Purity (last lot)":"99.4%","Storage":"Refrigerate after reconstitution","Supply":"4-week protocol"},
    faqs:[["How do I reconstitute?","Every order includes a step-by-step video and printed card. Add bacteriostatic water slowly down the side of the vial and swirl gently — never shake."],["Can I pause my subscription?","Yes. Pause, skip, or cancel anytime from your account. We remind you 5 days before every charge."]]
  },
  {
    id:"wolverine-stack", stock:"out", name:"Wolverine Stack", label:["WOLVERINE","STACK"], strength:"5mg blend",
    category:"recovery", subscribe:116, once:139, badge:"Top Rated", rating:4.8, reviews:287,
    tagline:"BPC-157 + TB-500. The recovery classic.",
    description:"Two of the most researched recovery peptides in a single vial. BPC-157 and TB-500 are studied for tissue repair, joint comfort, and mobility.",
    inside:"BPC-157 5mg + TB-500 5mg, lyophilized. Ships with bacteriostatic water and supplies.",
    specs:{"Form":"Lyophilized powder","Vial size":"3ml, 10mg total","Purity (last lot)":"99.1%","Storage":"Refrigerate after reconstitution","Supply":"4-week protocol"},
    faqs:[["Why combine them?","BPC-157 and TB-500 are studied for complementary mechanisms — one local, one systemic. Combining simplifies your routine to a single daily injection."]]
  },
  {
    id:"cjc-ipamorelin", stock:"out", name:"CJC-1295 + Ipamorelin", label:["CJC-1295 +","IPAMORELIN"], strength:"5mg blend",
    category:"longevity", subscribe:116, once:139, rating:4.7, reviews:198,
    tagline:"Sleep deeper. Recover faster.",
    description:"A growth-hormone-releasing blend studied for sleep quality, lean mass, and recovery. Taken before bed to align with your natural hormone rhythm.",
    inside:"CJC-1295 (no DAC) 2.5mg + Ipamorelin 2.5mg, lyophilized.",
    specs:{"Form":"Lyophilized powder","Vial size":"3ml, 5mg","Purity (last lot)":"99.2%","Storage":"Refrigerate after reconstitution","Supply":"4-week protocol"},
    faqs:[["When should I take it?","Most protocols take it 30 minutes before bed on an empty stomach."]]
  },
  {
    id:"ghk-cu", stock:"out", name:"GHK-Cu", label:["GHK-CU","5MG"], strength:"5mg vial",
    category:"skin", subscribe:99, once:119, rating:4.8, reviews:356,
    tagline:"The copper peptide behind glowing skin.",
    description:"A naturally occurring copper peptide studied for collagen support, skin firmness, and hair health. Our most popular starting point for skin.",
    inside:"GHK-Cu 5mg, lyophilized. Ships with bacteriostatic water and supplies.",
    specs:{"Form":"Lyophilized powder","Vial size":"3ml, 5mg","Purity (last lot)":"99.6%","Storage":"Refrigerate after reconstitution","Supply":"4-week protocol"},
    faqs:[["Is this a cream?","No — this is an injectable peptide protocol. We also offer topical guidance in your welcome kit."]]
  },
  {
    id:"glow-klow", stock:"in", name:"Glow + Klow", label:["GLOW + KLOW","5MG"], strength:"5mg blend",
    category:"skin", subscribe:149, once:179, badge:"New", rating:4.9, reviews:94,
    tagline:"BPC-157, GHK-Cu and TB-500. Inside-out radiance.",
    description:"Our signature glow blend pairs the copper peptide GHK-Cu with BPC-157 and TB-500 for skin, hair, and recovery in one daily routine.",
    inside:"BPC-157 + GHK-Cu + TB-500 blend, lyophilized.",
    specs:{"Form":"Lyophilized powder","Vial size":"3ml, 5mg","Purity (last lot)":"99.0%","Storage":"Refrigerate after reconstitution","Supply":"4-week protocol"},
    faqs:[["How soon will I notice a difference?","Most members report changes in skin texture around week 4–6. Consistency matters more than dose."]]
  },
  {
    id:"bpc-157", stock:"in", name:"BPC-157", label:["BPC-157","10MG"], strength:"10mg vial",
    category:"recovery", subscribe:89, once:109, rating:4.8, reviews:521,
    tagline:"Gut and joint support, simplified.",
    description:"A body-protective compound studied for gut lining health, tendon and ligament repair, and inflammation. The first peptide most members try.",
    inside:"BPC-157 10mg, lyophilized. Ships with bacteriostatic water and supplies.",
    specs:{"Form":"Lyophilized powder","Vial size":"3ml, 10mg","Purity (last lot)":"99.5%","Storage":"Refrigerate after reconstitution","Supply":"4-week protocol"},
    faqs:[["Oral or injectable?","We ship injectable. Injection near the site of concern is the most-studied route."]]
  },
  {
    id:"semaglutide-10", stock:"out", name:"Semaglutide", label:["SEMAGLUTIDE","10MG"], strength:"10mg vial",
    category:"weight", subscribe:199, once:239, rating:4.7, reviews:264,
    tagline:"The GLP-1 that started it all.",
    description:"A GLP-1 receptor agonist studied for appetite control and metabolic support. A gentle entry point for members new to metabolic peptides.",
    inside:"Semaglutide 10mg, lyophilized. Ships with bacteriostatic water and supplies.",
    specs:{"Form":"Lyophilized powder","Vial size":"3ml, 10mg","Purity (last lot)":"99.3%","Storage":"Refrigerate after reconstitution","Supply":"4-week protocol"},
    faqs:[["Semaglutide or tirzepatide?","Semaglutide is typically the gentler starting point. Our care team can help you decide."]]
  },
  {
    id:"retatrutide-15", stock:"in", name:"Retatrutide", label:["RETATRUTIDE","15MG"], strength:"15mg vial",
    category:"weight", subscribe:329, once:379, badge:"Premium", rating:4.9, reviews:87,
    tagline:"Next-generation triple-agonist support.",
    description:"A triple GIP/GLP-1/glucagon receptor agonist at the leading edge of metabolic research. Reserved for members with prior GLP-1 experience.",
    inside:"Retatrutide 15mg, lyophilized. Ships with bacteriostatic water and supplies.",
    specs:{"Form":"Lyophilized powder","Vial size":"3ml, 15mg","Purity (last lot)":"99.2%","Storage":"Refrigerate after reconstitution","Supply":"4-week protocol"},
    faqs:[]
  },
  {
    id:"nad-500", stock:"out", name:"NAD+", label:["NAD+","500MG"], strength:"500mg vial",
    category:"longevity", subscribe:129, once:149, rating:4.6, reviews:142,
    tagline:"Cellular energy for the long game.",
    description:"Nicotinamide adenine dinucleotide is a coenzyme central to cellular energy. Levels decline with age; our NAD+ is lyophilized for stability.",
    inside:"NAD+ 500mg, lyophilized.",
    specs:{"Form":"Lyophilized powder","Vial size":"5ml, 500mg","Purity (last lot)":"99.7%","Storage":"Refrigerate after reconstitution","Supply":"4-week protocol"},
    faqs:[]
  },
  {
    id:"mots-c", stock:"out", name:"MOTS-c", label:["MOTS-C","10MG"], strength:"10mg vial",
    category:"longevity", subscribe:119, once:139, rating:4.7, reviews:76,
    tagline:"The mitochondrial peptide.",
    description:"A mitochondrial-derived peptide studied for metabolic flexibility, exercise capacity, and healthy aging.",
    inside:"MOTS-c 10mg, lyophilized.",
    specs:{"Form":"Lyophilized powder","Vial size":"3ml, 10mg","Purity (last lot)":"99.1%","Storage":"Refrigerate after reconstitution","Supply":"4-week protocol"},
    faqs:[]
  },
  {
    id:"epithalon", stock:"out", name:"Epithalon", label:["EPITHALON","10MG"], strength:"10mg vial",
    category:"longevity", subscribe:109, once:129, rating:4.6, reviews:63,
    tagline:"Telomere support, four times a year.",
    description:"A synthetic tetrapeptide studied for telomerase activity and sleep regulation. Typically run as a 10–20 day cycle a few times a year.",
    inside:"Epithalon 10mg, lyophilized.",
    specs:{"Form":"Lyophilized powder","Vial size":"3ml, 10mg","Purity (last lot)":"99.4%","Storage":"Refrigerate after reconstitution","Supply":"1 cycle"},
    faqs:[]
  },
  {
    id:"tesamorelin", stock:"out", name:"Tesamorelin", label:["TESAMORELIN","5MG"], strength:"5mg vial",
    category:"weight", subscribe:139, once:169, rating:4.7, reviews:118,
    tagline:"Targets the stubborn middle.",
    description:"A growth-hormone-releasing hormone analog studied specifically for visceral (abdominal) fat and body composition.",
    inside:"Tesamorelin 5mg, lyophilized.",
    specs:{"Form":"Lyophilized powder","Vial size":"3ml, 5mg","Purity (last lot)":"99.3%","Storage":"Refrigerate after reconstitution","Supply":"4-week protocol"},
    faqs:[]
  },
  {
    id:"bac-water", stock:"in", name:"Bacteriostatic Water", label:["BAC WATER","10ML"], strength:"10ml vial",
    category:"supplies", subscribe:null, once:12, rating:4.9, reviews:640,
    tagline:"For reconstitution. One included with every protocol.",
    description:"Sterile water with 0.9% benzyl alcohol for reconstituting lyophilized peptides. One vial is included in every protocol order; grab extras here.",
    inside:"Bacteriostatic water 10ml.",
    specs:{"Form":"Liquid","Vial size":"10ml","Storage":"Room temperature","Supply":"—"},
    faqs:[]
  }
];

/* stock: "in" = purchasable, "out" = shows "Join the waitlist" instead of Add to cart */
window.LUMA_CATEGORIES = {
  all:"All", weight:"Weight & Metabolic", recovery:"Recovery", skin:"Skin & Glow", longevity:"Longevity & Sleep", supplies:"Supplies"
};

/* Demo COA lot database for the Verify page. Replace with a real lookup. */
window.LUMA_LOTS = {
  "LP-2609-TZ15":{product:"Tirzepatide 15mg",lab:"Freedom Diagnostics",tested:"2026-08-18",purity:"99.4%",identity:"Confirmed (LC-MS)",netContent:"15.1mg",endotoxin:"< 0.05 EU/mg",status:"PASS",expires:"2028-08"},
  "LP-2609-GLOW":{product:"Glow + Klow 5mg",lab:"Freedom Diagnostics",tested:"2026-08-18",purity:"99.0%",identity:"Confirmed (LC-MS)",netContent:"5.0mg",endotoxin:"< 0.05 EU/mg",status:"PASS",expires:"2028-08"},
  "LP-2608-GHK5":{product:"GHK-Cu 5mg",lab:"Freedom Diagnostics",tested:"2026-07-30",purity:"99.6%",identity:"Confirmed (HPLC)",netContent:"5.1mg",endotoxin:"< 0.05 EU/mg",status:"PASS",expires:"2028-07"},
  "LP-2608-BPC5":{product:"BPC-157 5mg",lab:"Freedom Diagnostics",tested:"2026-07-30",purity:"99.5%",identity:"Confirmed (HPLC)",netContent:"5.0mg",endotoxin:"< 0.05 EU/mg",status:"PASS",expires:"2028-07"}
};
