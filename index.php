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
if ($in && isset($_GET['reset'])) { @unlink(store_file()); header('Location: index.php'); exit; } // demo reset

/* ===== API: ?api=load | ?api=save ===== */
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    if (!$in) { http_response_code(401); echo '{"error":"auth"}'; exit; }
    $f = store_file();
    $d = is_file($f) ? json_decode(file_get_contents($f), true) : null;
    if (!is_array($d)) $d = ['answers' => null, 'history' => [], 'game' => null];

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

        // points state (sanitised). Production: award points on the server.
        $g = is_array($body['game'] ?? null) ? $body['game'] : [];
        $game = ['points' => max(0, (int)($g['points'] ?? 0)), 'awarded' => [], 'log' => []];
        foreach (array_slice((array)($g['awarded'] ?? []), 0, 300) as $a) {
            if (is_string($a)) $game['awarded'][] = mb_substr(preg_replace('/[^\w\-]/', '', $a), 0, 60);
        }
        foreach (array_slice((array)($g['log'] ?? []), -30) as $l) {
            if (is_array($l)) $game['log'][] = ['t' => mb_substr((string)($l['t'] ?? ''), 0, 90), 'p' => (int)($l['p'] ?? 0), 'at' => mb_substr((string)($l['at'] ?? ''), 0, 30)];
        }
        $d['game'] = $game;
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
:root{box-sizing:border-box;--bl:#00a1d5;--dk:#0a6e96;--bg:#f4f8fb;--tx:#16303d;--mu:#5c7280;--hi:#d63b3b;--md:#e08a00;--lo:#2e8b57;--gold:#f2b400}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font-family:system-ui,-apple-system,Segoe UI,sans-serif}
body{background:var(--bl);color:#fff}
body.dash{background:var(--bg);color:var(--tx)}
@keyframes in{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
@keyframes ld{to{width:100%}}

/* Line icons */
.ico{width:1.1em;height:1.1em;display:inline-block;vertical-align:-.2em;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
nav.tabs button b .ico{width:22px;height:22px;vertical-align:middle}
.chat-fab .ico{width:26px;height:26px}
.ins .ic .ico{width:28px;height:28px;color:var(--bl)}
.rw .ic .ico{width:22px;height:22px;color:var(--dk)}
.top a.bell .ico{width:20px;height:20px}

/* Splash + login */
.screen{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:env(safe-area-inset-top,0px) 24px env(safe-area-inset-bottom,0px)}
.screen[hidden]{display:none}
.logo{width:min(80vw,420px);height:auto;animation:in 1s ease both}.logo.sm{width:min(60vw,300px)}
h1{font-size:clamp(22px,6vw,34px);margin:0;animation:in 1s .5s ease both}
.screen p{margin:6px 0 28px;opacity:.9;font-size:clamp(14px,4vw,18px)}
.bar{width:min(60vw,240px);height:6px;background:rgba(255,255,255,.3);border-radius:6px;overflow:hidden}
.bar i{display:block;height:100%;width:0;background:#fff;animation:ld 2s 1s ease forwards}
.btn{display:inline-block;margin-top:28px;border:0;border-radius:999px;padding:14px 40px;font:600 17px inherit;font-family:inherit;background:#fff;color:var(--dk);cursor:pointer;text-decoration:none}
.btn.late{opacity:0;animation:in .6s 3s ease forwards}

/* Shell */
.top{background:var(--bl);color:#fff;padding:calc(env(safe-area-inset-top,0px) + 16px) 20px 30px;border-radius:0 0 24px 24px}
.top .row{display:flex;justify-content:space-between;align-items:center}
.top .links{display:flex;gap:12px;align-items:center}
.top img{height:36px;border-radius:8px}.top a{color:#fff;font-size:14px;text-decoration:none}
.top a.bell{position:relative;font-size:19px}
.nb{position:absolute;top:-7px;right:-9px;background:#ffd95a;color:#16303d;border-radius:999px;font-size:11px;font-weight:700;padding:0 5px}
.top a.pts{background:rgba(255,255,255,.22);border-radius:999px;padding:4px 11px;font-size:13px;font-weight:700;transition:transform .3s}
.top a.pts.pop{transform:scale(1.25)}
.top h2{margin:16px 0 2px;font-size:24px}.top span{opacity:.9;font-size:14px}
.wrap{max-width:720px;margin:0 auto;padding:0 16px calc(env(safe-area-inset-bottom,0px) + 90px)}
.card{background:#fff;border-radius:18px;padding:16px;margin-top:14px;box-shadow:0 4px 18px rgba(0,80,120,.08)}
.card h3{margin:0 0 10px;font-size:17px}.mu{color:var(--mu);font-size:14px}
.view[hidden]{display:none}.first{margin-top:-18px}
nav.tabs{position:fixed;left:0;right:0;bottom:0;background:#fff;display:flex;justify-content:center;gap:4px;padding:8px 8px calc(env(safe-area-inset-bottom,0px) + 8px);box-shadow:0 -4px 18px rgba(0,80,120,.12);z-index:10}
nav.tabs button{flex:1;max-width:150px;border:0;background:none;padding:8px 2px;border-radius:12px;font:600 12px inherit;font-family:inherit;color:var(--mu);cursor:pointer;position:relative}
nav.tabs button b{display:block;font-size:20px;font-weight:400}
nav.tabs button.on{background:#e6f6fc;color:var(--dk)}
.badge{position:absolute;top:2px;right:14%;background:var(--hi);color:#fff;border-radius:999px;font-size:11px;padding:1px 6px}

/* Profile / risk */
.who{display:flex;gap:14px;align-items:center}
.av{width:56px;height:56px;border-radius:50%;background:var(--bl);color:#fff;display:grid;place-items:center;font-size:22px;font-weight:700;flex:none}
.ins{display:flex;gap:12px;padding:12px 0;border-top:1px solid #e3eef4}.ins:first-of-type{border-top:0}.ins .ic{font-size:26px}
.pill{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;color:#fff;vertical-align:middle}
.High{background:var(--hi)}.Medium{background:var(--md)}.Low{background:var(--lo)}.ok{background:#e1f4ea;color:var(--lo)}
.meter{height:10px;background:#e3eef4;border-radius:8px;overflow:hidden;margin:6px 0}.meter i{display:block;height:100%;transition:width .5s}
.pmeter i{background:linear-gradient(90deg,var(--gold),#ffd95a)}
.score{display:flex;align-items:center;gap:16px}
.ring{width:96px;height:96px;border-radius:50%;display:grid;place-items:center;flex:none}
.ring b{width:72px;height:72px;border-radius:50%;background:#fff;display:grid;place-items:center;font-size:21px}
.lv{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:14px}
.lv div{border-radius:14px;padding:12px;text-align:center;color:#fff}.lv b{display:block;font-size:24px}
.arow{margin:12px 0}.arow .t{display:flex;justify-content:space-between;font-size:14px;font-weight:600}
.rl{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-top:1px solid #e3eef4;font-size:14px}.rl:first-of-type{border-top:0}

/* Alerts */
.al{border-left:6px solid var(--c)}.al h4{margin:8px 0 4px;font-size:16px}
.al .what{background:#fff5f5;border-radius:12px;padding:10px;margin:10px 0;font-size:14px}.al .what.Medium{background:#fff8ec}
.al .row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.al button,.sub{border:0;border-radius:999px;padding:10px 18px;font:600 14px inherit;font-family:inherit;cursor:pointer}
.go{background:var(--bl);color:#fff}.sn{background:#e9f1f6;color:var(--tx)}
.sub{width:100%;background:var(--bl);color:#fff;padding:14px;font-size:16px;margin-top:16px}

/* Forms */
.sec{margin:22px 0 4px;padding-bottom:6px;border-bottom:2px solid #e3eef4;color:var(--dk);font-weight:700}
.f label{display:block;font-size:14px;font-weight:600;margin:14px 0 6px}
.f input,.f select{width:100%;padding:12px;font:inherit;font-size:16px;border:1.5px solid #c9d8e1;border-radius:12px;background:#fff;color:var(--tx)}
.f input:focus,.f select:focus{outline:none;border-color:var(--bl);box-shadow:0 0 0 3px rgba(0,161,213,.2)}
details.more{margin-top:18px;background:#f4f8fb;border-radius:14px;padding:10px 14px}
details.more summary{cursor:pointer;font-weight:700;color:var(--dk);padding:6px 0}
.tag{display:inline-block;background:#e6f6fc;color:var(--dk);border-radius:999px;font-size:12px;font-weight:700;padding:3px 10px}

/* Toast */
.toast{position:fixed;left:50%;bottom:90px;transform:translateX(-50%);background:#16303d;color:#fff;padding:10px 18px;border-radius:16px;font-size:14px;opacity:0;pointer-events:none;transition:opacity .3s;z-index:40;max-width:90vw;text-align:center}.toast.on{opacity:1}

/* Points */
.pbig{display:flex;align-items:center;gap:14px}
.pbig .n{font-size:38px;font-weight:800;color:var(--gold);line-height:1}
.pbig .l{font-weight:700}
.earn{display:grid;gap:6px;margin-top:6px;font-size:14px}
.earn div{display:flex;justify-content:space-between;gap:10px;background:#f4f8fb;border-radius:10px;padding:8px 10px}
.rw{display:flex;gap:10px;align-items:center;padding:10px 0;border-top:1px solid #e3eef4;font-size:14px}.rw:first-of-type{border-top:0}
.rw.lock{opacity:.5}.rw .ic{font-size:22px}
.lg{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-top:1px solid #e3eef4;font-size:14px}.lg:first-of-type{border-top:0}
.lg b{color:var(--lo);white-space:nowrap}
.rst{display:block;text-align:center;font-size:12px;color:var(--mu);margin-top:18px}

/* Notifications */
.np{position:fixed;left:12px;right:12px;margin:0 auto;max-width:420px;top:calc(env(safe-area-inset-top,0px) + 64px);max-height:70vh;overflow:auto;background:#fff;color:var(--tx);border-radius:18px;box-shadow:0 10px 40px rgba(0,60,90,.3);display:none;z-index:25;padding:12px}
.np.on{display:block}
.ni{border-left:5px solid var(--c);padding:8px 10px;margin:8px 0;background:#f4f8fb;border-radius:10px;font-size:14px}
.ni.unread{background:#e6f6fc}
.ni .r{display:flex;gap:8px;margin-top:8px;flex-wrap:wrap}
.ni button,.np .pb{border:0;border-radius:999px;padding:7px 12px;font:600 12px inherit;font-family:inherit;cursor:pointer;background:var(--bl);color:#fff}
.ni button.g{background:#e9f1f6;color:var(--tx)}

/* Guide */
.step{display:flex;gap:12px;padding:12px 0;border-top:1px solid #e3eef4}
.step:first-of-type{border-top:0}
.step .n{width:34px;height:34px;border-radius:50%;background:var(--bl);color:#fff;display:grid;place-items:center;font-weight:700;flex:none}
.step b{display:block}
.legend{display:grid;gap:8px;margin-top:6px}
.legend div{display:flex;gap:10px;align-items:flex-start;font-size:14px}
.legend .pill{min-width:76px;text-align:center}
.two{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.two div{background:#f4f8fb;border-radius:12px;padding:10px;font-size:14px}
.two b{display:block;margin-bottom:4px}
@media(max-width:520px){.two{grid-template-columns:1fr}}

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
    <div class="links">
      <a href="#" class="bell" onclick="toggleNP();return false" aria-label="Notifications">🔔<span class="nb" id="nb"></span></a>
      <a href="#" class="pts" id="hp" onclick="show('profile');return false">⭐ 0</a>
      <a href="#" onclick="show('guide');return false" aria-label="Guide">❓</a>
      <a href="index.php?logout=1">Sign out</a>
    </div></div>
  <h2 id="ttl">Profile</h2><span id="sub">Your details and insurances</span>
</header>

<div class="wrap">

  <!-- PROFILE -->
  <section class="view" id="v-profile">
    <div class="card first">
      <div class="who"><div class="av" id="av"></div><div><h3 style="margin:0" id="pn"></h3><div class="mu" id="pa"></div></div></div>
      <div class="meter pmeter"><i id="pbar" style="width:0"></i></div>
      <div class="mu" id="ptxt"></div>
    </div>
    <div class="card"><h3>My insurances</h3><div id="ins"></div></div>
    <div class="card">
      <h3>⭐ Points &amp; rewards</h3>
      <div class="pbig"><div class="n" id="ptotal">0</div><div><div class="l" id="plevel"></div><div class="mu" id="pnext"></div></div></div>
      <h3 style="margin-top:16px;font-size:15px">How to earn points</h3>
      <div class="earn">
        <div><span>✅ Complete a <b>high-risk</b> action</span><b>+50</b></div>
        <div><span>✅ Complete a <b>medium-risk</b> action</span><b>+20</b></div>
        <div><span>🧾 Update your assessment (once a day)</span><b>+10</b></div>
      </div>
      <h3 style="margin-top:16px;font-size:15px">Rewards <span class="mu">(example perks)</span></h3>
      <div id="rewards"></div>
    </div>
    <div class="card"><h3>Recent activity</h3><div id="plog"></div></div>
    <div class="card"><h3>Overview</h3><div id="ov" class="mu"></div></div>
    <a class="rst" href="index.php?reset=1" onclick="try{localStorage.clear()}catch(e){}">Reset demo data</a>
  </section>

  <!-- RISK DASHBOARD -->
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

  <!-- ALERTS -->
  <section class="view" id="v-alerts" hidden><div id="alerts"></div></section>

  <!-- ASSESS: quick check + optional full assessment -->
  <section class="view" id="v-assess" hidden>
    <div class="card first f">
      <span class="tag">⚡ Quick check · about 1 minute</span>
      <h3 style="margin-top:10px">🏠 Home risk check</h3>
      <div class="mu">Answer a few questions to see your main risks. Add more details later for a sharper result. Answers are used only to show your own dashboard, not for pricing.</div>
      <form id="form" novalidate></form>
    </div>
  </section>

  <!-- GUIDE + WIN-WIN -->
  <section class="view" id="v-guide" hidden>
    <div class="card first">
      <h3>Welcome to TurvaTarkistus</h3>
      <div class="mu">The best loss is the one that never happens. Find your home's risks, get simple actions and earn points for preventing damage.</div>
    </div>
    <div class="card">
      <h3>How it works</h3>
      <div class="step"><div class="n">1</div><div><b>Quick check</b><span class="mu">Answer a few questions in the <b>Assess</b> tab. Add details any time.</span></div></div>
      <div class="step"><div class="n">2</div><div><b>See your risk</b><span class="mu">The <b>Risk</b> tab shows your overall score and fire, water, security, property and claims risk.</span></div></div>
      <div class="step"><div class="n">3</div><div><b>Act and earn points</b><span class="mu">The <b>Alerts</b> tab says what could happen and what to do. Tap <b>Mark as done</b> to earn points.</span></div></div>
      <div class="step"><div class="n">4</div><div><b>Get reminded</b><span class="mu">The bell at the top warns you about overdue checks, seasonal risks and stale assessments.</span></div></div>
    </div>
    <div class="card">
      <h3>What the risk levels mean</h3>
      <div class="legend">
        <div><span class="pill Low">LOW</span><span>Under 25%. Well protected.</span></div>
        <div><span class="pill Medium">MEDIUM</span><span>25–49%. A few improvements will help.</span></div>
        <div><span class="pill High">HIGH</span><span>50–74%. Act soon to prevent damage.</span></div>
        <div><span class="pill" style="background:#8f1d1d">CRITICAL</span><span>75% or more. Needs attention now.</span></div>
      </div>
    </div>
    <div class="card">
      <h3>Win-win</h3>
      <div class="two">
        <div><b>👤 For the customer</b>Fewer accidents and damages, simple actions, points, rewards and a feeling of control.</div>
        <div><b>🏢 For LocalTapiola</b>Fewer and smaller claims, a regular customer touchpoint, anonymised regional risk insight, higher loyalty and a natural moment to review coverage.</div>
      </div>
    </div>
    <div class="card">
      <h3>Challenges and next steps</h3>
      <div class="mu">• Privacy, consent and clear use of data<br>• A sustainable reward model<br>• Expert-validated rules from real claims data<br>• Real policy data and server-side scoring<br>• Push, SMS or email notifications from a scheduled job<br>• Extend to traffic, company and farm customers<br>• Pilot in one region and season, then measure claims impact</div>
    </div>
    <div class="card">
      <h3>Good to know</h3>
      <div class="mu">This is a prototype. Scores come from a simplified demo model and are not an insurance decision, price or underwriting result. Rewards are examples only.</div>
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
<div class="np" id="np"></div>

<button class="chat-fab" id="fab" onclick="toggleChat()" aria-label="Open chatbot">💬<span class="dot" id="fabDot"></span></button>
<div class="chat" id="chat" role="dialog" aria-label="Risk Analysis Assistant">
  <div class="chat-h"><div><b>🛡️ Risk Analysis Assistant</b><span>Ask about risks, insurances and points</span></div>
    <button onclick="toggleChat()" aria-label="Close">✕</button></div>
  <div class="chat-m" id="chatM"></div>
  <div class="chips" id="chips"></div>
  <form class="chat-f" onsubmit="return sendChat(event)">
    <input id="chatIn" placeholder="Type your question…" autocomplete="off">
    <button type="submit">Send</button>
  </form>
</div>

<script>
const $ = id => document.getElementById(id);
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

/* ============ 1. QUESTIONNAIRE (edit here to add or change questions) ============
   [id, label, type, options "value:text|value:text"]  ·  ['#', 'Section title']
   Questions listed in QUICK appear in the 1-minute quick check; the rest are optional. */
const Q = [
['#','1. About you'],
['fullName','Full name','text'],['address','Address (city)','text'],['dob','Date of birth','date'],
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
['workHome','Work from home or business at home?','select','0:No|2:Part-time remote|4:Full-time remote|6:Business at home'],
['occupants','Who lives in the home?','select','0:One adult|1:Two adults|2:Family with children|3:Multiple unrelated occupants'],
['pets','Pets','select','0:No pets|1:Dog / cat|2:Multiple / other pets'],
['#','6. Claims & coverage'],
['claims','Claims in the last 5 years','select','0:None|5:One small claim|10:Two claims|18:Three or more'],
['buildingValue','Building coverage amount (€)','number'],
['#','7. Recent incident'],
['recentIncident','Recent incident?','select','0:No|8:Yes, minor|15:Yes, significant'],
['injury','Anyone injured?','select','0:No|10:Yes'],
['ongoing','Damage still ongoing?','select','0:No|10:Yes'],
['causeStopped','Cause stopped / water valve closed?','select','0:Yes|5:No / not yet|3:Yes, but valve hard to find'],
['damage','Estimated damage','select','0:No recent damage|3:Under €2,000|6:€2,000–5,000|10:€5,000–10,000|15:Over €10,000']
];
const QUICK = ['fullName','address','builtYear','propertyType','smokeAlarms','checks','waterValve','emptyPeriods'];
const opts = s => s.split('|').map(o => { const i=o.indexOf(':'); return [o.slice(0,i), o.slice(i+1)]; });
const DEMO = {fullName:'Matti Meikäläinen',dob:'1985-05-12',address:'Vaasa',builtYear:1988,floorArea:140,buildingValue:350000,
  propertyType:'4',construction:'4',roof:'4',saunaFireplace:'7',smokeAlarms:'8',checks:'7',waterValve:'5',emptyPeriods:'5',security:'4'};
let ANS = {}; Q.forEach(q => { if (q[2]==='select') ANS[q[0]] = opts(q[3])[0][0]; }); Object.assign(ANS, DEMO);
let HIST = [], SC = {};
let GAME = {points:0, awarded:[], log:[]};

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
  const claims = Math.min(100, v('claims')*3+v('recentIncident')*3+v('injury')*3+v('ongoing')*3+v('damage')*2);
  const overall = Math.round(Math.min(100, fire*.20 + water*.25 + security*.15 + prop*.20 + claims*.20));
  return {fire:Math.round(fire),water:Math.round(water),security:Math.round(security),property:Math.round(prop),claims:Math.round(claims),overall};
}
const level = p => p<25?'Low':p<50?'Medium':p<75?'High':'Critical';
const col = p => p<25?'var(--lo)':p<50?'var(--md)':'var(--hi)';

/* ============ 3. ALERT RULES (answers -> notifications) ============ */
const RULES = [
{id:'chk',cat:'Fire',ins:'Home insurance',on:n=>n.checks>=3,lv:n=>n.checks>=7?'High':'Medium',
 t:'Chimney and washing-machine hose inspection overdue',
 why:'Not inspected for years: soot build-up can cause a chimney fire and aged hoses can burst and flood the home. Neglected maintenance can also affect compensation.',
 act:'Book a chimney sweep and replace hoses older than 10 years.',fix:{checks:0}},
{id:'smoke',cat:'Fire',ins:'Home insurance',on:n=>n.smokeAlarms>0,lv:n=>n.smokeAlarms>=15?'High':'Medium',
 t:'Smoke alarms missing or incomplete',why:'A fire can spread unnoticed, especially at night. Late detection is a main factor in serious injuries and total losses.',
 act:'Install and test an alarm on every floor.',fix:{smokeAlarms:0}},
{id:'ext',cat:'Fire',ins:'Home insurance',on:n=>n.fireExtinguisher>0,lv:()=>'Medium',
 t:'No fire extinguisher',why:'A small kitchen or sauna fire can become a major loss before help arrives.',
 act:'Keep an extinguisher or fire blanket near the exit.',fix:{fireExtinguisher:0}},
{id:'valve',cat:'Water',ins:'Home insurance',on:n=>n.waterValve>0,lv:n=>n.waterValve>=10?'High':'Medium',
 t:'Water shut-off valve location unknown',why:'A leak you cannot stop quickly turns a small pipe failure into major water damage.',
 act:'Find the main valve, label it and test that it turns.',fix:{waterValve:0}},
{id:'incident',cat:'Water',ins:'Home insurance',on:n=>n.recentIncident>0||n.ongoing>0,lv:n=>(n.ongoing>0||n.recentIncident>=15)?'High':'Medium',
 t:'Recent damage incident not resolved',why:'Unrepaired or ongoing damage can spread (mould, structural damage) and increase the final claim cost.',
 act:'Stop the cause, document the damage with photos and contact claims support.',fix:{recentIncident:0,ongoing:0,causeStopped:0,damage:0,injury:0}},
{id:'empty',cat:'Security',ins:'Home insurance',on:n=>n.emptyPeriods>0,lv:n=>n.emptyPeriods>=10?'High':'Medium',
 t:'Home often empty',why:'Long empty periods raise the risk of unnoticed leaks, frost damage and burglary.',
 act:'Install leak sensors, ask a neighbour to check in and use timers on lights.',fix:{emptyPeriods:0}},
{id:'sec',cat:'Security',ins:'Home insurance',on:n=>n.security>=4,lv:n=>n.security>=7?'High':'Medium',
 t:'Basic home security only',why:'Weak locks and no alarm make burglary easier, especially with valuables at home.',
 act:'Add security locks, an alarm and leak sensors.',fix:{security:0}},
{id:'roof',cat:'Property',ins:'Home insurance',on:n=>n.roof>=4,lv:n=>n.roof>=6?'High':'Medium',
 t:'Roof condition old or unknown',why:'An aged roof can leak or fail in storms or heavy snow, causing water and structural damage.',
 act:'Have the roof inspected and plan maintenance.',fix:{roof:0}},
{id:'old',cat:'Property',ins:'Home insurance',on:n=>(new Date().getFullYear()-n.builtYear)>40&&n.renovation>=4,lv:()=>'Medium',
 t:'Older building without major renovation',why:'Old pipes, wiring and insulation are common causes of water and fire damage.',
 act:'Inspect pipes and electrics; plan renovation.',fix:{renovation:0}}
];
const INSURANCES = [
 {n:'Home insurance',i:'🏠',d:'Fire, water damage, theft',s:'Active'},
 {n:'Car insurance',i:'🚗',d:'Motor liability + comprehensive · ABC-123',s:'Active',note:'Traffic risk check coming soon'},
 {n:'Accident insurance',i:'🩹',d:'Leisure-time accidents · family',s:'Active',note:'Wellbeing check coming soon'}
];
const ORDER = {High:0,Medium:1};
const optText = (id,val) => { const q=Q.find(x=>x[0]===id); const o=q&&q[3]?opts(q[3]).find(o=>o[0]==val):null; return o?o[1]:''; };
function liveAlerts(){
  const n=num();
  return RULES.map((r,i)=>({...r,i})).filter(r=>r.on(n)).map(r=>({...r,l:r.lv(n)})).sort((a,b)=>ORDER[a.l]-ORDER[b.l]);
}

/* ============ 4. POINT SYSTEM ============ */
const PTS = {High:50, Medium:20, Assess:10};
const LEVELS = [{n:'Bronze',i:'🥉',min:0},{n:'Silver',i:'🥈',min:100},{n:'Gold',i:'🥇',min:250},{n:'Platinum',i:'💎',min:500}];
const REWARDS = [ // example perks, to be defined with the insurer
 {min:100,i:'📞',t:'Silver: priority customer support'},
 {min:250,i:'🎁',t:'Gold: partner discount on safety products (e.g. smoke alarm)'},
 {min:500,i:'💶',t:'Platinum: bonus towards owner-customer benefits'}
];
const lvlOf = p => [...LEVELS].reverse().find(l=>p>=l.min);
function award(key, text, pts){
  if (GAME.awarded.includes(key)) return 0;
  GAME.awarded.push(key); GAME.points += pts;
  GAME.log.push({t:text, p:pts, at:new Date().toISOString()});
  GAME.log = GAME.log.slice(-30);
  return pts;
}
function levelUpToast(before){
  const a=lvlOf(GAME.points); if (a.n!==before.n) setTimeout(()=>toast(`Level up: ${a.i} ${a.n}`),2300);
}
function renderPoints(){
  const p=GAME.points, cur=lvlOf(p), next=LEVELS.find(l=>l.min>p);
  $('hp').textContent='⭐ '+p;
  $('ptotal').textContent=p; $('plevel').textContent=`${cur.i} ${cur.n} level`;
  const pct = next ? Math.round((p-cur.min)/(next.min-cur.min)*100) : 100;
  $('pbar').style.width=pct+'%';
  const txt = next ? `${next.min-p} points to ${next.i} ${next.n}` : 'Top level reached!';
  $('pnext').textContent=txt; $('ptxt').textContent=`${cur.i} ${cur.n} · ${p} points · ${txt}`;
  $('rewards').innerHTML=REWARDS.map(r=>`<div class="rw ${p>=r.min?'':'lock'}"><span class="ic">${p>=r.min?r.i:'🔒'}</span><span>${r.t}<br><span class="mu">${p>=r.min?'Unlocked':'Unlocks at '+r.min+' points'}</span></span></div>`).join('');
  $('plog').innerHTML=GAME.log.length?[...GAME.log].reverse().slice(0,8).map(l=>
    `<div class="lg"><span>${esc(l.t)}<br><span class="mu">${l.at?new Date(l.at).toLocaleDateString('en-GB'):''}</span></span><b>+${l.p}</b></div>`).join('')
    :'<div class="mu">No points yet. Complete an action in the Alerts tab to earn your first points.</div>';
}

/* ============ 5. NOTIFICATIONS ============ */
let NS = {read:{}, snooze:{}};
try { NS = JSON.parse(localStorage.getItem('notif')) || NS; } catch(e){}
const saveNS = () => { try { localStorage.setItem('notif', JSON.stringify(NS)); } catch(e){} };
function notifs(){
  const n=num(), out=[], m=new Date().getMonth()+1;
  RULES.forEach(r=>{ if(r.on(n)) out.push({id:'r'+r.id,l:r.lv(n),t:r.t,b:r.why,go:'alerts'}); });      // risk-based
  if(m>=10||m<=3) out.push({id:'s-winter',l:'Medium',t:'Winter is coming: tyres, elk and frost',          // seasonal
    b:'Check your tyres and know the elk-collision steps. Frost can burst pipes in empty or poorly heated buildings.',go:'alerts'});
  if(HIST.length){                                                                                        // stale assessment
    const d=(Date.now()-new Date(HIST[HIST.length-1].at))/864e5;
    if(d>90) out.push({id:'s-stale',l:'Medium',t:'Time to update your assessment',b:`Your last check was ${Math.round(d)} days ago. Homes change, and a quick update keeps your risks accurate.`,go:'assess'});
  }
  return out.filter(x=>!(NS.snooze[x.id]>Date.now())).sort((a,b)=>(a.l==='High'?0:1)-(b.l==='High'?0:1));
}
function renderNotifs(){
  const L=notifs(), un=L.filter(x=>!NS.read[x.id]).length;
  $('nb').textContent=un; $('nb').style.display=un?'':'none';
  $('np').innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center"><b>Notifications</b><button class="pb" onclick="readAll()">Mark all read</button></div>`
   +(L.length?L.map(x=>`<div class="ni ${NS.read[x.id]?'':'unread'}" style="--c:${x.l==='High'?'var(--hi)':'var(--md)'}">
      <span class="pill ${x.l}">${x.l.toUpperCase()}</span> <b>${esc(x.t)}</b><div class="mu">${esc(x.b)}</div>
      <div class="r"><button onclick="openN('${x.id}')">Open</button><button class="g" onclick="snoozeN('${x.id}')">Remind in 7 days</button></div></div>`).join('')
     :'<div class="mu" style="padding:12px">No notifications</div>')
   +`<div class="ni" style="--c:var(--bl)"><div class="mu">Get alerts on your phone or desktop even when the app is closed.</div><div class="r"><button onclick="enablePush()">Enable push</button><button class="g" onclick="pushTop(true)">Send test push</button></div></div>`;
}
function toggleNP(){ $('np').classList.toggle('on'); }
function openN(id){ const x=notifs().find(y=>y.id===id); NS.read[id]=1; saveNS(); $('np').classList.remove('on'); renderNotifs(); if(x) show(x.go); }
function snoozeN(id){ NS.snooze[id]=Date.now()+7*864e5; saveNS(); renderNotifs(); toast('⏰ We will remind you in 7 days'); }
function readAll(){ notifs().forEach(x=>NS.read[x.id]=1); saveNS(); renderNotifs(); }
function enablePush(){
  if(!('Notification' in window)){ toast('Push is not supported in this browser'); return; }
  Notification.requestPermission().then(p=>{ toast(p==='granted'?'✅ Push enabled':'Push was blocked'); if(p==='granted') pushTop(true); });
}
function pushTop(force){
  if(!('Notification' in window)||Notification.permission!=='granted'){ if(force) toast('Enable push first'); return; }
  const L=notifs().filter(x=>force||(x.l==='High'&&!NS.read[x.id])); if(!L.length) return;
  new Notification('TurvaTarkistus: '+L[0].t,{body:L[0].b.slice(0,110)});
}

/* ============ 6. UI ============ */
function show(v){
  ['profile','risk','alerts','assess','guide'].forEach(x=>{
    $('v-'+x).hidden = x!==v;
    const b=$('b-'+x); if(b) b.className = x===v?'on':'';
  });
  const T={profile:['Profile','Your details, points and insurances'],risk:['Risk dashboard','Where your biggest risks are'],alerts:['Alerts','Act on these to prevent damage and earn points'],assess:['Assessment','Quick check and optional details'],guide:['Guide','How TurvaTarkistus works']};
  $('ttl').textContent=T[v][0]; $('sub').textContent=T[v][1]; $('np').classList.remove('on'); scrollTo(0,0);
}
function closeGuide(){ try{localStorage.setItem('guideSeen','1')}catch(e){} show('profile'); }
function toast(m){ const t=$('toast'); t.textContent=m; t.classList.add('on'); setTimeout(()=>t.classList.remove('on'),2400); }
function pop(){ const h=$('hp'); h.classList.add('pop'); setTimeout(()=>h.classList.remove('pop'),400); }

function field(q){
  const [id,label,type,o]=q, val=ANS[id]??'';
  return `<label for="q_${id}">${label}</label>` + (type==='select'
    ? `<select id="q_${id}">${opts(o).map(([v,t])=>`<option value="${v}" ${String(val)===v?'selected':''}>${t}</option>`).join('')}</select>`
    : `<input id="q_${id}" type="${type}" value="${String(val).replace(/"/g,'&quot;')}" ${type==='number'?'inputmode="numeric"':''}>`);
}
function buildForm(){
  const byId = id => Q.find(q=>q[0]===id);
  let rest=''; Q.forEach(q=>{ if(q[0]==='#') rest+=`<div class="sec">${q[1]}</div>`; else if(!QUICK.includes(q[0])) rest+=field(q); });
  $('form').innerHTML = QUICK.map(id=>field(byId(id))).join('')
    + `<details class="more"><summary>➕ Add more details for a sharper result (optional)</summary>${rest}</details>`
    + `<button class="sub" type="submit">🔍 Save and analyse risk</button>`;
}
$('form').addEventListener('submit', e => {
  e.preventDefault();
  Q.forEach(q=>{ if(q[0]!=='#' && $('q_'+q[0])) ANS[q[0]] = $('q_'+q[0]).value; });
  if (!String(ANS.fullName).trim() || !ANS.builtYear) { toast('Please fill in name and year built'); return; }
  const before=lvlOf(GAME.points);
  const got=award('assess-'+new Date().toISOString().slice(0,10),'Updated risk assessment',PTS.Assess);
  save().then(()=>{ show('risk'); toast(got?`✅ Saved · +${got} points`:'✅ Assessment saved. Dashboard updated'); if(got){pop();levelUpToast(before);} });
});

async function save(){
  SC = score(num());
  try {
    const r = await fetch('index.php?api=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({answers:ANS,scores:SC,game:GAME})});
    const d = await r.json(); HIST = d.history || HIST;
  } catch(e) { toast('Could not save to server (showing local result)'); }
  render();
}
function applyFix(i){
  const n=num(), r=RULES[i], pts=PTS[r.lv(n)] || PTS.Medium, before=lvlOf(GAME.points);
  const got=award('rule-'+r.id,'Completed: '+r.t,pts);
  Object.assign(ANS, r.fix); buildForm();
  save().then(()=>{
    toast(got?`✅ Done! +${got} points`:'✅ Done. Points were already earned for this one');
    if(got){pop();levelUpToast(before);}
  });
}

function render(){
  const n=num(); SC=score(n);
  const A=liveAlerts();
  const nH=A.filter(a=>a.l==='High').length, nM=A.length-nH, nL=RULES.length-A.length;
  const lv=level(SC.overall);

  // profile
  $('av').textContent=(String(n.fullName||'?')).split(' ').map(s=>s[0]).join('').slice(0,2).toUpperCase();
  $('pn').textContent=n.fullName; $('pa').textContent=`Owner-customer · ${n.address||''} · ${optText('propertyType',ANS.propertyType)}`;
  $('ins').innerHTML=INSURANCES.map(x=>{
    const open=A.filter(a=>a.ins===x.n).length;
    const extra = x.n==='Home insurance' && n.buildingValue ? ` · cover €${Number(n.buildingValue).toLocaleString('en')}` : '';
    return `<div class="ins"><div class="ic">${x.i}</div><div style="flex:1"><b>${x.n}</b> <span class="pill ok">${x.s}</span>
      <div class="mu">${x.d}${extra}</div><div class="mu">${x.note?'ℹ️ '+x.note:open?`⚠️ ${open} open risk${open>1?'s':''} linked`:'✅ No open risks'}</div></div></div>`;}).join('');
  $('ov').innerHTML=`Overall home risk <b>${SC.overall}% (${lv})</b> · ${nH} high, ${nM} medium. Last assessed: ${HIST.length?new Date(HIST[HIST.length-1].at).toLocaleDateString('en-GB'):'demo data (not yet saved)'}.`;
  renderPoints();

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
    const note=a.id==='chk'?` Last check: <b>${optText('checks',ANS.checks)}</b>.`:'';
    const pts=PTS[a.l], earned=GAME.awarded.includes('rule-'+a.id);
    return `<div class="card al" style="--c:${a.l==='High'?'var(--hi)':'var(--md)'}">
      <span class="pill ${a.l}">${a.l.toUpperCase()} RISK</span> <span class="mu">· ${a.cat}</span>
      <h4>${a.t}</h4>
      <div class="what ${a.l}"><b>What could happen:</b> ${a.why}${note}</div>
      <div class="mu"><b>Recommended:</b> ${a.act}</div><div class="mu">🛡️ Related insurance: ${a.ins}</div>
      <div class="row"><button class="go" onclick="applyFix(${a.i})">Mark as done${earned?'':' · ⭐ +'+pts+' pts'}</button><button class="sn" onclick="snoozeN('r${a.id}')">Remind me later</button></div></div>`;}).join('')
    :`<div class="card first" style="text-align:center"><b>No open alerts</b><div class="mu">Your home looks well protected.</div></div>`;
  if(A.length) $('alerts').firstElementChild.classList.add('first');
  renderNotifs();
}

/* ============ 7. CHATBOT (rule-based; uses the customer's live data) ============ */
const CHIPS = [
  ['What is my risk score?'],['What should I fix first?'],['How do I earn points?'],
  ['What insurances do I have?'],['How can I lower my risk?'],['Open my alerts','__alerts']
];
function bot(html){ const d=document.createElement('div'); d.className='msg bot'; d.innerHTML=html; $('chatM').appendChild(d); $('chatM').scrollTop=1e9; }
function me(t){ const d=document.createElement('div'); d.className='msg me'; d.textContent=t; $('chatM').appendChild(d); $('chatM').scrollTop=1e9; }
function renderChips(){ $('chips').innerHTML = CHIPS.map(([t],i)=>`<button onclick="chip(${i})">${t}</button>`).join(''); }
function chip(i){ const [t,a]=CHIPS[i]; if(a==='__alerts'){ toggleChat(); show('alerts'); return; } ask(t); }
function toggleChat(){
  const c=$('chat'); c.classList.toggle('on');
  if(c.classList.contains('on')){
    $('fabDot').style.display='none';
    if(!$('chatM').children.length){
      const n=num(), nH=liveAlerts().filter(a=>a.l==='High').length;
      bot(`Hi ${esc(String(n.fullName||'there').split(' ')[0])}, I'm your safety assistant. `+
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

  if(has('hello','hi ','hey','moi')) return 'Hello! Ask me about your risk score, alerts, insurances or points.';
  if(has('how to use','guide','instruction','how does this work')) return 'Open the help icon at the top of the page for a quick walkthrough.';
  if(has('notification','remind','push')) return 'Tap the bell at the top. It lists overdue checks, seasonal risks and reminders. You can snooze items or enable push notifications there.';
  if(has('point','reward','level','bonus','perk')){
    const p=GAME.points, cur=lvlOf(p), next=LEVELS.find(l=>l.min>p);
    return `You have <b>${p} points</b> (${cur.i} ${cur.n}). ${next?`${next.min-p} more to reach ${next.i} ${next.n}.`:'You are at the top level!'}<br><br>`+
      `Earn points by:<br>• High-risk action done: <b>+${PTS.High}</b><br>• Medium-risk action done: <b>+${PTS.Medium}</b><br>• Updating your assessment (once a day): <b>+${PTS.Assess}</b><br><br>`+
      (top?`Biggest points right now: <b>${esc(top.t)}</b> (+${PTS[top.l]}).`:'No open actions at the moment.');
  }
  if(has('score','overall','how risky','risk level')){
    return `Your overall home risk is <b>${s.overall}% (${level(s.overall)})</b>.<br>🔥 Fire ${s.fire}% · 💧 Water ${s.water}% · 🔐 Security ${s.security}% · 🏠 Property ${s.property}% · 📋 Claims ${s.claims}%.`;
  }
  if(has('first','urgent','priority','biggest','important','alert')){
    if(!top) return 'No open alerts. Nothing urgent right now.';
    return `Top priority: <b>${esc(top.t)}</b> (${top.l} risk, +${PTS[top.l]} points).<br><br><b>What could happen:</b> ${esc(top.why)}<br><br><b>Do this:</b> ${esc(top.act)}`;
  }
  if(has('insurance','policy','cover','insured','vakuutus')){
    return INSURANCES.map(x=>`${x.i} <b>${x.n}</b>: ${x.d}`).join('<br>') + (n.buildingValue?`<br><br>Home cover amount: €${Number(n.buildingValue).toLocaleString('en')}.`:'');
  }
  if(has('lower','reduce','improve','tips','better','decrease','safer')){
    if(!A.length) return 'Your risk is already low. Keep your checks up to date every few months.';
    return 'Quick wins to lower your risk:<br>'+A.slice(0,4).map(a=>`• ${esc(a.act)} (+${PTS[a.l]} pts)`).join('<br>')+'<br><br>Tap <b>Mark as done</b> in Alerts when finished and your score updates.';
  }
  if(has('chimney','hose','sweep')) return 'Chimneys and washing-machine hoses should be checked regularly. Soot can cause a chimney fire, and old hoses can burst and flood your home. Book a chimney sweep and replace hoses older than ~10 years.';
  if(has('water','leak','valve','flood')) return `Water damage is one of the most common home claims. Know where your main shut-off valve is and consider leak sensors. Your water risk is <b>${s.water}%</b>.`;
  if(has('fire','smoke','extinguisher','sauna')) return `Test smoke alarms on every floor and keep an extinguisher or fire blanket near the exit. Your fire risk is <b>${s.fire}%</b>.`;
  if(has('burglar','theft','security','lock','empty')) return `Security locks, an alarm and checks while you are away reduce break-in risk. Your security risk is <b>${s.security}%</b>.`;
  if(has('roof')) return 'An old roof can leak or fail in storms and heavy snow. A professional inspection every few years is a good idea.';
  if(has('claim','damage','accident')) return 'If you have had damage, stop the cause (e.g. close the water valve), take photos, and contact claims support. You can also update your answers in the <b>Assess</b> tab.';
  if(has('thank')) return 'You are welcome!';
  return 'I can help with your <b>risk score</b>, <b>alerts</b>, <b>insurances</b>, <b>points</b> and tips to <b>lower your risk</b>. Try one of the buttons below.';
}

/* ============ 8. ICONS: swaps every emoji for a line icon (runs on all current and future content) ============ */
const P = {
 bell:'<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
 home:'<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
 car:'<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>',
 pulse:'<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/><path d="M3.5 12H8l2-3 3 6 2-3h2.5"/>',
 star:'<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.9L12 17.8 5.8 21.1 7 14.2 2 9.3l6.9-1z"/>',
 help:'<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
 chart:'<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
 list:'<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4M12 16h4M8 11h.01M8 16h.01"/>',
 user:'<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
 chat:'<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22z"/>',
 shield:'<path d="M20 13c0 5-3.5 7.5-7.7 8.9a1 1 0 0 1-.6 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1.2 1.2 0 0 1 1.6 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"/>',
 zap:'<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
 search:'<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
 check:'<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
 clock:'<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
 award:'<circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/>',
 medal:'<circle cx="12" cy="15" r="6"/><path d="M8.5 3 12 9l3.5-6"/>',
 gem:'<path d="M6 3h12l4 6-10 13L2 9z"/><path d="M2 9h20"/>',
 phone:'<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
 gift:'<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
 wallet:'<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
 lock:'<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
 info:'<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
 warn:'<path d="m21.7 18-8-14a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3"/><path d="M12 9v4M12 17h.01"/>',
 flame:'<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.4-.5-2-1-3-1.1-2.1-.2-4 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.2.4-2.3 1-3 0 1.4 1 2.5 2.5 2.5z"/>',
 drop:'<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
 heart:'<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/>',
 plus:'<path d="M12 5v14M5 12h14"/>',
 building:'<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>',
 trophy:'<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16M10 14.7V17c0 .6-.5 1-1.2 1.2C7.8 18.7 7 20.2 7 22M14 14.7V17c0 .6.5 1 1.2 1.2 1 .5 1.8 2 1.8 3.8"/><path d="M18 2H6v7a6 6 0 0 0 12 0z"/>',
 dot:'<circle cx="12" cy="12" r="6" fill="currentColor" stroke="none"/>'
};
// emoji -> [icon name, optional colour]
const EM = {'🔔':['bell'],'🏠':['home'],'🚗':['car'],'🩹':['pulse'],'⭐':['star'],'❓':['help'],'📊':['chart'],'🧾':['list'],'👤':['user'],
 '💬':['chat'],'🛡':['shield'],'⚡':['zap'],'🔍':['search'],'✅':['check'],'⏰':['clock'],'🎉':['award'],'🏆':['trophy'],
 '🥉':['medal','#b8733a'],'🥈':['medal','#9aa5b1'],'🥇':['medal','#f2b400'],'💎':['gem','#00a1d5'],'📞':['phone'],'🎁':['gift'],'💶':['wallet'],
 '🔒':['lock'],'ℹ':['info'],'⚠':['warn'],'🔥':['flame'],'💧':['drop'],'🔐':['lock'],'📋':['list'],'🟢':['dot','#2e8b57'],'🔴':['dot','#d63b3b'],
 '👋':[''],'💙':['heart','#00a1d5'],'➕':['plus'],'🏢':['building']};
const EMRX = new RegExp(Object.keys(EM).join('|'), 'u'), EMRXG = new RegExp(Object.keys(EM).join('|'), 'gu');
function icoEl(e){
  const [k,c]=EM[e]; if(!k) return document.createTextNode('');
  const s=document.createElement('span'); s.innerHTML=`<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"${c?` style="color:${c}"`:''}>${P[k]}</svg>`;
  return s.firstChild;
}
function iconify(root){
  const w=document.createTreeWalker(root,NodeFilter.SHOW_TEXT,{acceptNode:n=>/^(SCRIPT|STYLE|TEXTAREA|OPTION)$/.test(n.parentNode.nodeName)?NodeFilter.FILTER_REJECT:NodeFilter.FILTER_ACCEPT});
  const list=[]; while(w.nextNode()) list.push(w.currentNode);
  list.forEach(n=>{
    const t=n.nodeValue.replace(/\uFE0F/g,''); if(!EMRX.test(t)) return;
    const f=document.createDocumentFragment(); let last=0,m; EMRXG.lastIndex=0;
    while((m=EMRXG.exec(t))){ f.append(t.slice(last,m.index)); f.append(icoEl(m[0])); last=m.index+m[0].length; }
    f.append(t.slice(last)); n.replaceWith(f);
  });
}
let _ico=0;
new MutationObserver(()=>{ cancelAnimationFrame(_ico); _ico=requestAnimationFrame(()=>iconify(document.body)); })
  .observe(document.body,{childList:true,subtree:true,characterData:true});
iconify(document.body);

/* ============ 9. START ============ */
(async()=>{
  try {
    const r=await fetch('index.php?api=load'); const d=await r.json();
    if(d.answers) Object.assign(ANS,d.answers);
    HIST=d.history||[];
    if(d.game) GAME={points:d.game.points||0, awarded:d.game.awarded||[], log:d.game.log||[]};
  } catch(e){}
  buildForm(); render();
  try{ if(!localStorage.getItem('guideSeen')) show('guide'); }catch(e){}
  const u=notifs().filter(x=>!NS.read[x.id]).length;
  if(u) setTimeout(()=>toast('You have '+u+' new notification'+(u>1?'s':'')),700);
  pushTop();
})();
</script>
<?php endif; ?>
</body>
</html>