<!doctype html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>91VPN</title>
<meta name="description" content="解锁全球流媒体与 AI 的加速服务——像机场一样，直达你到不了的目的地">
<link rel="canonical" href="{{ url('/') }}">
{{-- `[!]` 此前 rel=icon 指向 og.jpg —— 拿 37 KB 的社交大图当浏览器标签图标。
     favicon.ico / favicon.svg 本来就在 public/ 下,只是没被引用。 --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('og.jpg') }}">
<meta name="theme-color" content="#F1EEE4">
<meta property="og:type" content="website">
<meta property="og:site_name" content="91VPN">
<meta property="og:title" content="91VPN — 解锁全球互联网">
<meta property="og:description" content="Netflix、YouTube、ChatGPT 一键畅连。香港就近入口，多地区高速直达，晚高峰也不卡。">
<meta property="og:url" content="{{ url('/') }}">
<meta property="og:image" content="{{ setting('og_image') ?: asset('og.jpg') }}">
<meta property="og:image:type" content="image/jpeg">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="91VPN — 解锁全球互联网">
<meta name="twitter:description" content="Netflix、YouTube、ChatGPT 一键畅连。香港就近入口，多地区高速直达。">
<meta name="twitter:image" content="{{ setting('og_image') ?: asset('og.jpg') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
@verbatim
<style>
:root{
  color-scheme: light;
  --bg:#F1EEE4; --board:#E7E2D4; --flap:#E9E3D5;
  --rule:#C4BCA8; --rule-soft:#D8D2C3; --seam:#DCD6C8;
  --ink:#1A160F; --dim:#5E5849; --faint:#928B78;
  --amber:#D8442A; --amber-hi:#EA4E2E; --amber-dim:#B23A20;
  --stamp:#BE4526; --ok:#D8442A;
  --sign:"Archivo","PingFang SC","Hiragino Sans GB","Microsoft YaHei","Noto Sans CJK SC","Source Han Sans SC",sans-serif;
  --mono:"JetBrains Mono",ui-monospace,monospace;
  --body:-apple-system,"Segoe UI",Roboto,"PingFang SC","Hiragino Sans GB","Microsoft YaHei","Noto Sans CJK SC","Source Han Sans SC",sans-serif;
  --maxw:1180px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--body);font-size:16px;line-height:1.6;-webkit-font-smoothing:antialiased}
@media (prefers-reduced-motion: reduce){html{scroll-behavior:auto} *{animation:none!important;transition:none!important}}
a{color:inherit;text-decoration:none}

/* 印刷质感:极淡纸张颗粒(multiply,像油墨落在纸上)。想更淡/更重改 opacity 即可 */
body::before{content:"";position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.05;mix-blend-mode:multiply;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E")}
/* 四角印刷套准/裁切标记:整张纸被"印在版上"的框感 */
.crop{position:fixed;inset:13px;z-index:4;pointer-events:none}
.crop i{position:absolute;width:11px;height:11px;opacity:.55}
.crop i::before,.crop i::after{content:"";position:absolute;background:var(--rule);top:0;left:0}
.crop i::before{width:11px;height:1px}
.crop i::after{width:1px;height:11px}
.crop i:nth-child(1){top:0;left:0}
.crop i:nth-child(2){top:0;right:0;transform:scaleX(-1)}
.crop i:nth-child(3){bottom:0;left:0;transform:scaleY(-1)}
.crop i:nth-child(4){bottom:0;right:0;transform:scale(-1)}
@media (max-width:600px){.crop{inset:8px}}

/* 一次编排的入场:hero 元素错峰上浮渐显(prefers-reduced-motion 下自动关闭) */
@keyframes rise{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
.hero h1{animation:rise .62s .04s both cubic-bezier(.2,.7,.2,1)}
.hero .lead{animation:rise .62s .14s both cubic-bezier(.2,.7,.2,1)}
.hero .cta-row{animation:rise .62s .24s both cubic-bezier(.2,.7,.2,1)}
.hero .trial{animation:rise .62s .32s both cubic-bezier(.2,.7,.2,1)}
.hero .board{animation:rise .7s .42s both cubic-bezier(.2,.7,.2,1)}
.hero .ticker{animation:rise .7s .54s both cubic-bezier(.2,.7,.2,1)}
h1,h2,h3{margin:0;font-family:var(--sign);font-weight:900;line-height:1.06;letter-spacing:-.01em;text-wrap:balance}
.wrap{max-width:var(--maxw);margin:0 auto;padding-inline:24px}
.mono{font-family:var(--mono);font-variant-numeric:tabular-nums}
:focus-visible{outline:2px solid var(--amber);outline-offset:2px}
button{font:inherit}

/* buttons — sharp, flat */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;font-family:var(--sign);font-weight:800;padding:12px 22px;font-size:14px;border:1px solid var(--amber);cursor:pointer;letter-spacing:.02em;transition:background .15s,color .15s}
.btn-solid{background:var(--amber);color:#FBF6EC;border-color:var(--amber)}
.btn-solid:hover{background:var(--amber-hi)}
.btn-line{background:transparent;color:var(--ink);border-color:var(--ink)}
.btn-line:hover{border-color:var(--amber);color:var(--amber);background:rgba(216,68,42,.06)}
.btn-lg{padding:15px 30px;font-size:15px}

/* 批2:统一微交互 —— 可交互面的 hover/active 平滑过渡(不再硬切),细节更精致 */
.gate,.brow.row,.dur,.fcol a,.nav .links a,.hero .cta-row .btn,.qa summary .q{transition:background-color .16s ease,color .16s ease}
.qa .pm{transition:transform .22s ease}
.qa summary:hover .q{color:var(--amber)}

/* header */
header{position:sticky;top:0;z-index:50;background:var(--bg);border-bottom:1px solid var(--rule)}
.nav{display:flex;align-items:center;gap:26px;height:60px}
.brand{font-family:var(--sign);font-weight:900;font-size:19px;letter-spacing:.03em;display:flex;align-items:center;gap:8px}
.brand .n{color:var(--amber)}
.brand .sq{width:10px;height:10px;background:var(--amber)}
.brand img{width:24px;height:24px;object-fit:cover;border-radius:3px;display:block}
.nav .links{display:flex;gap:22px;margin-left:8px}
.nav .links a{font-family:var(--mono);font-size:12.5px;letter-spacing:.05em;color:var(--dim)}
.nav .links a:hover{color:var(--amber)}
.nav .right{margin-left:auto;display:flex;align-items:center;gap:16px}
.clock{font-family:var(--mono);font-size:12px;color:var(--amber-dim);display:flex;align-items:center;gap:7px}
.clock .live{width:6px;height:6px;background:var(--ok);animation:blink 1.6s steps(1) infinite}
@keyframes blink{50%{opacity:.2}}
.nav .btn{padding:8px 16px;font-size:13px}

/* hero */
.hero{padding:56px 0 34px}
.hero h1{font-size:clamp(40px,7vw,80px);letter-spacing:-.015em;text-transform:none}
.hero h1 em{font-style:normal;color:var(--amber)}
.hero .lead{color:var(--dim);font-size:clamp(16px,2.1vw,19px);margin:22px 0 0;max-width:42ch}
.hero .cta-row{display:flex;flex-wrap:wrap;gap:0;margin-top:30px;border:1px solid var(--amber-dim);width:fit-content}
.hero .cta-row .btn{border:0}
.hero .cta-row .btn-line{border-left:1px solid var(--amber-dim)}
.trial{margin-top:18px;font-family:var(--mono);font-size:13px;color:var(--dim);letter-spacing:.01em}
.trial b{color:var(--amber);font-weight:700}

/* board — the flight information display */
.board{margin:40px 0 0;border:1px solid var(--rule)}
.board-bar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:12px 18px;border-bottom:1px solid var(--rule);background:var(--board)}
.board-bar .t{font-family:var(--sign);font-weight:800;font-size:14px;letter-spacing:.06em}
.board-bar .t span{color:var(--amber-dim);font-family:var(--mono);font-weight:400;font-size:11px;letter-spacing:.16em;margin-left:10px}
.board-bar .m{font-family:var(--mono);font-size:11.5px;color:var(--faint);letter-spacing:.1em}
.brow{display:grid;grid-template-columns:.7fr 1.5fr .8fr 1fr;gap:12px;align-items:center;padding:10px 18px}
.bhead{border-bottom:1px solid var(--rule);background:var(--board)}
.bhead span{font-family:var(--mono);font-size:10px;letter-spacing:.2em;color:var(--faint);text-transform:uppercase}
.brow.row{border-bottom:1px solid var(--seam)}
.brow.row:last-child{border-bottom:0}
.brow.row:hover{background:var(--flap)}
.fno{font-family:var(--mono);font-size:12.5px;color:var(--amber-dim);letter-spacing:.05em}
.dest{font-family:var(--mono);font-weight:700;font-size:15px;letter-spacing:.02em;color:var(--ink);display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.dest .cat{font-size:9.5px;color:var(--faint);letter-spacing:.12em;border:1px solid var(--rule);padding:2px 6px}
.via{font-family:var(--mono);font-size:13px;color:var(--dim);letter-spacing:.06em}
.status{justify-self:start;display:flex;align-items:center;gap:8px}
.status .dot{width:6px;height:6px;background:var(--faint)}
.status.on .dot{background:var(--ok)}
.status .flap{font-family:var(--mono);font-weight:700;font-size:12.5px;letter-spacing:.1em;color:var(--amber);background:var(--flap);padding:4px 9px;position:relative;transform-origin:center;display:inline-block}
.status .flap::after{content:"";position:absolute;left:0;right:0;top:50%;height:1px;background:var(--seam)}
.status.on .flap{color:var(--amber-hi)}
@keyframes flip{0%{transform:rotateX(0)}34%{transform:rotateX(-88deg);opacity:.4}35%{transform:rotateX(88deg)}100%{transform:rotateX(0);opacity:1}}
.status.flip .flap{animation:flip .5s ease}
.board-foot{padding:10px 18px;border-top:1px solid var(--rule);font-family:var(--mono);font-size:11px;color:var(--faint);letter-spacing:.05em;display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px}

/* stat ticker — one ruled line, not tiles */
.ticker{display:flex;flex-wrap:wrap;border:1px solid var(--rule);border-top:0;font-family:var(--mono)}
.ticker .it{flex:1;min-width:130px;padding:16px 18px;border-right:1px solid var(--rule-soft);display:flex;align-items:baseline;gap:9px}
.ticker .it:last-child{border-right:0}
.ticker .v{font-weight:700;font-size:22px;color:var(--amber);letter-spacing:-.01em}
.ticker .k{font-size:12px;color:var(--dim);letter-spacing:.04em}

/* sections */
section{padding:72px 0;border-top:1px solid var(--rule)}
.shead{display:flex;align-items:baseline;justify-content:space-between;gap:16px;padding-bottom:15px;border-bottom:2px solid var(--ink);margin-bottom:28px}
.shead h2{font-size:clamp(24px,3.6vw,36px)}
.shead .m{font-family:var(--mono);font-size:12px;color:var(--faint);letter-spacing:.08em;white-space:nowrap}
.lede{color:var(--dim);font-size:16px;max-width:56ch;margin:0 0 30px}

/* route */
.legs{display:grid;grid-template-columns:1fr auto 1fr auto 1fr;align-items:center;gap:6px;padding:14px 0 26px;border-bottom:1px solid var(--rule-soft)}
.stop{text-align:center}
.stop .ap{font-family:var(--mono);font-weight:700;font-size:clamp(22px,4vw,38px);color:var(--ink);letter-spacing:.04em}
.stop.mid .ap{color:var(--amber)}
.stop .pl{font-family:var(--sign);font-size:13px;color:var(--dim);margin-top:4px;font-weight:700}
.stop .sb{font-family:var(--mono);font-size:10px;color:var(--faint);letter-spacing:.12em;margin-top:2px}
.leg{position:relative;text-align:center;padding:0 6px}
.leg .ln{height:1px;background:repeating-linear-gradient(90deg,var(--amber-dim) 0 6px,transparent 6px 12px)}
.leg.hot .ln{background:var(--amber)}
.leg .lb{font-family:var(--mono);font-size:10px;letter-spacing:.1em;color:var(--faint);margin-bottom:6px;white-space:nowrap}
.leg .p{position:absolute;top:-7px;right:-2px;color:var(--amber);font-size:12px}
.rrow{display:grid;grid-template-columns:180px 1fr;gap:20px;padding:20px 0;border-bottom:1px solid var(--rule-soft);align-items:baseline}
.rrow:last-child{border-bottom:0}
.rrow .rk{font-family:var(--mono);font-size:12px;letter-spacing:.14em;color:var(--amber-dim);text-transform:uppercase}
.rrow h3{font-size:18px;font-family:var(--sign);margin-bottom:7px}
.rrow p{color:var(--dim);font-size:14.5px;margin:0;line-height:1.65}

/* three steps — ruled, sharp, no cards */
.steps{display:grid;grid-template-columns:repeat(3,1fr);border:1px solid var(--rule)}
.step{padding:26px 24px;border-right:1px solid var(--rule-soft)}
.step:last-child{border-right:0}
.step .n{font-family:var(--mono);font-weight:700;font-size:15px;color:var(--amber);letter-spacing:.1em}
.step h3{font-size:19px;margin:12px 0 8px;font-family:var(--sign)}
.step p{color:var(--dim);font-size:14.5px;margin:0;line-height:1.6}

/* platforms — ruled row */
.gates{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid var(--rule)}
.gate{padding:24px 20px;border-right:1px solid var(--rule-soft);display:block}
.gate:last-child{border-right:0}
.gate:hover{background:var(--flap)}
.gate svg{width:26px;height:26px;display:block;margin-bottom:14px;color:var(--dim);transition:color .16s ease}
.gate:hover svg{color:var(--amber)}
.gate .g{font-family:var(--mono);font-size:10.5px;letter-spacing:.14em;color:var(--amber-dim)}
.gate b{display:block;font-family:var(--sign);font-size:18px;margin:6px 0 2px}
.gate span{font-family:var(--mono);font-size:11.5px;color:var(--faint)}

/* fares — a timetable, not pricing cards */
.fares-scroll{overflow-x:auto}
.fares{border:1px solid var(--rule);min-width:640px}
.frow{display:grid;grid-template-columns:1.6fr 1fr 1fr auto;gap:16px;align-items:center;padding:20px 22px;border-bottom:1px solid var(--rule-soft)}
.frow:last-child{border-bottom:0}
.fhead{background:var(--board);border-bottom:1px solid var(--rule)}
.fhead span{font-family:var(--mono);font-size:10px;letter-spacing:.18em;color:var(--faint);text-transform:uppercase}
.frow.feat{background:rgba(216,68,42,.06);box-shadow:inset 3px 0 0 var(--amber)}
.fclass b{font-family:var(--sign);font-weight:800;font-size:18px}
.fclass span{font-family:var(--mono);font-size:10px;color:var(--amber-dim);letter-spacing:.14em;display:block;margin-top:2px}
.fq,.fd{font-family:var(--mono);font-size:14px;color:var(--dim)}
.fclass .dev{display:block;font-family:var(--mono);font-size:11px;color:var(--faint);margin-top:3px;letter-spacing:.04em}
.fp{font-family:var(--mono);font-weight:700;font-size:20px;color:var(--ink);letter-spacing:-.01em}
.fp small{font-size:12px;color:var(--faint);font-weight:400}
.fp2{font-family:var(--mono);font-size:14px;color:var(--dim);line-height:1.35}
.fp2 .save{display:block;font-size:10px;color:var(--amber);letter-spacing:.03em;margin-top:2px}
/* 通航地区 */
.regions{border:1px solid var(--rule)}
.rg{display:grid;grid-template-columns:104px 1fr;border-bottom:1px solid var(--rule-soft)}
.rg:last-child{border-bottom:0}
.rg .cont{font-family:var(--sign);font-weight:800;font-size:14px;padding:16px 18px;border-right:1px solid var(--rule-soft);background:var(--board);display:flex;align-items:center}
.rg .list{display:flex;flex-wrap:wrap}
.rc{font-family:var(--mono);font-size:13px;color:var(--dim);padding:13px 16px;border-right:1px solid var(--rule-soft);letter-spacing:.03em}
.rc b{color:var(--amber);font-weight:700;margin-right:8px}
.frow .btn{padding:9px 18px;font-size:13px;white-space:nowrap}
.fnote{font-family:var(--mono);font-size:12.5px;color:var(--faint);letter-spacing:.04em;padding:14px 22px;border-top:1px solid var(--rule);text-align:center}
/* 套餐卡(商店同结构:时长切换 + 权益清单),纸白时刻表皮 */
.plans{display:grid;grid-template-columns:repeat(auto-fit,minmax(238px,1fr));border:1px solid var(--rule)}
.plan{padding:26px 24px;border-right:1px solid var(--rule-soft);display:flex;flex-direction:column}
.plan:last-child{border-right:0}
.plan .pname{font-family:var(--sign);font-weight:800;font-size:18px;letter-spacing:-.01em}
.plan .pprice{font-family:var(--mono);font-weight:800;font-size:34px;color:var(--ink);letter-spacing:-.02em;margin:14px 0 2px;line-height:1}
.plan .pprice small{font-size:14px;color:var(--faint);font-weight:400}
.plan .pdays{font-family:var(--mono);font-size:12px;color:var(--faint);letter-spacing:.04em}
.durs{display:flex;border:1px solid var(--rule);margin:16px 0 4px}
.dur{flex:1;padding:7px 4px;background:transparent;border:0;border-right:1px solid var(--rule-soft);font-family:var(--mono);font-size:12px;color:var(--dim);cursor:pointer;letter-spacing:.02em}
.dur:last-child{border-right:0}
.dur.active{background:var(--amber);color:#FBF6EC;font-weight:700}
.pfeat{list-style:none;padding:0;margin:16px 0 22px;display:flex;flex-direction:column;gap:9px}
.pfeat li{display:flex;gap:8px;font-size:13.5px;color:var(--dim);line-height:1.5}
.pfeat li svg{width:14px;height:14px;flex:0 0 14px;stroke:var(--amber);margin-top:3px;fill:none;stroke-width:2.4}
.plan .btn{margin-top:auto;width:100%}
@media (max-width:640px){.plans{grid-template-columns:1fr}.plan{border-right:0;border-bottom:1px solid var(--rule-soft)}}

/* trust — ruled row */
.trust{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid var(--rule)}
.ti{padding:22px 20px;border-right:1px solid var(--rule-soft)}
.ti:last-child{border-right:0}
.ti .k{font-family:var(--sign);font-weight:800;font-size:15px}
.ti .d{font-family:var(--mono);font-size:11.5px;color:var(--faint);letter-spacing:.03em;margin-top:6px;display:block}

/* faq — ruled */
.qa{border-bottom:1px solid var(--rule-soft)}
.qa summary{list-style:none;cursor:pointer;padding:19px 0;display:flex;gap:16px;align-items:baseline}
.qa summary::-webkit-details-marker{display:none}
.qa .no{font-family:var(--mono);font-size:12px;color:var(--amber-dim);flex:0 0 auto;letter-spacing:.08em}
.qa .q{font-family:var(--sign);font-weight:800;font-size:16.5px;flex:1}
.qa .pm{font-family:var(--mono);color:var(--amber);flex:0 0 auto}
.qa[open] .pm{transform:rotate(45deg)}
.qa .ans{color:var(--dim);font-size:15px;line-height:1.7;padding:0 0 20px 42px;margin:0;max-width:68ch}

/* closing */
.call{padding:82px 0;text-align:center}
.call .stamp{display:inline-block;font-family:var(--mono);font-weight:700;letter-spacing:.3em;color:var(--stamp);border:2px solid var(--stamp);padding:8px 16px;transform:rotate(-3deg);font-size:12.5px;margin-bottom:26px}
.call h2{font-size:clamp(30px,5vw,52px)}
.call p{color:var(--dim);margin:16px auto 30px;max-width:44ch}

footer{border-top:2px solid var(--ink);color:var(--dim);margin-top:8px}
.foot-top{display:grid;grid-template-columns:1.7fr 1fr 1.2fr 1fr 1fr;border-bottom:1px solid var(--rule)}
.fcol{padding:32px 22px;border-right:1px solid var(--rule-soft)}
.fcol:last-child{border-right:0}
.fcol .ft{font-family:var(--mono);font-size:10.5px;letter-spacing:.16em;color:var(--faint);text-transform:uppercase;margin-bottom:14px}
.fcol a{display:block;color:var(--dim);font-size:14px;padding:6px 0}
.fcol a:hover{color:var(--amber)}
.fbrand .desc{color:var(--dim);font-size:13.5px;margin:14px 0 16px;line-height:1.65;max-width:34ch}
.pay{display:flex;gap:8px;flex-wrap:wrap}
.pay span{font-family:var(--mono);font-size:11px;color:var(--dim);border:1px solid var(--rule);padding:5px 10px;letter-spacing:.03em}
.foot-bot{display:flex;flex-wrap:wrap;gap:14px 24px;justify-content:space-between;align-items:center;padding:18px 22px}
.foot-bot .cr{font-family:var(--mono);font-size:12px;color:var(--faint);letter-spacing:.05em}
.foot-bot .note{font-size:12.5px;color:var(--faint);max-width:56ch}
@media (max-width:820px){.foot-top{grid-template-columns:1fr 1fr}.fcol{border-right:0;border-bottom:1px solid var(--rule-soft)}.fbrand{grid-column:1/-1}}
@media (max-width:480px){.foot-top{grid-template-columns:1fr}}

@media (max-width:820px){
  .steps,.gates,.trust,.ticker{grid-template-columns:1fr 1fr}
  .step:nth-child(2),.gate:nth-child(2),.ti:nth-child(2){border-right:0}
  .step,.gate,.ti{border-bottom:1px solid var(--rule-soft)}
}
@media (max-width:600px){
  .nav .links,.clock{display:none}
  .wrap{padding-inline:16px}
  .nav{gap:12px}
  .nav .right{gap:10px}
  .nav .btn{padding:8px 12px;font-size:12.5px}
  .brow{grid-template-columns:1.4fr 1fr}.brow .fno,.brow .via{display:none}
  .legs{grid-template-columns:1fr;gap:12px}.leg .ln{width:1px;height:22px;margin:0 auto;background:repeating-linear-gradient(180deg,var(--amber-dim) 0 6px,transparent 6px 12px)}.leg .p{display:none}
  .rrow{grid-template-columns:1fr;gap:6px}
  .ticker,.steps,.gates,.trust{grid-template-columns:1fr}
  .ticker .it,.step,.gate,.ti{border-right:0;border-bottom:1px solid var(--rule-soft)}
  .rg{grid-template-columns:1fr}.rg .cont{border-right:0;border-bottom:1px solid var(--rule-soft)}
}
</style>
@endverbatim
</head>
<body>
<div class="crop" aria-hidden="true"><i></i><i></i><i></i><i></i></div>

<header id="hdr">
  <div class="wrap nav">
    <a class="brand" href="#top"><img src="/og.jpg" alt="91VPN">91<span class="n">VPN</span></a>
    <nav class="links">
      <a href="#board">航班信息</a>
      <a href="#regions">通航地区</a>
      <a href="#gates">值机下载</a>
      <a href="#fares">票价</a>
      <a href="#trust">客服</a>
    </nav>
    <div class="right">
      <span class="clock"><span class="live"></span><span id="clk">--:--:--</span></span>
      <a class="btn btn-line" href="/login">登录</a>
      <a class="btn btn-solid" href="/register">立即值机</a>
    </div>
  </div>
</header>

@php
  /**
   * `[!!]` 旅客须知的 4 问在这里【只写一遍】—— 下面的可见折叠和页尾的
   * FAQPage 结构化数据都从它渲染。若两处各写一份,早晚会对不上,
   * 而 Google 判"结构化数据与可见内容不符"是会失去展示资格的。
   * `[!]` 这 4 问是冲着【潜在客户】写的(能不能解锁、快不快、几台设备、
   * 怎么退款),不是帮助中心那 16 篇故障排查 —— 受众不同,别混。
   */
  // 已开放下载的平台 / 还没开放的平台 —— url 为空即"即将推出"(见 client_downloads 迁移注释)
  $dlReady = collect($downloads)->filter(fn ($d) => filled($d->url))->pluck('platform')->all();
  $dlSoon = collect($downloads)->filter(fn ($d) => blank($d->url))->pluck('platform')->all();
  $devicesAnswer = trim(
      ($dlReady ? '现提供 '.implode('、', $dlReady).' 客户端。' : '')
      .($dlSoon ? implode('、', $dlSoon).' 客户端即将开放下载。' : '')
      .'一个账号全平台通用;同时在线设备数因套餐而异,详见各套餐说明。'
  );

  $faqs = [
    ['能解锁 Netflix 和 ChatGPT 吗?',
     '能。多地区节点针对主流流媒体与 AI 服务做了解锁优化——ChatGPT / Claude / Gemini 注册订阅、Netflix 各区片库都可直达。个别服务风控严格时,切到对应地区的原生 IP 节点即可。'],
    ['速度和稳定性怎么样?晚高峰会误点吗?',
     '采用香港就近入口 + 多地区高速落地的中转航线:过境那一跳最短,其余走海外骨干。客户端持续测速自动选最快节点,某个节点异常会自动改签,晚高峰体验更稳。'],
    ['支持哪些设备?一张票能用几台?',
     /* `[!!]` 平台清单【不写死】—— 按 HomeController 自己定的规矩(价格/地区/下载
        全部读真实数据)从 $downloads 生成。写死过一次:页面四个平台都显示
        "即将推出",而这句话说"提供 Android、Windows、iOS、macOS 客户端"。 */
     $devicesAnswer],
    ['怎么付款?可以退票吗?',
     '付款后订阅即时开通,支持多种在线支付方式。新用户 3 天内不满意可无理由退票。'],
  ];

  // 平台图标(内联单色 SVG,契合纸墨单色调性;iOS/macOS 同用苹果标)。按 platform 小写取。
  $appleSvg = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.06 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>';
  // macOS 用笔记本轮廓,和 iOS 的苹果标区分开
  $macSvg = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 5h14a1 1 0 011 1v8H4V6a1 1 0 011-1zm1 2v6h12V7H6z"/><path d="M2 16h20l-1.3 2.2a1 1 0 01-.86.5H4.16a1 1 0 01-.86-.5L2 16z"/></svg>';
  $platSvg = [
    'android' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.52 15.34a1 1 0 110-2 1 1 0 010 2zm-11.04 0a1 1 0 110-2 1 1 0 010 2zM17.88 9.32l2-3.46a.42.42 0 00-.72-.42l-2.02 3.5A12.2 12.2 0 0012 7.85c-1.85 0-3.59.39-5.14 1.09L4.84 5.44a.42.42 0 00-.72.42l2 3.46C2.69 11.19.34 14.66 0 18.76h24c-.34-4.1-2.69-7.57-6.12-9.44z"/></svg>',
    'windows' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M0 3.45L9.75 2.1v9.45H0zM10.95 1.95L24 0v11.4H10.95zM0 12.6h9.75v9.45L0 20.7zM10.95 12.6H24V24l-13.05-1.8z"/></svg>',
    'ios' => $appleSvg,
    'macos' => $macSvg,
  ];

  // 发车信息板目的地(服务端渲染 → 首屏/无 JS/爬虫都能看到;JS 只做翻牌入场增强)
  $dest = [
    ['NETFLIX','HKG','流媒体'],['DISNEY+','JPN','流媒体'],['HBO MAX','USA','流媒体'],
    ['PRIME VIDEO','JPN','流媒体'],['YOUTUBE','HKG','流媒体'],['HULU','USA','流媒体'],
    ['CHATGPT','USA','AI'],['CLAUDE','USA','AI'],['GEMINI','USA','AI'],
    ['SPOTIFY','SGP','音乐'],['INSTAGRAM','HKG','社交'],['TIKTOK','JPN','社交'],
  ];
@endphp

<main id="top">
<div class="wrap">
  <section class="hero" style="border-top:0;padding-top:56px">
    <h1>直飞你到不了的<br><em>全球互联网</em></h1>
    <p class="lead">Netflix、YouTube、ChatGPT——像机场一样,从香港就近值机,多地区高速直达。晚高峰也不误点。</p>
    <div class="cta-row">
      <a class="btn btn-solid btn-lg" id="dl-cta" href="#gates">立即下载</a>
      <a class="btn btn-line btn-lg" href="#fares">查看票价</a>
    </div>
    <div class="trial">新用户注册即领 <b>1 GB 试用流量</b> · 无需付款、无需实名,连上再决定</div>

    <div class="board" id="board" aria-label="通航目的地信息屏">
      <div class="board-bar">
        <span class="t">航班信息 <span>DEPARTURES</span></span>
        <span class="m" id="board-clk">TERMINAL 91 · --:--</span>
      </div>
      <div class="brow bhead"><span>航班 FLT</span><span>目的地 DEST</span><span>经由 VIA</span><span>状态 STATUS</span></div>
      <div id="rows">
        @foreach($dest as $i => $d)
        <div class="brow row">
          <div class="fno">9V{{ 201 + $i }}</div>
          <div class="dest">{{ $d[0] }} <span class="cat">{{ $d[2] }}</span></div>
          <div class="via">VIA {{ $d[1] }}</div>
          <div class="status on"><span class="dot"></span><span class="flap">已通航</span></div>
        </div>
        @endforeach
      </div>
      <div class="board-foot"><span>* 目的地按套餐与地区就近直达 · 部分服务需切换对应地区落地</span><span id="cnt">通航中</span></div>
    </div>

    <div class="ticker">
      <div class="it"><span class="v">{{ $regionCount }}</span><span class="k">覆盖地区</span></div>
      <div class="it"><span class="v">{{ $nodeCount }}</span><span class="k">高速节点</span></div>
      <div class="it"><span class="v">99.9%</span><span class="k">在线率</span></div>
      <div class="it"><span class="v">30+</span><span class="k">解锁服务</span></div>
    </div>
  </section>
</div>

<section id="route">
  <div class="wrap">
    <div class="shead"><h2>为什么快而稳</h2><span class="m">FAST & STABLE</span></div>
    <p class="lede">慢和卡,几乎都堵在"国内直连海外"那一跳。我们像航空中转一样把它拆开:过境段最短,其余全走干净的海外航线。</p>
    <div class="legs">
      <div class="stop"><div class="ap">CHN</div><div class="pl">中国</div><div class="sb">出发 DEP</div></div>
      <div class="leg"><div class="lb">过境·最短</div><div class="ln"></div><span class="p">✈</span></div>
      <div class="stop mid"><div class="ap">HKG</div><div class="pl">香港中转</div><div class="sb">就近值机</div></div>
      <div class="leg hot"><div class="lb">海外高速航线</div><div class="ln"></div><span class="p">✈</span></div>
      <div class="stop"><div class="ap">WLD</div><div class="pl">全球</div><div class="sb">到达 ARR</div></div>
    </div>
    <div class="rrow"><span class="rk">Nearest Gate</span><div><h3>香港就近入口</h3><p>先连最近的香港入口,过境那一跳距离最短、延迟最低——这一步决定了大半速度。</p></div></div>
    <div class="rrow"><span class="rk">Multi-Region</span><div><h3>多地区到达</h3><p>日本、新加坡、美国、台湾等优质落地,入口到落地走干净海外骨干,不跟拥堵的直连线抢路。</p></div></div>
    <div class="rrow"><span class="rk">Auto Reroute</span><div><h3>自动改签选路</h3><p>客户端持续测速,永远连当下最快节点;某个节点误点,自动切走,你无感。</p></div></div>
  </div>
</section>

<section id="start">
  <div class="wrap">
    <div class="shead"><h2>三步,几分钟直达</h2><span class="m">GET STARTED</span></div>
    <div class="steps">
      {{-- `[!!]` 顺序是【先下载、后注册】,不能反。网站不受理注册
           (RegisterController::store 直接挡回提示页),账号只能在客户端里开。
           原文把注册写成 STEP 01 并说"邮箱注册",两处都不对:注册没有邮箱字段
           (AuthApiController::register 只收 username/password),而按原顺序走的人
           会到 /register 看见"请回首页下载客户端",转一圈回到原点。 --}}
      <div class="step"><div class="n">STEP 01</div><h3>下载客户端</h3><p>选你的设备下载 App,一个账号手机、电脑通用。</p></div>
      <div class="step"><div class="n">STEP 02</div><h3>注册账号</h3><p>在客户端里注册,只需账户名和密码,无需邮箱、无需实名。</p></div>
      <div class="step"><div class="n">STEP 03</div><h3>一键起飞</h3><p>打开点一下,自动选最快节点,直达全球互联网。</p></div>
    </div>
  </div>
</section>

<section id="gates">
  <div class="wrap">
    <div class="shead"><h2>值机 · 下载客户端</h2><span class="m">CHECK-IN</span></div>
    <div class="gates">
      @forelse($downloads as $dl)
      <a class="gate" href="{{ $dl->url ?: '/register' }}"@if($dl->url) target="_blank" rel="noopener"@endif>
        {!! $platSvg[strtolower($dl->platform)] ?? '' !!}
        <span class="g">{{ $dl->platform }}</span>
        <b>{{ $dl->label ?: $dl->platform }}</b>
        <span>{{ $dl->url ? ($dl->version ?: '点击下载') : '即将推出' }}</span>
      </a>
      @empty
      <a class="gate" href="/register"><span class="g">全平台</span><b>Android · Windows</b><span>iOS · macOS · 注册后下载</span></a>
      @endforelse
    </div>
    <p style="font-family:var(--mono);font-size:13px;color:var(--faint);margin-top:16px;letter-spacing:.04em">不会用?<a href="/help" style="color:var(--amber)">查看各平台安装教程 →</a></p>
  </div>
</section>

<section id="regions">
  <div class="wrap">
    <div class="shead"><h2>通航地区</h2><span class="m">DESTINATIONS · {{ $regionCount }}</span></div>
    <div class="regions">
      <div class="rg"><div class="cont">覆盖地区</div><div class="list">
        @foreach($regions as $r)<span class="rc">{{ $r }}</span>@endforeach
      </div></div>
    </div>
    <p style="font-family:var(--mono);font-size:12px;color:var(--faint);margin-top:14px;letter-spacing:.04em">* 地区持续增加 · 具体可用节点以客户端为准</p>
  </div>
</section>

<section id="fares">
  <div class="wrap">
    <div class="shead"><h2>舱位与票价</h2><span class="m">FARES</span></div>
    @php $check = '<svg viewBox="0 0 24 24"><path d="M5 12l4 4 10-10"/></svg>'; @endphp
    <div class="plans">
      @forelse($groups as $g)
        @php $b = $g['benefits']; $first = $g['durations']->first(); @endphp
        <div class="plan" data-plan-card>
          <div class="pname">{{ $b->name }}</div>
          <div class="pprice">¥<span data-price-out>{{ $first['price'] }}</span></div>
          <div class="pdays">有效期 <span data-days-out>{{ $first['days'] }}</span> 天</div>
          @if($g['durations']->count() > 1)
          <div class="durs">
            @foreach($g['durations'] as $d)
            <button type="button" class="dur {{ $loop->first ? 'active' : '' }}"
              data-price="{{ $d['price'] }}" data-days="{{ $d['days'] }}"
              data-traffic="{{ $catalog->trafficText($d) }}">{{ $d['label'] }}</button>
            @endforeach
          </div>
          @endif
          <ul class="pfeat">
            <li>{!! $check !!}<span data-traffic-out>{{ $catalog->trafficText($first) }}</span></li>
            <li>{!! $check !!}多地区高速中转，自动选最快节点</li>
            <li>{!! $check !!}稳定解锁 Netflix / YouTube / ChatGPT</li>
            <li>{!! $check !!}同时在线设备 {{ $b->ip_limit > 0 ? $b->ip_limit.' 台' : '不限' }}</li>
            <li>{!! $check !!}{{ $b->speed_limit > 0 ? '端口限速 '.$b->speed_limit.' Mbps' : '端口不限速' }}</li>
          </ul>
          <a class="btn btn-solid" href="/register">注册开通</a>
        </div>
      @empty
        <div class="plan">
          <div class="pname">套餐即将上线</div>
          <div class="pdays" style="margin-top:12px">先注册免费试用，开放后第一时间通知你。</div>
          <a class="btn btn-solid" href="/register" style="margin-top:20px">免费试用</a>
        </div>
      @endforelse
    </div>
    <p style="font-family:var(--mono);font-size:12px;color:var(--faint);margin-top:14px;letter-spacing:.04em">* 全部套餐含全部地区节点 · 流媒体 + AI 解锁 · 3 天无理由退票 · 注册后在用户中心下单</p>
  </div>
</section>

<section id="notice">
  <div class="wrap">
    <div class="shead"><h2>旅客须知</h2><span class="m">PASSENGER INFO</span></div>
    @foreach ($faqs as $i => [$q, $ans])
    <details class="qa" @if($i === 0) open @endif><summary><span class="no">Q{{ $i + 1 }}</span><span class="q">{{ $q }}</span><span class="pm">+</span></summary><p class="ans">{{ $ans }}</p></details>
    @endforeach
    <p style="font-family:var(--mono);font-size:13px;color:var(--faint);margin-top:20px;letter-spacing:.04em">更多问题?<a href="/help" style="color:var(--amber)">查看帮助中心 →</a></p>
  </div>
</section>

<section id="trust">
  <div class="wrap">
    <div class="shead"><h2>为什么放心</h2><span class="m">WHY TRUST US</span></div>
    <div class="trust">
      <div class="ti"><span class="k">3 天无理由退票</span><span class="d">不满意全额退</span></div>
      <div class="ti"><span class="k">7×24 在线客服</span><span class="d">工单 / TG 群随时找得到人</span></div>
      <div class="ti"><span class="k">在线支付</span><span class="d">付款后订阅即时开通</span></div>
      <div class="ti"><span class="k">隐私优先</span><span class="d">不记录你的上网内容</span></div>
    </div>
  </div>
</section>

<section class="call">
  <div class="wrap">
    <span class="stamp">NOW BOARDING</span>
    <h2>现在登机</h2>
    <p>注册即可免费试用,几分钟直达全球互联网。</p>
    <a class="btn btn-solid btn-lg" href="/register">立即值机 · 免费试用 →</a>
  </div>
</section>
</main>

<footer>
  <div class="wrap">
    <div class="foot-top">
      <div class="fcol fbrand">
        <a class="brand" href="#top"><img src="/og.jpg" alt="91VPN">91<span class="n">VPN</span></a>
        <p class="desc">解锁全球流媒体与 AI 的加速服务。香港就近入口,多地区高速直达。</p>
      </div>
      <div class="fcol">
        <div class="ft">产品</div>
        <a href="#board">航班信息</a>
        <a href="#regions">通航地区</a>
        <a href="#gates">值机下载</a>
        <a href="#fares">舱位票价</a>
      </div>
      <div class="fcol">
        <div class="ft">帮助与支持</div>
        <a href="/help">帮助中心</a>
        <a href="#notice">常见问题</a>
        <a href="/user/ticket">在线客服 / 工单</a>
        <a href="{{ setting('support_group') ?: (setting('support_tg') ?: 'https://t.me/') }}" rel="noopener">Telegram 社群</a>
        <a href="mailto:support@91vpn.com">support@91vpn.com</a>
      </div>
      <div class="fcol">
        <div class="ft">账户</div>
        <a href="/register">注册</a>
        <a href="/login">登录</a>
        <a href="/user">用户中心</a>
      </div>
      <div class="fcol">
        <div class="ft">条款</div>
        <a href="/terms">服务条款</a>
        <a href="/privacy">隐私政策</a>
        <a href="/refund">退款政策</a>
      </div>
    </div>
    <div class="foot-bot">
      <span class="cr">91VPN · 国际航站楼 © 2026</span>
      <span class="note">本站仅提供网络加速服务,请遵守所在地法律法规。</span>
    </div>
  </div>
</footer>

<script>
// 发车板行已由服务端渲染(默认 .status.on / 已通航)。JS 只做翻牌入场增强:
// 先复位成"候机中"再错峰翻成"已通航"。prefers-reduced-motion 下保持 SSR 的已通航态,不动。
var sts=[].slice.call(document.querySelectorAll('#rows .status'));
var reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if(!reduce){
  sts.forEach(function(s){ s.classList.remove('on'); s.querySelector('.flap').textContent='候机中'; });
  sts.forEach(function(s,i){ setTimeout(function(){
    s.classList.add('flip');
    setTimeout(function(){ s.querySelector('.flap').textContent='已通航'; s.classList.add('on'); },170);
  }, 240+i*95); });
}
function tick(){var d=new Date(),p=function(n){return(n<10?'0':'')+n;};
  var hh=p(d.getHours()),mm=p(d.getMinutes()),ss=p(d.getSeconds());
  var c=document.getElementById('clk');if(c)c.textContent=hh+':'+mm+':'+ss;
  var b=document.getElementById('board-clk');if(b)b.textContent='TERMINAL 91 · '+hh+':'+mm;}
tick();setInterval(tick,1000);

// 套餐时长切换:点 1/3/6/12月,更新本卡的价格/有效期/流量(与商店一致)
document.querySelectorAll('[data-plan-card]').forEach(function(card){
  var price=card.querySelector('[data-price-out]'),days=card.querySelector('[data-days-out]'),traf=card.querySelector('[data-traffic-out]');
  card.querySelectorAll('.dur').forEach(function(btn){
    btn.addEventListener('click',function(){
      card.querySelectorAll('.dur').forEach(function(b){b.classList.remove('active');});
      btn.classList.add('active');
      if(price)price.textContent=btn.dataset.price;
      if(days)days.textContent=btn.dataset.days;
      if(traf)traf.textContent=btn.dataset.traffic;
    });
  });
});
var qas=[].slice.call(document.querySelectorAll('.qa'));
qas.forEach(function(q){q.querySelector('summary').addEventListener('click',function(e){
  e.preventDefault();var open=q.open;qas.forEach(function(o){o.open=false;});q.open=!open;});});

/* UA 自适应下载:按 navigator 匹配当前平台。有下载链接→直接下载;暂无→回退到下载区 #gates。
   客户端判断(非服务端 UA),HTML 对所有访客一致,规避 CDN 缓存串味。 */
(function(){
  var dl=@json($downloads->mapWithKeys(fn($d)=>[strtolower($d->platform)=>['url'=>$d->url,'label'=>$d->label ?: $d->platform]]));
  var ua=navigator.userAgent||'', uap=(navigator.userAgentData&&navigator.userAgentData.platform)||'', p='';
  if(/android/i.test(ua)) p='android';
  else if(/iphone|ipad|ipod/i.test(ua)) p='ios';
  else if(/windows/i.test(ua)||/win/i.test(uap)) p='windows';
  else if(/macintosh|mac os x/i.test(ua)||/mac/i.test(uap)) p='macos';
  var names={android:'Android',ios:'iOS',windows:'Windows',macos:'macOS'};
  var btn=document.getElementById('dl-cta');
  if(!btn||!p) return;                              // 认不出平台 → 保持"立即下载"→#gates
  var d=dl[p];
  if(d&&d.url){ btn.href=d.url; btn.textContent='立即下载 · '+names[p]; btn.target='_blank'; btn.rel='noopener'; }
  else { btn.textContent='查看'+names[p]+'下载'; } // 该平台暂无链接 → 文案提示,href 仍指 #gates
})();
</script>
{{--
  `[!!]` 结构化数据。三条纪律:
   1. 全部从【已经渲染在页面上的真实数据】生成 —— $faqs 与可见折叠同源,
      价格来自 PlanCatalog,与「舱位与票价」板块是同一批数字。写死会漂移,
      而 Google 判"结构化数据与可见内容不符"是会失去展示资格的。
   2. `[I]` FAQPage 的富摘要资格自 2023 年起被 Google 收窄到政府与医疗类
      权威站点,本站【很可能拿不到那个折叠问答位】。留着的理由是它仍然帮
      搜索引擎与 AI 检索理解页面结构 —— 但别指望它变成搜索结果里的样式。
   3. `[!]` 故意【没有】SoftwareApplication:那组是给可下载的软件用的,
      而当前四个平台的 client_downloads.url 全为空、页面显示"即将推出"。
      标一个下载不到的 App 是误导。等下载链接填上再加。
--}}
@php
  $ldHome = rtrim(config('app.url'), '/');
  // 价格区间:只取还在售(未售罄)的档位,与页面显示的口径一致
  $ldPrices = collect($groups)
      ->flatMap(fn ($g) => collect($g['durations'] ?? []))
      ->reject(fn ($d) => ($d['sold_out'] ?? false))
      // `[!!]` price 是【带千位分隔符的展示字符串】("1,800")。直接 (float) 转
      //   会得到 1.0 —— 实测踩过:最低价被算成 ¥1、最高价从 1800 掉到 900。
      ->pluck('price')->map(fn ($v) => (float) str_replace(',', '', (string) $v))
      ->filter()->values();
  $ldTg = trim((string) setting('support_tg', ''));

  $ldGraph = [
    [
      '@type' => 'Organization',
      '@id' => $ldHome.'/#org',
      'name' => '91VPN',
      'url' => $ldHome,
      'logo' => $ldHome.'/og.jpg',
      'sameAs' => array_values(array_filter([$ldTg])),
    ],
    [
      '@type' => 'WebSite',
      '@id' => $ldHome.'/#site',
      'name' => '91VPN',
      'url' => $ldHome,
      'inLanguage' => 'zh-CN',
      'publisher' => ['@id' => $ldHome.'/#org'],
    ],
    [
      '@type' => 'FAQPage',
      '@id' => $ldHome.'/#faq',
      'mainEntity' => array_map(fn ($f) => [
        '@type' => 'Question',
        'name' => $f[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
      ], $faqs),
    ],
  ];

  // 没有在售套餐时【不输出】价格 —— 空的 AggregateOffer 比没有更糟
  if ($ldPrices->isNotEmpty()) {
      $ldGraph[] = [
          '@type' => 'Product',
          '@id' => $ldHome.'/#plans',
          'name' => '91VPN 订阅',
          'brand' => ['@id' => $ldHome.'/#org'],
          'offers' => [
              '@type' => 'AggregateOffer',
              'priceCurrency' => 'CNY',
              'lowPrice' => (string) $ldPrices->min(),
              'highPrice' => (string) $ldPrices->max(),
              'offerCount' => $ldPrices->count(),
              'availability' => 'https://schema.org/InStock',
              'url' => $ldHome.'/#fares',
          ],
      ];
  }
@endphp
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $ldGraph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
</body>

</html>
