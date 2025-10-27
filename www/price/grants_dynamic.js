
// Dynamic grants loader for price_result v7+
(function(){
  let grantDefs = [];
  let selectedGrantIndex = 0;
  let selectedGrantAmount = 0;

  function formatCZK(x){ return Number(x||0).toLocaleString('cs-CZ') + ' Kč'; }
  function escapeHTML(s){ return (s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  async function loadConfigJSON() {
    try {
      const res = await fetch('/config/config.json?ts=' + Date.now(), {cache:'no-store'});
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return await res.json();
    } catch(e){
      console.warn('Nepodařilo se načíst /config/config.json, použiji výchozí dotace.', e);
      return null;
    }
  }

  function renderGrants(root){
    root.innerHTML = '';
    grantDefs.forEach((g,i)=>{
      const btn=document.createElement('button');
      btn.type='button';
      btn.className='grant-card'+(i===selectedGrantIndex?' selected':'');
      btn.setAttribute('data-amount', String(g.amount));
      btn.innerHTML = `
        <div class="gc-title">${escapeHTML(g.name)}</div>
        <div class="gc-pill">${formatCZK(g.amount)}</div>
        <div class="gc-sub">${g.amount>0?('Strop podpory '+formatCZK(g.amount)+'.'):'Bez uplatnění podpory.'}</div>
      `;
      btn.addEventListener('click', ()=> selectGrant(i, root));
      root.appendChild(btn);
    });
    selectGrant(0, root);
  }

  function selectGrant(index, root){
    selectedGrantIndex = index;
    selectedGrantAmount = Number(grantDefs[index].amount||0);
    // UI
    root.querySelectorAll('.grant-card').forEach((el,i)=>{
      el.classList.toggle('selected', i===index);
    });
    // Informuj zbytek aplikace
    if (typeof window.recomputeTotals === 'function'){
      window.recomputeTotals({ grantAmount: selectedGrantAmount });
    } else if (typeof window.recalcTotals === 'function'){
      try { window.recalcTotals(); } catch(e){}
    }
    // Event pro libovolný listener
    document.dispatchEvent(new CustomEvent('grant-change', {detail:{amount:selectedGrantAmount,index}}));
  }

  async function init(){
    const host = document.getElementById('grantOptions');
    if (!host){ console.warn('grantOptions container nenalezen'); return; }

    const cfg = await loadConfigJSON();
    if (cfg && Array.isArray(cfg.grants) && cfg.grants.length){
      grantDefs = cfg.grants.map(g=>({name:String(g.name||'Dotace').trim(), amount:Number(g.amount||0)}));
    } else {
      grantDefs = [
        {name:'Dotace s chytrým řízením', amount:140000},
        {name:'Dotace bez chytrého řízení', amount:100000}
      ];
    }
    grantDefs.push({name:'Nechci čerpat dotaci', amount:0});
    renderGrants(host);
  }

  document.addEventListener('DOMContentLoaded', init);
})();
