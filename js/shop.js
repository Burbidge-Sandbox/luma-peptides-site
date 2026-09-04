/* Shop page: filter chips, sort, URL state */
(function(){
  const P=window.LUMA_PRODUCTS, C=window.LUMA_CATEGORIES;
  const grid=document.getElementById("shopGrid"), chips=document.getElementById("chips"), sort=document.getElementById("sort"), count=document.getElementById("count");
  const params=new URLSearchParams(location.search);
  let cat=params.get("cat")||"all", order=params.get("sort")||"featured";
  chips.innerHTML=Object.entries(C).map(([k,v])=>`<button class="chip" data-cat="${k}" aria-pressed="${k===cat}">${v}</button>`).join("");
  sort.value=order;
  function draw(){
    let list=P.filter(p=>cat==="all"||p.category===cat);
    const price=p=>p.subscribe||p.once;
    if(order==="low") list.sort((a,b)=>price(a)-price(b));
    if(order==="high") list.sort((a,b)=>price(b)-price(a));
    if(order==="rating") list.sort((a,b)=>b.rating-a.rating);
    grid.innerHTML=list.length?list.map(productCard).join(""):`<div class="empty">Nothing here yet.</div>`;
    grid.querySelectorAll(".reveal").forEach(el=>el.classList.add("in"));
    count.textContent=`${list.length} treatment${list.length===1?"":"s"}`;
    const u=new URL(location); cat==="all"?u.searchParams.delete("cat"):u.searchParams.set("cat",cat); order==="featured"?u.searchParams.delete("sort"):u.searchParams.set("sort",order); history.replaceState(null,"",u);
    document.getElementById("shopTitle").textContent = cat==="all"?"All Treatments":C[cat];
  }
  chips.addEventListener("click",e=>{const b=e.target.closest(".chip"); if(!b) return; cat=b.dataset.cat; chips.querySelectorAll(".chip").forEach(c=>c.setAttribute("aria-pressed",c===b)); draw();});
  sort.addEventListener("change",()=>{order=sort.value; draw();});
  draw();
})();
