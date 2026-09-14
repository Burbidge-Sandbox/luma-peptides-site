<?php
/**
 * Catalog — port of legacy shop.html + js/shop.js. Grid is rendered client-side
 * from the Woo catalogue (LUMA_PRODUCTS) so the cards are identical.
 */
$u = luma_catalogue_json()['config']['urls'];
get_header();
?>
<main id="main">
<div class="wrap page-head">
 <div class="breadcrumb"><a href="<?php echo esc_url( $u['home'] ); ?>">Home</a> › Catalog</div>
 <h1 id="shopTitle">All compounds</h1>
 <p>Lyophilized research peptides, sold per vial with volume pricing. Every lot independently tested; every certificate published. For laboratory research use only.</p>
</div>
<div class="wrap">
 <div class="shop-toolbar">
  <div class="chips" id="chips" role="group" aria-label="Filter by category"></div>
  <label class="sr-only" for="sort">Sort</label>
  <select id="sort"><option value="featured">Featured</option><option value="low">Price: low to high</option><option value="high">Price: high to low</option></select>
  <span class="result-count" id="count"></span>
 </div>
 <div class="grid grid-4" id="shopGrid" style="padding-bottom:5rem"></div>
</div>
<script>
document.addEventListener("DOMContentLoaded",()=>{
  const P=window.LUMA_PRODUCTS, C=window.LUMA_CATEGORIES;
  const grid=document.getElementById("shopGrid"), chips=document.getElementById("chips"), sort=document.getElementById("sort"), count=document.getElementById("count");
  const params=new URLSearchParams(location.search);
  let cat=params.get("cat")||"all", order=params.get("sort")||"featured";
  let inOnly=params.get("stock")==="in";
  chips.innerHTML=Object.entries(C).map(([k,v])=>`<button class="chip" data-cat="${k}" aria-pressed="${k===cat}">${v}</button>`).join("")+`<button class="chip chip-stock" id="stockChip" aria-pressed="${inOnly}">In stock only</button>`;
  document.getElementById("stockChip").addEventListener("click",e=>{inOnly=!inOnly; e.currentTarget.setAttribute("aria-pressed",inOnly); draw();});
  sort.value=order;
  function draw(){
    let list=P.filter(p=>(cat==="all"||p.category===cat)&&(!inOnly||p.stock!=="out"));
    list.sort((a,b)=>(a.stock==="out")-(b.stock==="out"));
    const price=p=>p.once;
    if(order==="low") list.sort((a,b)=>price(a)-price(b));
    if(order==="high") list.sort((a,b)=>price(b)-price(a));
    grid.innerHTML=list.length?list.map(productCard).join(""):`<div class="empty">Nothing here yet.</div>`;
    grid.querySelectorAll(".reveal").forEach(el=>el.classList.add("in"));
    count.textContent=`${list.length} compound${list.length===1?"":"s"}`;
    const u=new URL(location); cat==="all"?u.searchParams.delete("cat"):u.searchParams.set("cat",cat); order==="featured"?u.searchParams.delete("sort"):u.searchParams.set("sort",order); inOnly?u.searchParams.set("stock","in"):u.searchParams.delete("stock"); history.replaceState(null,"",u);
    document.getElementById("shopTitle").textContent = cat==="all"?"All compounds":(C[cat]||"All compounds");
  }
  chips.addEventListener("click",e=>{const b=e.target.closest(".chip[data-cat]"); if(!b) return; cat=b.dataset.cat; chips.querySelectorAll(".chip[data-cat]").forEach(c=>c.setAttribute("aria-pressed",c===b)); draw();});
  sort.addEventListener("change",()=>{order=sort.value; draw();});
  draw();
});
</script>
</main>
<?php get_footer(); ?>
