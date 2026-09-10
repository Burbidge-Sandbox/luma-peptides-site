/* ============================================================
   Compliant catalog (variant B).
   Rules applied: compound + mass naming; third-person study language;
   no outcomes, no "you", no dosing, no supply/protocol/month; one-time
   per-vial pricing with volume tiers; GLP-1 compounds coded; research-
   neutral category names. Prices carried over from variant A.
   ============================================================ */
window.LUMA_PRODUCTS = [
  {
    id:"glp-2-t", stock:"in", name:"GLP-2 T", label:["GLP-2 T","15MG"], strength:"15mg vial",
    variants:[{key:"15mg",label:"15mg",strength:"15mg vial",once:349,stock:"in",sku:"TR15"},{key:"30mg",label:"30mg",strength:"30mg vial",once:459,stock:"in",sku:"TR30"}],
    category:"metabolic", once:349, subscribe:null, badge:null,
    tagline:"Dual GIP/GLP-1 receptor agonist analog. Lyophilized.",
    description:"A synthetic 39-amino-acid peptide analog that has been characterized in the literature as a dual agonist at the GIP and GLP-1 receptors. Preclinical studies have examined receptor binding kinetics, incretin signaling pathways, and downstream effects on glucose homeostasis in rodent models. Supplied as a lyophilized powder for in-vitro and analytical research.",
    inside:"Single lyophilized peptide, sealed under inert gas. No excipients other than mannitol.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.4%","Identity":"LC-MS confirmed","Storage":"−20 °C, desiccated, protected from light","CAS":"2023788-19-2"},
    faqs:[]
  },
  {
    id:"glp-3-rt", stock:"in", name:"GLP-3 RT", label:["GLP-3 RT","15MG"], strength:"15mg vial",
    variants:[{key:"15mg",label:"15mg",strength:"15mg vial",once:379,stock:"in",sku:"RT15"},{key:"30mg",label:"30mg",strength:"30mg vial",once:519,stock:"in",sku:"RT30"}],
    category:"metabolic", once:379, subscribe:null, badge:null,
    tagline:"Triple GIP/GLP-1/glucagon receptor agonist analog. Lyophilized.",
    description:"A synthetic peptide analog described in the literature as an agonist at the GIP, GLP-1, and glucagon receptors. Published preclinical work has investigated receptor selectivity, energy expenditure pathways, and hepatic lipid signaling in animal models. Supplied as a lyophilized powder for in-vitro and analytical research.",
    inside:"Single lyophilized peptide, sealed under inert gas.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.2%","Identity":"LC-MS confirmed","Storage":"−20 °C, desiccated, protected from light","CAS":"2381089-83-2"},
    faqs:[]
  },
  {
    id:"glp-1-sm", stock:"out", name:"GLP-1 SM", label:["GLP-1 SM","10MG"], strength:"10mg vial",
    category:"metabolic", once:239, subscribe:null,
    tagline:"Long-acting GLP-1 receptor agonist analog. Lyophilized.",
    description:"A synthetic acylated GLP-1 analog that has been studied for receptor binding affinity, albumin association, and extended half-life in preclinical pharmacokinetic models. Supplied as a lyophilized powder for in-vitro and analytical research.",
    inside:"Single lyophilized peptide, sealed under inert gas.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.3%","Identity":"LC-MS confirmed","Storage":"−20 °C, desiccated, protected from light","CAS":"910463-68-2"},
    faqs:[]
  },
  {
    id:"tesamorelin", stock:"out", name:"Tesamorelin", label:["TESAMORELIN","5MG"], strength:"5mg vial",
    category:"metabolic", once:169, subscribe:null, image:"assets/products/tesamorelin", imageFocus:"50% 55%",
    tagline:"Stabilized GHRH (1-44) analog. Lyophilized.",
    description:"A synthetic analog of growth-hormone-releasing hormone with an N-terminal trans-3-hexenoic acid modification. Published research has examined its stability against dipeptidyl peptidase cleavage and its activity at the GHRH receptor in cell-based assays. Supplied as a lyophilized powder for in-vitro research.",
    inside:"Single lyophilized peptide, sealed under inert gas.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.3%","Identity":"LC-MS confirmed","Storage":"−20 °C, desiccated, protected from light","CAS":"218949-48-5"},
    faqs:[]
  },
  {
    id:"bpc-157", stock:"in", name:"BPC-157", label:["BPC-157","10MG"], strength:"10mg vial",
    category:"tissue", once:109, subscribe:null, image:"assets/products/bpc-157", imageFocus:"50% 55%",
    tagline:"Pentadecapeptide, gastric-juice derived sequence. Lyophilized.",
    description:"A 15-amino-acid synthetic peptide with a sequence derived from a protein found in gastric juice. Rodent studies have investigated its influence on angiogenic signaling, nitric-oxide pathway modulation, and tendon fibroblast migration under controlled conditions. Supplied as a lyophilized powder for in-vitro research.",
    inside:"Single lyophilized peptide, sealed under inert gas.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.5%","Identity":"LC-MS confirmed","Storage":"−20 °C, desiccated, protected from light","CAS":"137525-51-0"},
    faqs:[]
  },
  {
    id:"wolverine-stack", stock:"out", name:"BPC-157 + TB-500 Blend", label:["BPC-157 +","TB-500 BLEND"], strength:"5mg + 5mg vial",
    category:"tissue", once:139, subscribe:null, image:"assets/products/wolverine-stack", imageFocus:"50% 55%",
    tagline:"Co-lyophilized pentadecapeptide and thymosin β4 fragment.",
    description:"A co-lyophilized preparation of BPC-157 and TB-500, a synthetic fragment of thymosin β4. The two peptides have been studied separately in animal models for angiogenic signaling and actin-sequestration activity; the blend is supplied for researchers characterizing the compounds in combination. Lyophilized powder for in-vitro research.",
    inside:"Two co-lyophilized peptides, 5mg each, sealed under inert gas.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.1%","Identity":"LC-MS confirmed, both components","Storage":"−20 °C, desiccated, protected from light"},
    faqs:[]
  },
  {
    id:"cjc-ipamorelin", stock:"out", name:"CJC-1295 (no DAC) + Ipamorelin Blend", label:["CJC-1295 +","IPAMORELIN"], strength:"2.5mg + 2.5mg vial",
    category:"longevity", once:139, subscribe:null, image:"assets/products/cjc-ipamorelin", imageFocus:"50% 55%",
    tagline:"Co-lyophilized GHRH analog and GHS-R agonist.",
    description:"A co-lyophilized preparation of a modified GHRH (1-29) analog and the pentapeptide ipamorelin, a selective ghrelin-receptor agonist. Each has been studied in cell-based and animal models for growth-hormone secretagogue activity. Supplied for researchers characterizing the compounds in combination. Lyophilized powder for in-vitro research.",
    inside:"Two co-lyophilized peptides, 2.5mg each, sealed under inert gas.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.2%","Identity":"LC-MS confirmed, both components","Storage":"−20 °C, desiccated, protected from light"},
    faqs:[]
  },
  {
    id:"ghk-cu", stock:"out", name:"GHK-Cu", label:["GHK-CU","5MG"], strength:"5mg vial",
    category:"dermal", once:119, subscribe:null, image:"assets/products/ghk-cu", imageFocus:"50% 55%",
    tagline:"Copper tripeptide complex. Lyophilized.",
    description:"A naturally occurring tripeptide (glycyl-L-histidyl-L-lysine) complexed with copper(II). In-vitro studies have examined its effects on collagen and glycosaminoglycan synthesis in fibroblast cultures and its role in copper transport. Supplied as a lyophilized powder for in-vitro research.",
    inside:"Single lyophilized copper-peptide complex, sealed under inert gas.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.6%","Identity":"LC-MS confirmed","Storage":"−20 °C, desiccated, protected from light","CAS":"89030-95-5"},
    faqs:[]
  },
  {
    id:"glow-klow", stock:"in", name:"BPC-157 + GHK-Cu + TB-500 Blend", label:["BPC + GHK-CU","+ TB-500"], strength:"5mg blend vial",
    category:"dermal", once:179, subscribe:null, image:"assets/products/glow-klow", imageFocus:"50% 55%",
    tagline:"Three-peptide co-lyophilized preparation.",
    description:"A co-lyophilized preparation of BPC-157, the copper tripeptide GHK-Cu, and TB-500. Each component has been independently characterized in the literature; the blend is supplied for researchers studying the compounds together in cell-based assays. Lyophilized powder for in-vitro research.",
    inside:"Three co-lyophilized peptides, sealed under inert gas.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.0%","Identity":"LC-MS confirmed, all components","Storage":"−20 °C, desiccated, protected from light"},
    faqs:[]
  },
  {
    id:"nad-500", stock:"out", name:"NAD+", label:["NAD+","500MG"], strength:"500mg vial",
    category:"longevity", once:149, subscribe:null, image:"assets/products/nad-500", imageFocus:"50% 55%",
    tagline:"Nicotinamide adenine dinucleotide, oxidized form. Lyophilized.",
    description:"A coenzyme present in all living cells and central to redox reactions and sirtuin activity. Widely used as a reagent in enzymology and cell-metabolism assays. Supplied as a lyophilized powder for in-vitro research.",
    inside:"Lyophilized NAD+ disodium salt.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.7%","Identity":"LC-MS confirmed","Storage":"−20 °C, desiccated, protected from light","CAS":"53-84-9"},
    faqs:[]
  },
  {
    id:"mots-c", stock:"out", name:"MOTS-c", label:["MOTS-C","10MG"], strength:"10mg vial",
    category:"longevity", once:139, subscribe:null,
    tagline:"Mitochondrial-derived 16-amino-acid peptide. Lyophilized.",
    description:"A peptide encoded within the mitochondrial 12S rRNA gene. Published research has investigated its role in AMPK signaling and folate-methionine metabolism in cell culture and rodent models. Supplied as a lyophilized powder for in-vitro research.",
    inside:"Single lyophilized peptide, sealed under inert gas.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.1%","Identity":"LC-MS confirmed","Storage":"−20 °C, desiccated, protected from light","CAS":"1627580-64-6"},
    faqs:[]
  },
  {
    id:"epithalon", stock:"out", name:"Epithalon", label:["EPITHALON","10MG"], strength:"10mg vial",
    category:"longevity", once:129, subscribe:null,
    tagline:"Synthetic tetrapeptide Ala-Glu-Asp-Gly. Lyophilized.",
    description:"A synthetic tetrapeptide originally derived from a pineal extract. In-vitro studies have examined its effect on telomerase expression in human fibroblast cultures. Supplied as a lyophilized powder for in-vitro research.",
    inside:"Single lyophilized peptide, sealed under inert gas.",
    specs:{"Form":"Lyophilized powder","Purity (last lot, HPLC)":"99.4%","Identity":"LC-MS confirmed","Storage":"−20 °C, desiccated, protected from light","CAS":"307297-39-8"},
    faqs:[]
  },
  {
    id:"bac-water", stock:"in", name:"Bacteriostatic Water", label:["BAC WATER","10ML"], strength:"10ml vial",
    category:"supplies", once:10, subscribe:null,
    tagline:"Sterile water, 0.9% benzyl alcohol. Laboratory solvent.",
    description:"Sterile water containing 0.9% benzyl alcohol as a bacteriostatic preservative. A general-purpose laboratory solvent for preparing peptide solutions for analytical work. Sold separately; not included with any peptide.",
    inside:"10ml sterile water, 0.9% benzyl alcohol.",
    specs:{"Form":"Liquid","Volume":"10ml","Storage":"Room temperature"},
    faqs:[]
  }
];

/* Research-neutral category names */
window.LUMA_CATEGORIES = { all:"All compounds", metabolic:"Metabolic Research", tissue:"Tissue Research", dermal:"Dermal Research", longevity:"Longevity Research", supplies:"Lab Supplies" };

/* Volume pricing tiers applied at cart level (per line quantity) */
window.LUMA_VOLUME_TIERS = [{min:3,pct:5},{min:5,pct:10},{min:10,pct:15}];

window.LUMA_LOTS = {
  "LP-2609-TZ15":{product:"GLP-2 T 15mg",lab:"Freedom Diagnostics",tested:"2026-08-18",purity:"99.4%",identity:"Confirmed (LC-MS)",netContent:"15.1mg",endotoxin:"< 0.05 EU/mg",status:"PASS",expires:"2028-08"},
  "LP-2609-GLOW":{product:"BPC-157 + GHK-Cu + TB-500 Blend 5mg",lab:"Freedom Diagnostics",tested:"2026-08-18",purity:"99.0%",identity:"Confirmed (LC-MS)",netContent:"5.0mg",endotoxin:"< 0.05 EU/mg",status:"PASS",expires:"2028-08"},
  "LP-2608-GHK5":{product:"GHK-Cu 5mg",lab:"Freedom Diagnostics",tested:"2026-07-30",purity:"99.6%",identity:"Confirmed (HPLC)",netContent:"5.1mg",endotoxin:"< 0.05 EU/mg",status:"PASS",expires:"2028-07"},
  "LP-2608-BPC5":{product:"BPC-157 10mg",lab:"Freedom Diagnostics",tested:"2026-07-30",purity:"99.5%",identity:"Confirmed (HPLC)",netContent:"10.0mg",endotoxin:"< 0.05 EU/mg",status:"PASS",expires:"2028-07"}
};
