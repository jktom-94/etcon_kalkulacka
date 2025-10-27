
/**
 * grants_override_v3.js
 * Self-contained řešení: vytvoří vlastní UI dotací z /config/config.json,
 * skryje původní blok dotací (rádia), a udrží kompatibilitu:
 *  - vytvoří skrytý <input type="radio" name="grant" id="grant_hidden">
 *  - tento input je vždy checked a nese value = vybraná dotace (Kč)
 *  - nastavuje také hidden #grantAmountInput a #grantNameInput pokud existují
 *  - vysílá CustomEvent 'grant-change' s {amount, name, index}
 */
(function(){
  const STYLE_ID = 'grants-override-style';
  const MOUNT_ID = 'grants-override-mount';
  function css(s){ const st=document.createElement('style'); st.id=STYLE_ID; st.textContent=s; document.head.appendChild(st); }
  function $(sel,root=document){ return root.querySelector(sel); }
  function $all(sel,root=document){ return Array.from(root.querySelectorAll(sel)); }
  function formatCZK(n){ return Number(n||0).toLocaleString('cs-CZ') + ' Kč'; }
  function mountBefore(el){ const wrap=document.createElement('div'); wrap.id=MOUNT_ID; el.parentNode.insertBefore(wrap, el); return wrap; }

  const baseCSS = `
    #${MOUNT_ID}{margin:12px 0}
    .gogrid{display:grid;gap:12px;grid-template-columns:repeat(3,minmax(0,1fr))}
    @media(max-width:980px){.gogrid{grid-template-columns:1fr}}
    .gocard{display:flex;flex-direction:column;gap:8px;padding:16px;border-radius:16px;
            border:1px solid #1d274a;background:#0f1530;color:#e8eefc;text-align:left;cursor:pointer}
    .gocard.sel{outline:3px solid #5ea1ff}
    .gopill{margin-left:auto;padding:6px 10px;border-radius:999px;background:#0c2548;border:1px solid #1e3666;color:#cfe2ff;font-weight:700;white-space:nowrap}
    .gohead{font-weight:800;font-size:1.08rem;line-height:1.25}
    .gosub{color:#b7c3e0}
    /* Skryj původní rádia/boxy dotací (bezpečně) */
    .orig-grants-hidden [type="radio"][name="grant"], .orig-grants-hidden label[for]{display:none !important}
  `;

  async function getConfig(){
    try{
      const r=await fetch('/config/config.json?ts=' + Date.now(), {cache:'no-store'});
      if(!r.ok) throw new Error('HTTP '+r.status);
      return await r.json();
    }catch(e){
      console.warn('[grants_override] nemohu načíst config.json, použiji výchozí:', e);
      return {grants:[
        {name:'Dotace s chytrým řízením', amount:140000},
        {name:'Dotace bez chytrého řízení', amount:100000}
      ]};
    }
  }

  function findHeadingNode(){
    // hledej typické nadpisy
    const candidates = Array.from(document.querySelectorAll('h2,h3,h4'))
      .filter(n=>/zvolte.*(režim|rezim).*podpory/i.test(n.textContent));
    if (candidates.length) return candidates[0];
    // fallback: první prvek v main/section/form/body
    return document.querySelector('main,section,form') || document.body;
  }

  function buildUI(list){
    let mount = document.getElementById(MOUNT_ID);
    if (!mount){
      const head = findHeadingNode();
      mount = head ? mountBefore(head) : (function(){ const d=document.createElement('div'); d.id=MOUNT_ID; document.body.prepend(d); return d; })();
    }
    mount.innerHTML = `
      <div class="gogrid"></div>
      <input type="radio" name="grant" id="grant_hidden" style="position:absolute;left:-9999px" checked value="0">
      <input type="hidden" id="grantAmountInput" value="0">
      <input type="hidden" id="grantNameInput" value="">
    `;
    const grid = mount.querySelector('.gogrid');

    list.forEach((g, i)=>{
      const card = document.createElement('button');
      card.type='button';
      card.className = 'gocard' + (i===0?' sel':'');
      card.innerHTML = `
        <div class="gohead">${g.name}</div>
        <div class="gopill">${formatCZK(g.amount)}</div>
        <div class="gosub">${g.amount>0?('Strop podpory ' + formatCZK(g.amount) + '.'):'Bez uplatnění podpory.'}</div>
      `;
      card.addEventListener('click', ()=> selectGrant(i, list));
      grid.appendChild(card);
    });

    // skryj původní rádia na stránce (pokud existují – jen přidáme class na rodiče body)
    document.body.classList.add('orig-grants-hidden');

    // default select první
    selectGrant(0, list);
  }

  function selectGrant(index, list){
    const amount = Number(list[index].amount||0);
    const name   = String(list[index].name||'');

    // vizuální stav
    Array.from(document.querySelectorAll('.gocard')).forEach((el,i)=> el.classList.toggle('sel', i===index));

    // skrytý radio + hiddeny
    const r = document.getElementById('grant_hidden'); if (r){ r.value = String(amount); r.checked = true; r.dispatchEvent(new Event('change', {bubbles:true})); }
    const a = document.getElementById('grantAmountInput'); if (a) a.value = String(amount);
    const n = document.getElementById('grantNameInput');   if (n) n.value = name;

    // informuj app
    if (typeof window.recomputeTotals === 'function'){ try{ window.recomputeTotals({grantAmount: amount}); }catch(e){} }
    else if (typeof window.recalcTotals === 'function'){ try{ window.recalcTotals(); }catch(e){} }

    document.dispatchEvent(new CustomEvent('grant-change', {detail:{amount,name,index}}));
  }

  async function init(){
    if (!document.getElementById(STYLE_ID)) css(baseCSS);
    const cfg = await getConfig();
    const grants = Array.isArray(cfg.grants) ? cfg.grants.slice() : [];
    // přidej "nechci čerpat"
    if (!grants.some(g=>String(g.amount||0)==='0')) grants.push({name:'Nechci čerpat dotaci', amount:0});
    buildUI(grants);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
