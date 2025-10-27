<?php
// config_admin_v7.php (viz popis v předchozí zprávě)
$CONFIG_PATH = __DIR__ . '/../config/config.json';
$HISTORY_DIR = __DIR__ . '/../config/history';
$ADMIN_PASS = getenv('FVE_ADMIN_PASS') ?: 'Heslo';
session_start();
if (isset($_POST['login_pass'])) { $_SESSION['auth'] = ($_POST['login_pass'] === $ADMIN_PASS); }
if (isset($_GET['logout'])) { session_destroy(); header("Location: " . strtok($_SERVER["REQUEST_URI"],'?')); exit; }
function load_config($p){ if(!file_exists($p)) return []; $t=file_get_contents($p); $j=json_decode($t,true); return is_array($j)?$j:[]; }
function save_config($p,$d,$h){ if(!is_dir(dirname($p))) mkdir(dirname($p),0775,true); if($h && !is_dir($h)) mkdir($h,0775,true);
  $j=json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); if($h){ $stamp=date('Y-m-d_His'); file_put_contents($h . "/config_$stamp.json",$j);} file_put_contents($p,$j); }
function num($a,$k,$def=0){ return isset($a[$k]) && is_numeric($a[$k]) ? floatval($a[$k]) : $def; }
$cfg = load_config($CONFIG_PATH);
$cfg += [
  'unit'=>['panel'=>1800,'rack'=>850,'laborPerPanel'=>1100,'inverter'=>25000,'bms'=>8500,'acdc'=>17000,'rev'=>15000,'stop'=>2500,'misc'=>10000,'disconnector'=>800,'batModule'=>14000,'marginPct'=>38,'vatPct'=>12,'electricianHour'=>600,'electricianHours'=>38,'wallboxAddPrice'=>25000,'wallboxGrant'=>10000,'batModuleKWh'=>3.55,'panelWp'=>500],
  'grants'=>[['name'=>'Dotace s chytrým řízením / sdílením','amount'=>140000],['name'=>'Dotace bez chytrého řízení','amount'=>100000]],
  'model'=>['seasonShares'=>['zima'=>0.10,'jaro'=>0.25,'leto'=>0.45,'podzim'=>0.20],'selfConsumption'=>['zima'=>0.95,'jaro'=>0.75,'leto'=>0.55,'podzim'=>0.70],'panelDegradation'=>0.004,'defaultBuyPrice'=>1.00]
];
if (isset($_POST['save_unit']) && !empty($_SESSION['auth'])){
  foreach ($cfg['unit'] as $k=>$v) $cfg['unit'][$k]=num($_POST,$k,$v);
  save_config($CONFIG_PATH,$cfg,$HISTORY_DIR); $msg="Ceník uložen.";
}
if (isset($_POST['save_grants']) && !empty($_SESSION['auth'])){
  $names=$_POST['grant_name']??[]; $amts=$_POST['grant_amount']??[]; $gr=[];
  for($i=0;$i<count($names);$i++){ $n=trim($names[$i]); $a=is_numeric($amts[$i])?floatval($amts[$i]):0; if($n!=='') $gr[]=['name'=>$n,'amount'=>$a]; }
  $cfg['grants']=$gr; save_config($CONFIG_PATH,$cfg,$HISTORY_DIR); $msg="Dotace uloženy.";
}
if (isset($_POST['save_model']) && !empty($_SESSION['auth'])){
  $m=$cfg['model'];
  foreach(['zima','jaro','leto','podzim'] as $s){ $m['seasonShares'][$s]=num($_POST,'share_'.$s,$m['seasonShares'][$s]); }
  $sum = array_sum($m['seasonShares']); if ($sum>0){ foreach($m['seasonShares'] as $k=>$v){ $m['seasonShares'][$k]=$v/$sum; } }
  foreach(['zima','jaro','leto','podzim'] as $s){ $m['selfConsumption'][$s]=num($_POST,'sc_'.$s,$m['selfConsumption'][$s]); }
  $m['panelDegradation']=num($_POST,'panel_deg',$m['panelDegradation']);
  $m['defaultBuyPrice']=num($_POST,'default_buy',$m['defaultBuyPrice']);
  $cfg['model']=$m; save_config($CONFIG_PATH,$cfg,$HISTORY_DIR); $msg="Model uložen.";
}
function calc_price_total($cfg,$kwp,$bmods,$wb){
  $U=$cfg['unit']; $panels=ceil($kwp/($U['panelWp']/1000)); $disc=ceil($panels/2); $batKwh=$bmods*$U['batModuleKWh'];
  $base=$panels*($U['panel']+$U['rack']+$U['laborPerPanel'])+$disc*$U['disconnector']+$U['inverter']+$U['bms']+$U['acdc']+$U['rev']+$U['stop']+$U['misc']+$bmods*$U['batModule']+$U['electricianHours']*$U['electricianHour'];
  $withMargin=$base*(1+$U['marginPct']/100); $withVat=round($withMargin*(1+$U['vatPct']/100)); if($wb) $withVat+=$U['wallboxAddPrice']; return [$withVat,$batKwh,$panels];
}
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin – ceník, dotace a model</title>
<style>:root{--bg:#0b1020;--card:#111830;--ink:#e8eefc;--muted:#b7c3e0;--line:#1d274a}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1100px;margin:22px auto;padding:0 16px}.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px;margin:12px 0}label{display:block;margin:8px 0 4px}input[type=text],input[type=number],input[type=password]{width:100%;background:#0d1430;border:1px solid #1f2a4d;border-radius:10px;color:#e8eefc;padding:10px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.row{display:flex;gap:10px;flex-wrap:wrap}button{background:#0c2548;border:1px solid #1e3666;color:#cfe2ff;border-radius:10px;padding:10px 14px;cursor:pointer}.pill{display:inline-block;background:#0c2548;border:1px solid #1e3666;color:#cfe2ff;border-radius:999px;padding:6px 10px;margin:6px 0}.right{display:flex;gap:8px;align-items:center;justify-content:flex-end}@media(max-width:980px){.grid,.grid-3{grid-template-columns:1fr}}</style>
</head><body><div class="wrap">
<?php if (empty($_SESSION['auth'])): ?>
  <div class="card"><h1>Přihlášení</h1>
    <form method="post"><label>Heslo</label><input type="password" name="login_pass"><div class="right"><button type="submit">Přihlásit</button></div></form>
  </div>
<?php else: ?>
  <div class="right"><a href="?logout=1" class="pill">Odhlásit</a></div>
  <h1>Admin – nastavení</h1>
  <?php if(isset($msg)) echo '<div class="pill">'.$msg.'</div>'; ?>

  <div class="card"><h2>Nastavení cen FVE</h2>
    <form method="post"><div class="grid">
      <?php foreach ($cfg['unit'] as $k=>$v): $val=htmlspecialchars($v,ENT_QUOTES,'UTF-8'); ?>
        <div><label><?= $k ?></label><input type="number" step="0.01" name="<?= $k ?>" value="<?= $val ?>"></div>
      <?php endforeach; ?>
    </div><div class="right"><button type="submit" name="save_unit">Uložit ceník</button></div></form>

    <hr style="border-color:#1d274a;margin:12px 0">
    <h3>Simulace ceny</h3>
    <div class="grid-3">
      <div><label>Výkon FVE (kWp)</label><input id="sim_kwp" type="number" step="0.5" value="5.0"></div>
      <div><label>Počet bat. modulů</label><input id="sim_bmods" type="number" step="1" value="2"></div>
      <div><label>Wallbox</label><input id="sim_wb" type="checkbox"></div>
    </div>
    <div class="row"><button type="button" onclick="simCalc()">Spočítat</button><div class="pill" id="sim_out">—</div></div>
  </div>

  <div class="card"><h2>Dotace</h2>
    <form method="post"><div id="grants_list">
      <?php foreach ($cfg['grants'] as $g): $n=htmlspecialchars($g['name'],ENT_QUOTES,'UTF-8'); $a=htmlspecialchars($g['amount'],ENT_QUOTES,'UTF-8'); ?>
        <div class="grid-3"><div><label>Název</label><input type="text" name="grant_name[]" value="<?= $n ?>"></div>
        <div><label>Výše (Kč)</label><input type="number" step="1" name="grant_amount[]" value="<?= $a ?>"></div>
        <div class="right"><button type="button" onclick="this.closest('.grid-3').remove()">Smazat</button></div></div>
      <?php endforeach; ?>
    </div>
    <div class="row"><button type="button" onclick="addGrant()">Přidat dotaci</button><div class="right" style="margin-left:auto"><button type="submit" name="save_grants">Uložit dotace</button></div></div>
    </form>
  </div>

  <div class="card"><h2>Model výpočtu</h2>
    <form method="post">
      <h3>Rozpad roční výroby (podíly 0–1)</h3>
      <div class="grid-3">
        <div><label>Zima</label><input type="number" step="0.01" name="share_zima" value="<?= htmlspecialchars($cfg['model']['seasonShares']['zima']) ?>"></div>
        <div><label>Jaro</label><input type="number" step="0.01" name="share_jaro" value="<?= htmlspecialchars($cfg['model']['seasonShares']['jaro']) ?>"></div>
        <div><label>Léto</label><input type="number" step="0.01" name="share_leto" value="<?= htmlspecialchars($cfg['model']['seasonShares']['leto']) ?>"></div>
      </div>
      <div class="grid-3">
        <div><label>Podzim</label><input type="number" step="0.01" name="share_podzim" value="<?= htmlspecialchars($cfg['model']['seasonShares']['podzim']) ?>"></div>
      </div>
      <h3>Vlastní spotřeba SC (0–1)</h3>
      <div class="grid-3">
        <div><label>Zima SC</label><input type="number" step="0.01" name="sc_zima" value="<?= htmlspecialchars($cfg['model']['selfConsumption']['zima']) ?>"></div>
        <div><label>Jaro SC</label><input type="number" step="0.01" name="sc_jaro" value="<?= htmlspecialchars($cfg['model']['selfConsumption']['jaro']) ?>"></div>
        <div><label>Léto SC</label><input type="number" step="0.01" name="sc_leto" value="<?= htmlspecialchars($cfg['model']['selfConsumption']['leto']) ?>"></div>
      </div>
      <div class="grid-3">
        <div><label>Podzim SC</label><input type="number" step="0.01" name="sc_podzim" value="<?= htmlspecialchars($cfg['model']['selfConsumption']['podzim']) ?>"></div>
      </div>
      <h3>Ostatní</h3>
      <div class="grid-3">
        <div><label>Degradace panelů (např. 0.004)</label><input type="number" step="0.0001" name="panel_deg" value="<?= htmlspecialchars($cfg['model']['panelDegradation']) ?>"></div>
        <div><label>Výkupní cena přetoků – výchozí (Kč/kWh)</label><input type="number" step="0.01" name="default_buy" value="<?= htmlspecialchars($cfg['model']['defaultBuyPrice']) ?>"></div>
      </div>
      <div class="right"><button type="submit" name="save_model">Uložit model</button></div>
    </form>
  </div>
<?php endif; ?>
</div>
<script>
function addGrant(){const host=document.getElementById('grants_list');const row=document.createElement('div');row.className='grid-3';row.innerHTML="<div><label>Název</label><input type='text' name='grant_name[]' value=''></div><div><label>Výše (Kč)</label><input type='number' step='1' name='grant_amount[]' value='0'></div><div class='right'><button type='button' onclick='this.closest(`.grid-3`).remove()'>Smazat</button></div>";host.appendChild(row);}
function simFmt(n){return Number(n||0).toLocaleString('cs-CZ')+' Kč';}
function simCalc(){const kwp=parseFloat(document.getElementById('sim_kwp').value||'0');const bmods=parseInt(document.getElementById('sim_bmods').value||'0',10);const wb=document.getElementById('sim_wb').checked;
fetch(location.pathname+'?sim=1&kwp='+encodeURIComponent(kwp)+'&bmods='+encodeURIComponent(bmods)+'&wb='+(wb?1:0)).then(r=>r.json()).then(j=>{document.getElementById('sim_out').textContent='Cena: '+simFmt(j.price)+' (panely '+j.panels+' ks, baterie '+j.batKwh.toFixed(2)+' kWh)';}).catch(()=>{document.getElementById('sim_out').textContent='Chyba výpočtu';});}
</script>
</body></html>
<?php
if (isset($_GET['sim'])){ header('Content-Type: application/json; charset=UTF-8'); $kwp=floatval($_GET['kwp']??0); $bmods=intval($_GET['bmods']??0); $wb=(isset($_GET['wb']) && intval($_GET['wb'])===1); list($p,$bat,$pan)=calc_price_total($cfg,$kwp,$bmods,$wb); echo json_encode(['price'=>$p,'batKwh'=>$bat,'panels'=>$pan]); exit; }
?>