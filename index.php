<?php
session_start();
define('DATA_DIR', __DIR__ . '/data');

/* ===== STORAGE ADAPTER (swap this for a real database later) ===== */
function store_file(): string {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0775, true);
    $uid = preg_replace('/\W/', '', $_SESSION['uid'] ?? 'demo'); // later: real customer ID
    return DATA_DIR . "/$uid.json";
}

if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (isset($_GET['login']))  { $_SESSION['in'] = true; $_SESSION['uid'] = 'demo'; header('Location: index.php'); exit; }
$in = !empty($_SESSION['in']);

/* ===== API: ?api=load | ?api=save ===== */
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    if (!$in) { http_response_code(401); echo '{"error":"auth"}'; exit; }
    $f = store_file();
    $d = is_file($f) ? json_decode(file_get_contents($f), true) : null;
    if (!is_array($d)) $d = ['answers' => null, 'history' => []];

    if ($_GET['api'] === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body) || !isset($body['answers']) || !is_array($body['answers'])) {
            http_response_code(400); echo '{"error":"bad request"}'; exit;
        }
        $clean = [];
        foreach ($body['answers'] as $k => $v) {           // keep only simple values
            if (is_scalar($v)) $clean[preg_replace('/\W/', '', (string)$k)] = mb_substr((string)$v, 0, 200);
        }
        $sc = $body['scores'] ?? [];
        $d['answers'] = $clean;
        $d['history'][] = ['at' => date('c'), 'overall' => (int)($sc['overall'] ?? 0), 'scores' => $sc];
        $d['history'] = array_slice($d['history'], -20);
        file_put_contents($f, json_encode($d), LOCK_EX);
    }
    echo json_encode($d);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>TurvaTarkistus</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#00a1d5">
<style>
:root{box-sizing:border-box;--bl:#00a1d5;--dk:#0a6e96;--bg:#f4f8fb;--tx:#16303d;--mu:#5c7280;--hi:#d63b3b;--md:#e08a00;--lo:#2e8b57}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font-family:system-ui,-apple-system,Segoe UI,sans-serif}
body{background:var(--bl);color:#fff}
body.dash{background:var(--bg);color:var(--tx)}
@keyframes in{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
@keyframes ld{to{width:100%}}
.screen{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:env(safe-area-inset-top,0px) 24px env(safe-area-inset-bottom,0px)}
.screen[hidden]{display:none}
.logo{width:min(80vw,420px);height:auto;animation:in 1s ease both}.logo.sm{width:min(60vw,300px)}
h1{font-size:clamp(22px,6vw,34px);margin:0;animation:in 1s .5s ease both}
.screen p{margin:6px 0 28px;opacity:.9;font-size:clamp(14px,4vw,18px)}
.bar{width:min(60vw,240px);height:6px;background:rgba(255,255,255,.3);border-radius:6px;overflow:hidden}
.bar i{display:block;height:100%;width:0;background:#fff;animation:ld 2s 1s ease forwards}
.btn{display:inline-block;margin-top:28px;border:0;border-radius:999px;padding:14px 40px;font:600 17px inherit;font-family:inherit;background:#fff;color:var(--dk);cursor:pointer;text-decoration:none}
.btn.late{opacity:0;animation:in .6s 3s ease forwards}

.top{background:var(--bl);color:#fff;padding:calc(env(safe-area-inset-top,0px) + 16px) 20px 30px;border-radius:0 0 24px 24px}
.top .row{display:flex;justify-content:space-between;align-items:center}
.top .links{display:flex;gap:16px}
.top img{height:36px;border-radius:8px}.top a{color:#fff;font-size:14px;opacity:.9}
.top h2{margin:16px 0 2px;font-size:24px}.top span{opacity:.9;font-size:14px}
.wrap{max-width:720px;margin:0 auto;padding:0 16px calc(env(safe-area-inset-bottom,0px) + 90px)}
.card{background:#fff;border-radius:18px;padding:16px;margin-top:14px;box-shadow:0 4px 18px rgba(0,80,120,.08)}
.card h3{margin:0 0 10px;font-size:17px}.mu{color:var(--mu);font-size:14px}
.view[hidden]{display:none}.first{margin-top:-18px}
nav.tabs{position:fixed;left:0;right:0;bottom:0;background:#fff;display:flex;justify-content:center;gap:4px;padding:8px 8px calc(env(safe-area-inset-bottom,0px) + 8px);box-shadow:0 -4px 18px rgba(0,80,120,.12)}
nav.tabs button{flex:1;max-width:150px;border:0;background:none;padding:8px 2px;border-radius:12px;font:600 12px inherit;font-family:inherit;color:var(--mu);cursor:pointer;position:relative}
nav.tabs button b{display:block;font-size:20px;font-weight:400}
nav.tabs button.on{background:#e6f6fc;color:var(--dk)}
.badge{position:absolute;top:2px;right:14%;background:var(--hi);color:#fff;border-radius:999px;font-size:11px;padding:1px 6px}

.who{display:flex;gap:14px;align-items:center}
.av{width:56px;height:56px;border-radius:50%;background:var(--bl);color:#fff;display:grid;place-items:center;font-size:22px;font-weight:700;flex:none}
.ins{display:flex;gap:12px;padding:12px 0;border-top:1px solid #e3eef4}.ins:first-of-type{border-top:0}.ins .ic{font-size:26px}
.pill{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;color:#fff;vertical-align:middle}
.High{background:var(--hi)}.Medium{background:var(--md)}.Low{background:var(--lo)}.ok{background:#e1f4ea;color:var(--lo)}
.meter{height:10px;background:#e3eef4;border-radius:8px;overflow:hidden;margin:6px 0}.meter i{display:block;height:100%;transition:width .5s}
.score{display:flex;align-items:center;gap:16px}
.ring{width:96px;height:96px;border-radius:50%;display:grid;place-items:center;flex:none}
.ring b{width:72px;height:72px;border-radius:50%;background:#fff;display:grid;place-items:center;font-size:21px}
.lv{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:14px}
.lv div{border-radius:14px;padding:12px;text-align:center;color:#fff}.lv b{display:block;font-size:24px}
.arow{margin:12px 0}.arow .t{display:flex;justify-content:space-between;font-size:14px;font-weight:600}
.rl{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-top:1px solid #e3eef4;font-size:14px}.rl:first-of-type{border-top:0}
.al{border-left:6px solid var(--c)}.al h4{margin:8px 0 4px;font-size:16px}
.al .what{background:#fff5f5;border-radius:12px;padding:10px;margin:10px 0;font-size:14px}.al .what.Medium{background:#fff8ec}
.al .row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.al button,.sub{border:0;border-radius:999px;padding:10px 18px;font:600 14px inherit;font-family:inherit;cursor:pointer}
.go{background:var(--bl);color:#fff}.sn{background:#e9f1f6;color:var(--tx)}
.sub{width:100%;background:var(--bl);color:#fff;padding:14px;font-size:16px;margin-top:16px}
.sec{margin:22px 0 4px;padding-bottom:6px;border-bottom:2px solid #e3eef4;color:var(--dk);font-weight:700}
.f label{display:block;font-size:14px;font-weight:600;margin:14px 0 6px}
.f input,.f select{width:100%;padding:12px;font:inherit;font-size:16px;border:1.5px solid #c9d8e1;border-radius:12px;background:#fff;color:var(--tx)}
.f input:focus,.f select:focus{outline:none;border-color:var(--bl);box-shadow:0 0 0 3px rgba(0,161,213,.2)}
.toast{position:fixed;left:50%;bottom:90px;transform:translateX(-50%);background:#16303d;color:#fff;padding:10px 18px;border-radius:999px;font-size:14px;opacity:0;pointer-events:none;transition:opacity .3s;z-index:30}.toast.on{opacity:1}

/* Guide */
.step{display:flex;gap:12px;padding:12px 0;border-top:1px solid #e3eef4}
.step:first-of-type{border-top:0}
.step .n{width:34px;height:34px;border-radius:50%;background:var(--bl);color:#fff;display:grid;place-items:center;font-weight:700;flex:none}
.step b{display:block}
.legend{display:grid;grid-template-columns:1fr;gap:8px;margin-top:6px}
.legend div{display:flex;gap:10px;align-items:flex-start;font-size:14px}
.legend .pill{min-width:76px;text-align:center}

/* Chatbot */
.chat-fab{position:fixed;right:16px;bottom:calc(env(safe-area-inset-bottom,0px) + 84px);width:58px;height:58px;border-radius:50%;border:0;background:var(--bl);color:#fff;font-size:26px;box-shadow:0 6px 20px rgba(0,80,120,.35);cursor:pointer;z-index:20}
.chat-fab .dot{position:absolute;top:2px;right:2px;width:14px;height:14px;border-radius:50%;background:var(--hi);border:2px solid #fff}
.chat{position:fixed;left:12px;right:12px;margin-left:auto;max-width:400px;bottom:calc(env(safe-area-inset-bottom,0px) + 84px);height:min(72vh,540px);background:#fff;border-radius:20px;box-shadow:0 10px 40px rgba(0,60,90,.3);display:none;flex-direction:column;overflow:hidden;z-index:21}
.chat.on{display:flex}
.chat-h{background:var(--bl);color:#fff;padding:12px 14px;display:flex;justify-content:space-between;align-items:center}
.chat-h b{display:block}.chat-h span{font-size:12px;opacity:.9}
.chat-h button{background:none;border:0;color:#fff;font-size:22px;cursor:pointer}
.chat-m{flex:1;overflow-y:auto;padding:12px;background:var(--bg);display:flex;flex-direction:column;gap:8px}
.msg{max-width:85%;padding:9px 12px;border-radius:14px;font-size:14px;line-height:1.4;animation:in .25s ease both}
.msg.bot{background:#fff;color:var(--tx);border-bottom-left-radius:4px;align-self:flex-start;box-shadow:0 1px 4px rgba(0,80,120,.1)}
.msg.me{background:var(--bl);color:#fff;border-bottom-right-radius:4px;align-self:flex-end}
.chips{display:flex;gap:6px;flex-wrap:wrap;padding:8px 12px;background:var(--bg);border-top:1px solid #e3eef4}
.chips button{border:1.5px solid var(--bl);background:#fff;color:var(--dk);border-radius:999px;padding:6px 12px;font:600 13px inherit;font-family:inherit;cursor:pointer}
.chat-f{display:flex;gap:8px;padding:10px;border-top:1px solid #e3eef4}
.chat-f input{flex:1;padding:11px 14px;font:inherit;font-size:16px;border:1.5px solid #c9d8e1;border-radius:999px;color:var(--tx)}
.chat-f button{border:0;background:var(--bl);color:#fff;border-radius:999px;padding:0 18px;font:600 15px inherit;font-family:inherit;cursor:pointer}
</style>
</head>
<body class="<?= $in ? 'dash' : '' ?>">

<?php if (!$in): ?>
<main class="screen" id="splash">
  <img class="logo" alt="LähiTapiola" src="logo.JPG">
  <h1>TurvaTarkistus</h1>
  <p style="animation:in 1s .8s ease both">Safety check-up for everyday life</p>
  <div class="bar"><i></i></div>
  <button class="btn late" onclick="document.getElementById('splash').hidden=true;document.getElementById('login').hidden=false">Get started</button>
</main>
<main class="screen" id="login" hidden>
  <img class="logo sm" alt="LähiTapiola" src="logo.JPG">
  <h1 style="animation:none">Welcome</h1>
  <p>Log in to see your safety dashboard</p>
  <a class="btn" style="margin-top:0" href="index.php?login=1">Log in</a>
</main>

<?php else: ?>
<header class="top">
  <div class="row"><img src="logo.JPG" alt="LähiTapiola">
    <div class="links"><a href="#" onclick="show('guide');return false">❓ Guide</a><a href="index.php?logout=1">Sign out</a></div></div>
  <h2 id="ttl">Profile</h2><span id="sub">Your details and insurances</span>
</header>

<div class="wrap">
  <section class="view" id="v-profile">
    <div class="card first">
      <div class="who"><div class="av" id="av"></div><div><h3 style="margin:0" id="pn"></h3><div class="mu" id="pa"></div></div></div>
      <div class="meter"><i style="width:60%;background:var(--bl)"></i></div>
      <div class="mu">Owner-customer level: Silver · 120 / 200 points to Gold</div>
    </div>
    <div class="card"><h3>My insurances</h3><div id="ins"></div></div>
    <div class="card"><h3>Overview</h3><div id="ov" class="mu"></div></div>
  </section>

  <section class="view" id="v-risk" hidden>
    <div class="card first">
      <div class="score"><div class="ring" id="ring"><b id="pct"></b></div>
        <div><h3 style="margin:0" id="lvl"></h3><div class="mu" id="scoreTxt"></div></div></div>
      <div class="lv">
        <div style="background:var(--hi)"><b id="nH">0</b>High</div>
        <div style="background:var(--md)"><b id="nM">0</b>Medium</div>
        <div style="background:var(--lo)"><b id="nL">0</b>Low</div>
      </div>
    </div>
    <div class="card"><h3>Risk by category</h3><div id="areas"></div></div>
    <div class="card"><h3>Assessment history</h3><div id="hist"></div></div>
  </section>

  <section class="view" id="v-alerts" hidden><div id="alerts"></div></section>

  <section class="view" id="v-assess" hidden>
    <div class="card first f">
      <h3>🏠 Home insurance risk assessment</h3>
      <div class="mu">Update these answers every few months. Your dashboard and alerts update automatically.</div>
      <form id="form" novalidate></form>
    </div>
  </section>

  <!-- INSTRUCTION / GUIDE PAGE -->
  <section class="view" id="v-guide" hidden>
    <div class="card first">
      <h3>👋 Welcome to TurvaTarkistus</h3>
      <div class="mu">Find out where your home is at risk, and get simple actions to prevent damage before it happens.</div>
    </div>

    <div class="card">
      <h3>How it works</h3>
      <div class="step"><div class="n">1</div><div><b>Assess</b><span class="mu">Answer the questions in the <b>Assess</b> tab. It takes about 3 minutes. Update them every few months.</span></div></div>
      <div class="step"><div class="n">2</div><div><b>See your risk</b><span class="mu">The <b>Risk</b> tab shows your overall score and fire, water, security, property and claims risk.</span></div></div>
      <div class="step"><div class="n">3</div><div><b>Act on alerts</b><span class="mu">The <b>Alerts</b> tab lists what could happen and what to do, starting with high risks. Tap <b>Mark as done</b> when finished.</span></div></div>
      <div class="step"><div class="n">4</div><div><b>Track progress</b><span class="mu">Your score updates straight away and the history shows your improvement. Your <b>Profile</b> shows which insurances each risk affects.</span></div></div>
    </div>

    <div class="card">
      <h3>What the risk levels mean</h3>
      <div class="legend">
        <div><span class="pill Low">LOW</span><span>Under 25%. Well protected. Keep up regular checks.</span></div>
        <div><span class="pill Medium">MEDIUM</span><span>25–49%. A few improvements will help.</span></div>
        <div><span class="pill High">HIGH</span><span>50–74%. Act soon to prevent damage.</span></div>
        <div><span class="pill" style="background:#8f1d1d">CRITICAL</span><span>75% or more. Needs attention now.</span></div>
      </div>
    </div>

    <div class="card">
      <h3>Tips</h3>
      <div class="mu">💬 Use the chat button to ask about your risks, alerts or insurances.<br>
      🔔 A red number on <b>Alerts</b> counts your high-risk items.<br>
      🏆 Completing actions moves you toward owner-customer rewards.</div>
    </div>

    <div class="card">
      <h3>Good to know</h3>
      <div class="mu">This is a prototype. Scores come from a simplified demo model and are not an insurance decision, price or underwriting result. Your answers are used only to show your own dashboard.</div>
      <button class="sub" onclick="closeGuide()">Got it, let's start</button>
    </div>
  </section>
</div>

<nav class="tabs">
  <button class="on" id="b-profile" onclick="show('profile')"><b>👤</b>Profile</button>
  <button id="b-risk" onclick="show('risk')"><b>📊</b>Risk</button>
  <button id="b-alerts" onclick="show('alerts')"><b>🔔</b>Alerts<span class="badge" id="badge">0</span></button>
  <button id="b-assess" onclick="show('assess')"><b>🧾</b>Assess</button>
</nav>
<div class="toast" id="toast"></div>

<!-- CHATBOT -->
<button class="chat-fab" id="fab" onclick="toggleChat()" aria-label="Open chatbot">💬<span class="dot" id="fabDot"></span></button>
<div class="chat" id="chat" role="dialog" aria-label="Safety assistant">
  <div class="chat-h"><div><b>🛡️ Turva Assistant</b><span>Ask about your risks and insurances</span></div>
    <button onclick="toggleChat()" aria-label="Close">✕</button></div>
  <div class="chat-m" id="chatM"></div>
  <div class="chips" id="chips"></div>
  <form class="chat-f" onsubmit="return sendChat(event)">
    <input id="chatIn" placeholder="Type your question…" autocomplete="off">
    <button type="submit">Send</button>
  </form>
</div>

<script>
/* ============ 1. QUESTIONNAIRE (edit here to add or change questions) ============
   [id, label, type, options "value:text|value:text"]  ·  ['#', 'Section title'] */
const Q = [
['#','1. Customer information'],
['fullName','Full name','text'],['dob','Date of birth','date'],['address','Address (city)','text'],
['existingInsurance','Existing insurance with us or another company?','select','0:Yes|3:No'],
['#','2. Property'],
['propertyType','Property type','select','0:Apartment|2:Row house|4:Detached house|5:Cottage'],
['ownership','Own or rent?','select','0:Own|2:Rent'],
['builtYear','Year built','number'],['floorArea','Floor area (m²)','number'],
['construction','Construction material','select','0:Concrete|2:Brick|4:Wood frame|5:Other / unknown'],
['roof','Roof condition','select','0:Recently renovated / good|2:5–10 years since renovation|4:More than 10 years|6:Unknown / never checked'],
['heating','Heating system','select','0:District heating|1:Geothermal / heat pump|3:Electric heating|5:Wood stove / fireplace as main|6:Other / unknown'],
['outbuildings','Basement, garage or outbuildings?','select','0:No|2:Garage|3:Basement|4:Multiple outbuildings'],
['renovation','Major renovations?','select','0:Yes, recently|2:Yes, more than 5 years ago|4:No major renovation|5:Unknown'],
['#','3. Fire & safety'],
['smokeAlarms','Smoke alarms?','select','0:Yes, on all required floors|8:Yes, but not everywhere|15:No'],
['fireExtinguisher','Fire extinguisher?','select','0:Yes|8:No'],
['saunaFireplace','Sauna, fireplace or chimney?','select','0:No|4:Sauna|5:Fireplace / chimney|7:Sauna and fireplace / chimney'],
['checks','Chimney and washing-machine hoses last checked','select','0:Within the last 2 years|3:2–5 years ago|7:More than 5 years ago|10:Never / unknown'],
['#','4. Security & water safety'],
['security','Security controls','select','0:Alarm + locks + leak sensors|2:Alarm + security locks|4:Security locks only|7:Basic locks only'],
['waterValve','Know where the main water shut-off valve is?','select','0:Yes|5:Not sure|10:No'],
['emptyPeriods','Home empty for long periods?','select','0:No|5:Sometimes, holidays|10:Frequently / long periods'],
['#','5. Belongings & occupancy'],
['belongings','Value of belongings','select','0:Under €20,000|2:€20,000–50,000|4:€50,000–100,000|6:Over €100,000'],
['highValue','High-value items','select','0:None|3:Laptop / electronics / instrument|5:Jewellery / art / several items'],
['outsideCover','Cover for items carried outside?','select','0:Yes|1:No'],
['workHome','Work from home or business at home?','select','0:No|2:Part-time remote|4:Full-time remote|6:Business at home'],
['occupants','Who lives in the home?','select','0:One adult|1:Two adults|2:Family with children|3:Multiple unrelated occupants'],
['pets','Pets','select','0:No pets|1:Dog / cat|2:Multiple / other pets'],
['#','6. Claims & coverage'],
['claims','Claims in the last 5 years','select','0:None|5:One small claim|10:Two claims|18:Three or more'],
['cancelled','Insurer refused or cancelled your cover?','select','0:No|15:Yes'],
['buildingValue','Building coverage amount (€)','number'],
['deductible','Preferred deductible','select','0:€500 or more|2:€250–499|4:Under €250'],
['coverage','Coverage wanted','select','0:Fire + water + storm + theft|1:All above + accidental damage|2:Basic only'],
['#','7. Recent incident'],
['recentIncident','Recent incident?','select','0:No|8:Yes, minor|15:Yes, significant'],
['injury','Anyone injured?','select','0:No|10:Yes'],
['ongoing','Damage still ongoing?','select','0:No|10:Yes'],
['causeStopped','Cause stopped / water valve closed?','select','0:Yes|5:No / not yet|3:Yes, but valve hard to find'],
['evidence','Photos or videos taken?','select','0:Yes|3:No'],
['damage','Estimated damage','select','0:No recent damage|3:Under €2,000|6:€2,000–5,000|10:€5,000–10,000|15:Over €10,000'],
['#','8. Confirmation'],
['confirmed','Information is correct?','select','0:Yes|10:No'],
['privacy','Agree to terms and privacy policy?','select','0:Yes|1:No']
];
const opts = s => s.split('|').map(o => { const i=o.indexOf(':'); return [o.slice(0,i), o.slice(i+1)]; });
const DEMO = {fullName:'Matti Meikäläinen',dob:'1985-05-12',address:'Vaasa',builtYear:1988,floorArea:140,buildingValue:350000,
  propertyType:'4',construction:'4',roof:'4',saunaFireplace:'7',smokeAlarms:'8',checks:'7',waterValve:'5',emptyPeriods:'5',security:'4'};
let ANS = {}; Q.forEach(q => { if (q[2]==='select') ANS[q[0]] = opts(q[3])[0][0]; }); Object.assign(ANS, DEMO);
let HIST = [], SC = {};

/* ============ 2. SCORING (move server-side in production) ============ */
function num(){ const o={}; for (const k in ANS) o[k] = (ANS[k]!=='' && !isNaN(ANS[k])) ? Number(ANS[k]) : ANS[k]; return o; }
function score(n){
  const v = id => Number(n[id]) || 0, age = Math.max(0, new Date().getFullYear() - v('builtYear'));
  let fire = v('smokeAlarms')+v('fireExtinguisher')+v('saunaFireplace')+v('checks') + (age>40?5:age>25?2:0);
  fire = Math.min(100, fire*2.5);
  let water = v('waterValve')+v('checks')+v('recentIncident')+v('ongoing')+v('causeStopped')+v('damage') + (v('construction')>=4?3:0);
  water = Math.min(100, water*2);
  const security = Math.min(100, v('security')*5 + v('emptyPeriods')*4 + v('highValue')*3);
  let prop = v('propertyType')*3+v('construction')*3+v('roof')*3+v('heating')*2+v('outbuildings')*2+v('renovation')*2+v('workHome')*2+v('occupants')+v('pets') + (age>50?15:age>30?8:0);
  prop = Math.min(100, prop*1.5);
  const claims = Math.min(100, v('claims')*3+v('cancelled')*3+v('recentIncident')*3+v('injury')*3+v('ongoing')*3+v('damage')*2+v('confirmed'));
  const overall = Math.round(Math.min(100, fire*.20 + water*.25 + security*.15 + prop*.20 + claims*.20));
  return {fire:Math.round(fire),water:Math.round(water),security:Math.round(security),property:Math.round(prop),claims:Math.round(claims),overall};
}
const level = p => p<25?'Low':p<50?'Medium':p<75?'High':'Critical';
const col = p => p<25?'var(--lo)':p<50?'var(--md)':'var(--hi)';

/* ============ 3. ALERT RULES (answers -> notifications) ============ */
const RULES = [
{cat:'Fire',ins:'Home insurance',on:n=>n.checks>=3,lv:n=>n.checks>=7?'High':'Medium',
 t:'Chimney and washing-machine hose inspection overdue',
 why:'Not inspected for years: soot build-up can cause a chimney fire and aged hoses can burst and flood the home. Neglected maintenance can also affect compensation.',
 act:'Book a chimney sweep and replace hoses older than 10 years.',fix:{checks:0}},
{cat:'Fire',ins:'Home insurance',on:n=>n.smokeAlarms>0,lv:n=>n.smokeAlarms>=15?'High':'Medium',
 t:'Smoke alarms missing or incomplete',why:'A fire can spread unnoticed, especially at night. Late detection is a main factor in serious injuries and total losses.',
 act:'Install and test an alarm on every floor.',fix:{smokeAlarms:0}},
{cat:'Fire',ins:'Home insurance',on:n=>n.fireExtinguisher>0,lv:()=>'Medium',
 t:'No fire extinguisher',why:'A small kitchen or sauna fire can become a major loss before help arrives.',
 act:'Keep an extinguisher or fire blanket near the exit.',fix:{fireExtinguisher:0}},
{cat:'Water',ins:'Home insurance',on:n=>n.waterValve>0,lv:n=>n.waterValve>=10?'High':'Medium',
 t:'Water shut-off valve location unknown',why:'A leak you cannot stop quickly turns a small pipe failure into major water damage.',
 act:'Find the main valve, label it and test that it turns.',fix:{waterValve:0}},
{cat:'Water',ins:'Home insurance',on:n=>n.recentIncident>0||n.ongoing>0,lv:n=>(n.ongoing>0||n.recentIncident>=15)?'High':'Medium',
 t:'Recent damage incident not resolved',why:'Unrepaired or ongoing damage can spread (mould, structural damage) and increase the final claim cost.',
 act:'Stop the cause, document the damage with photos and contact claims support.',fix:{recentIncident:0,ongoing:0,causeStopped:0,damage:0,injury:0}},
{cat:'Security',ins:'Home insurance',on:n=>n.emptyPeriods>0,lv:n=>n.emptyPeriods>=10?'High':'Medium',
 t:'Home often empty',why:'Long empty periods raise the risk of unnoticed leaks, frost damage and burglary.',
 act:'Install leak sensors, ask a neighbour to check in and use timers on lights.',fix:{emptyPeriods:0}},
{cat:'Security',ins:'Home insurance',on:n=>n.security>=4,lv:n=>n.security>=7?'High':'Medium',
 t:'Basic home security only',why:'Weak locks and no alarm make burglary easier, especially with valuables at home.',
 act:'Add security locks, an alarm and leak sensors.',fix:{security:0}},
{cat:'Property',ins:'Home insurance',on:n=>n.roof>=4,lv:n=>n.roof>=6?'High':'Medium',
 t:'Roof condition old or unknown',why:'An aged roof can leak or fail in storms or heavy snow, causing water and structural damage.',
 act:'Have the roof inspected and plan maintenance.',fix:{roof:0}},
{cat:'Property',ins:'Home insurance',on:n=>(new Date().getFullYear()-n.builtYear)>40&&n.renovation>=4,lv:()=>'Medium',
 t:'Older building without major renovation',why:'Old pipes, wiring and insulation are common causes of water and fire damage.',
 act:'Inspect pipes and electrics; plan renovation.',fix:{renovation:0}}
];
const INSURANCES = [
 {n:'Home insurance',i:'🏠',d:'Fire, water damage, theft',s:'Active'},
 {n:'Car insurance',i:'🚗',d:'Motor liability + comprehensive · ABC-123',s:'Active',note:'Car risk assessment coming soon'},
 {n:'Accident insurance',i:'🩹',d:'Leisure-time accidents · family',s:'Active',note:'Wellbeing assessment coming soon'}
];
const ORDER = {High:0,Medium:1};
const $ = id => document.getElementById(id);
const optText = (id,val) => { const q=Q.find(x=>x[0]===id); const o=q&&q[3]?opts(q[3]).find(o=>o[0]==val):null; return o?o[1]:''; };

/* ============ 4. UI ============ */
function show(v){
  ['profile','risk','alerts','assess','guide'].forEach(x=>{
    $('v-'+x).hidden = x!==v;
    const b=$('b-'+x); if(b) b.className = x===v?'on':'';
  });
  const T={profile:['Profile','Your details and insurances'],risk:['Risk dashboard','Where your biggest risks are'],alerts:['Alerts','Act on these to prevent damage'],assess:['Assessment','Update your answers'],guide:['Guide','How TurvaTarkistus works']};
  $('ttl').textContent=T[v][0]; $('sub').textContent=T[v][1]; scrollTo(0,0);
}
function closeGuide(){ try{localStorage.setItem('guideSeen','1')}catch(e){} show('profile'); }
function toast(m){ const t=$('toast'); t.textContent=m; t.classList.add('on'); setTimeout(()=>t.classList.remove('on'),2200); }

function buildForm(){
  let h=''; Q.forEach(q=>{
    if (q[0]==='#') { h+=`<div class="sec">${q[1]}</div>`; return; }
    const [id,label,type,o]=q, val=ANS[id]??'';
    h+=`<label for="q_${id}">${label}</label>`;
    h+= type==='select'
      ? `<select id="q_${id}">${opts(o).map(([v,t])=>`<option value="${v}" ${String(val)===v?'selected':''}>${t}</option>`).join('')}</select>`
      : `<input id="q_${id}" type="${type}" value="${String(val).replace(/"/g,'&quot;')}" ${type==='number'?'inputmode="numeric"':''}>`;
  });
  $('form').innerHTML = h + `<button class="sub" type="submit">🔍 Save and analyse risk</button>`;
}
$('form').addEventListener('submit', e => {
  e.preventDefault();
  Q.forEach(q=>{ if(q[0]!=='#') ANS[q[0]] = $('q_'+q[0]).value; });
  if (!ANS.fullName.trim() || !ANS.builtYear) { toast('Please fill in name and year built'); return; }
  save().then(()=>{ show('risk'); toast('✅ Assessment saved. Dashboard updated'); });
});

async function save(){
  SC = score(num());
  try {
    const r = await fetch('index.php?api=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({answers:ANS,scores:SC})});
    const d = await r.json(); HIST = d.history || HIST;
  } catch(e) { toast('Could not save to server (showing local result)'); }
  render();
}
function applyFix(i){ Object.assign(ANS, RULES[i].fix); buildForm(); save().then(()=>toast('✅ Done. Your risk was recalculated')); }

function render(){
  const n=num(); SC=score(n);
  const A = RULES.map((r,i)=>({...r,i})).filter(r=>r.on(n)).map(r=>({...r,l:r.lv(n)})).sort((a,b)=>ORDER[a.l]-ORDER[b.l]);
  const nH=A.filter(a=>a.l==='High').length, nM=A.length-nH, nL=RULES.length-A.length;
  const lv=level(SC.overall);

  // profile
  $('av').textContent=(n.fullName||'?').split(' ').map(s=>s[0]).join('').slice(0,2).toUpperCase();
  $('pn').textContent=n.fullName; $('pa').textContent=`Owner-customer · ${n.address||''} · ${optText('propertyType',ANS.propertyType)}`;
  $('ins').innerHTML=INSURANCES.map(x=>{
    const open=A.filter(a=>a.ins===x.n).length;
    const extra = x.n==='Home insurance' ? ` · cover €${Number(n.buildingValue||0).toLocaleString('en')}` : '';
    return `<div class="ins"><div class="ic">${x.i}</div><div style="flex:1"><b>${x.n}</b> <span class="pill ok">${x.s}</span>
      <div class="mu">${x.d}${extra}</div><div class="mu">${x.note?'ℹ️ '+x.note:open?`⚠️ ${open} open risk${open>1?'s':''} linked`:'✅ No open risks'}</div></div></div>`;}).join('');
  $('ov').innerHTML=`Overall home risk <b>${SC.overall}% (${lv})</b> · ${nH} high, ${nM} medium. Last assessed: ${HIST.length?new Date(HIST[HIST.length-1].at).toLocaleDateString('en-GB'):'demo data (not yet saved)'}.`;

  // risk dashboard
  $('pct').textContent=SC.overall+'%'; $('ring').style.background=`conic-gradient(${col(SC.overall)} 0 ${SC.overall}%,#e3eef4 0)`;
  $('lvl').textContent=lv.toUpperCase()+' RISK';
  $('scoreTxt').textContent=nH?`${nH} high-risk item${nH>1?'s need':' needs'} attention.`:'No high-risk items. Keep it up!';
  $('nH').textContent=nH;$('nM').textContent=nM;$('nL').textContent=nL;
  $('areas').innerHTML=[['🔥 Fire','fire'],['💧 Water','water'],['🔐 Security','security'],['🏠 Property / occupancy','property'],['📋 Claims / incidents','claims']].map(([t,k])=>
    `<div class="arow"><div class="t"><span>${t}</span><span>${SC[k]}%</span></div><div class="meter"><i style="width:${SC[k]}%;background:${col(SC[k])}"></i></div></div>`).join('');
  $('hist').innerHTML=HIST.length?[...HIST].reverse().slice(0,6).map((h,i,a)=>{const p=a[i+1];const d=p?h.overall-p.overall:0;
    return `<div class="rl"><span>${new Date(h.at).toLocaleDateString('en-GB')}</span><b>${h.overall}% ${p?(d<0?'🟢 ▼'+(-d):d>0?'🔴 ▲'+d:'▬'):''}</b></div>`;}).join('')
    :'<div class="mu">No saved assessments yet. Complete the Assess tab.</div>';

  // alerts
  $('badge').textContent=nH; $('badge').style.display=nH?'':'none';
  $('alerts').innerHTML=A.length?A.map(a=>{
    const note=a.i===0?` Last check: <b>${optText('checks',ANS.checks)}</b>.`:'';
    return `<div class="card al" style="--c:${a.l==='High'?'var(--hi)':'var(--md)'}">
      <span class="pill ${a.l}">${a.l.toUpperCase()} RISK</span> <span class="mu">· ${a.cat}</span>
      <h4>${a.t}</h4>
      <div class="what ${a.l}"><b>What could happen:</b> ${a.why}${note}</div>
      <div class="mu"><b>Recommended:</b> ${a.act}</div><div class="mu">🛡️ Related insurance: ${a.ins}</div>
      <div class="row"><button class="go" onclick="applyFix(${a.i})">Mark as done</button><button class="sn" onclick="toast('⏰ We will remind you in 7 days')">Remind me later</button></div></div>`;}).join('')
    :`<div class="card first" style="text-align:center">🎉 <b>No open alerts</b><div class="mu">Your home looks well protected.</div></div>`;
  if(A.length) $('alerts').firstElementChild.classList.add('first');
}

/* ============ 5. CHATBOT (rule-based; uses the customer's live data) ============ */
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function liveAlerts(){
  const n=num();
  return RULES.map((r,i)=>({...r,i})).filter(r=>r.on(n)).map(r=>({...r,l:r.lv(n)})).sort((a,b)=>ORDER[a.l]-ORDER[b.l]);
}
const CHIPS = [
  ['What is my risk score?'],['What should I fix first?'],['What insurances do I have?'],
  ['How can I lower my risk?'],['Open my alerts','__alerts']
];
function bot(html){ const d=document.createElement('div'); d.className='msg bot'; d.innerHTML=html; $('chatM').appendChild(d); $('chatM').scrollTop=1e9; }
function me(t){ const d=document.createElement('div'); d.className='msg me'; d.textContent=t; $('chatM').appendChild(d); $('chatM').scrollTop=1e9; }
function renderChips(){ $('chips').innerHTML = CHIPS.map(([t],i)=>`<button onclick="chip(${i})">${t}</button>`).join(''); }
function chip(i){
  const [t,a]=CHIPS[i];
  if(a==='__alerts'){ toggleChat(); show('alerts'); return; }
  ask(t);
}
function toggleChat(){
  const c=$('chat'); c.classList.toggle('on');
  if(c.classList.contains('on')){
    $('fabDot').style.display='none';
    if(!$('chatM').children.length){
      const n=num(), A=liveAlerts(), nH=A.filter(a=>a.l==='High').length;
      bot(`Hi ${esc((n.fullName||'there').split(' ')[0])} 👋 I'm your safety assistant. `+
          (nH?`You have <b>${nH} high-risk</b> item${nH>1?'s':''} that need attention.`:`Your home is looking good.`)+` What would you like to know?`);
      renderChips();
    }
    setTimeout(()=>$('chatIn').focus(),100);
  }
}
function sendChat(e){ e.preventDefault(); const v=$('chatIn').value.trim(); if(v){ $('chatIn').value=''; ask(v); } return false; }
function ask(text){
  me(text);
  const typing=document.createElement('div'); typing.className='msg bot'; typing.textContent='…'; $('chatM').appendChild(typing); $('chatM').scrollTop=1e9;
  setTimeout(()=>{ typing.remove(); bot(reply(text.toLowerCase())); },500);
}
function reply(q){
  const n=num(), s=score(n), A=liveAlerts(), top=A[0];
  const has = (...w)=>w.some(x=>q.includes(x));

  if(has('hello','hi ','hey','moi')) return 'Hello! Ask me about your risk score, alerts or insurances.';
  if(has('how to use','guide','instruction','help me use','how does this work')) return 'Open the <b>❓ Guide</b> link at the top of the page for a quick walkthrough.';
  if(has('score','overall','how risky','risk level')){
    return `Your overall home risk is <b>${s.overall}% (${level(s.overall)})</b>.<br>🔥 Fire ${s.fire}% · 💧 Water ${s.water}% · 🔐 Security ${s.security}% · 🏠 Property ${s.property}% · 📋 Claims ${s.claims}%.`;
  }
  if(has('first','urgent','priority','biggest','important','alert','notification')){
    if(!top) return 'No open alerts. Nothing urgent right now 🎉';
    return `Top priority: <b>${esc(top.t)}</b> (${top.l} risk).<br><br><b>What could happen:</b> ${esc(top.why)}<br><br><b>Do this:</b> ${esc(top.act)}`;
  }
  if(has('insurance','policy','cover','insured','vakuutus')){
    return INSURANCES.map(x=>`${x.i} <b>${x.n}</b>: ${x.d}`).join('<br>') + `<br><br>Home cover amount: €${Number(n.buildingValue||0).toLocaleString('en')}.`;
  }
  if(has('lower','reduce','improve','tips','better','decrease','safer')){
    if(!A.length) return 'Your risk is already low. Keep your checks up to date every few months.';
    return 'Quick wins to lower your risk:<br>'+A.slice(0,4).map(a=>`• ${esc(a.act)}`).join('<br>')+'<br><br>Tap <b>Mark as done</b> in Alerts when finished and your score updates.';
  }
  if(has('chimney','hose','sweep')) return 'Chimneys and washing-machine hoses should be checked regularly. Soot can cause a chimney fire, and old hoses can burst and flood your home. Book a chimney sweep and replace hoses older than ~10 years.';
  if(has('water','leak','valve','flood')) return `Water damage is one of the most common home claims. Make sure you know where your main shut-off valve is and consider leak sensors. Your water risk is <b>${s.water}%</b>.`;
  if(has('fire','smoke','extinguisher','sauna')) return `Test smoke alarms on every floor and keep an extinguisher or fire blanket near the exit. Your fire risk is <b>${s.fire}%</b>.`;
  if(has('burglar','theft','security','lock','empty')) return `Security locks, an alarm and checks while you are away reduce break-in risk. Your security risk is <b>${s.security}%</b>.`;
  if(has('roof')) return 'An old roof can leak or fail in storms and heavy snow. A professional inspection every few years is a good idea.';
  if(has('claim','damage','accident')) return 'If you have had damage, stop the cause (e.g. close the water valve), take photos, and contact claims support. You can also update your answers in the <b>Assess</b> tab.';
  if(has('thank')) return 'You are welcome! 💙';
  return 'I can help with your <b>risk score</b>, <b>alerts</b>, <b>insurances</b>, and tips to <b>lower your risk</b>. Try one of the buttons below.';
}

(async()=>{
  try { const r=await fetch('index.php?api=load'); const d=await r.json(); if(d.answers) Object.assign(ANS,d.answers); HIST=d.history||[]; } catch(e){}
  buildForm(); render();
  try{ if(!localStorage.getItem('guideSeen')) show('guide'); }catch(e){}
})();
</script>
<?php endif; ?>
</body>
</html>