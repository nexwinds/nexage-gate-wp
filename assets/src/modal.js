(function(){
  var cfg = window.nexageGate || {};
  var rootId = 'nexage-gate-root';
  function setCookie(name,value,days){
    var expires = '';
    if(days && days>0){
      var d = new Date();
      d.setTime(d.getTime()+days*24*60*60*1000);
      expires = '; expires='+d.toUTCString();
    }
    document.cookie = name+'='+value+expires+'; path=/; SameSite=Lax';
  }
  function getCookie(name){
    var m = document.cookie.match(new RegExp('(^| )'+name+'=([^;]+)'));
    return m?m[2]:'';
  }
  function approved(){ return getCookie('nexage_gate_access')==='approved'; }
  function denied(){ return getCookie('nexage_gate_access')==='denied'; }
  function text(key){
    var mobile = window.matchMedia('(max-width:600px)').matches;
    if(mobile && key==='headline') return (cfg.texts&&cfg.texts.mobile_headline)||'Age Check';
    if(mobile && key==='description') return (cfg.texts&&cfg.texts.mobile_description)||('You must be '+cfg.minAge+'+');
    return (cfg.texts&&cfg.texts[key])||'';
  }
  function build(){
    var overlay = document.createElement('div');
    overlay.className = 'nexage-gate-overlay';
    var panel = document.createElement('div');
    panel.className = 'nexage-gate-panel';
    var inner = document.createElement('div');
    inner.className = 'inner';
    if(cfg.logo){
      var img = document.createElement('img');
      img.className='nexage-gate-logo';
      img.src=cfg.logo; img.alt='';
      inner.appendChild(img);
    }
    var h = document.createElement('h2'); h.className='nexage-gate-title'; h.textContent = text('headline')||'Age Verification'; inner.appendChild(h);
    var p = document.createElement('p'); p.className='nexage-gate-desc';
    var desc = text('description')||('You must be '+cfg.minAge+'+ to visit this site.');
    desc = desc.replace('{age}', cfg.minAge);
    p.textContent = desc; inner.appendChild(p);
    var actions = document.createElement('div'); actions.className='nexage-gate-actions';
    if(cfg.method==='yesno'){
      var yes = document.createElement('button'); yes.className='nexage-gate-btn'; yes.textContent = text('yes_label')||'Yes';
      var no = document.createElement('button'); no.className='nexage-gate-btn'; no.textContent = text('no_label')||'No';
      yes.addEventListener('click', function(){ approveFlow(); });
      no.addEventListener('click', function(){ denyFlow(); });
      actions.appendChild(yes); actions.appendChild(no);
    } else {
      var dateBox = document.createElement('div'); dateBox.className='nexage-gate-date';
      var d = document.createElement('input'); d.type='number'; d.min='1'; d.max='31'; d.placeholder = text('date_day')||'Day';
      var m = document.createElement('input'); m.type='number'; m.min='1'; m.max='12'; m.placeholder = text('date_month')||'Month';
      var y = document.createElement('input'); y.type='number'; y.min='1900'; y.max=(new Date().getFullYear()); y.placeholder = text('date_year')||'Year';
      dateBox.appendChild(d); dateBox.appendChild(m); dateBox.appendChild(y);
      var confirm = document.createElement('button'); confirm.className='nexage-gate-btn'; confirm.textContent = text('confirm_label')||'Confirm';
      confirm.addEventListener('click', function(){
        var dd=parseInt(d.value,10), mm=parseInt(m.value,10), yy=parseInt(y.value,10);
        if(!dd||!mm||!yy) return;
        var now = new Date();
        var birth = new Date(yy, mm-1, dd);
        var age = now.getFullYear()-birth.getFullYear();
        var mdiff = now.getMonth()-birth.getMonth();
        if(mdiff<0 || (mdiff===0 && now.getDate()<birth.getDate())) age--;
        if(age>=cfg.minAge) approveFlow(); else denyFlow();
      });
      actions.appendChild(dateBox); actions.appendChild(confirm);
    }
    var rememberWrap = document.createElement('label'); rememberWrap.className='nexage-gate-remember';
    var chk = document.createElement('input'); chk.type='checkbox'; chk.checked=false; chk.id='nexage-gate-remember';
    var span = document.createElement('span'); span.textContent = text('remember_label')||'Remember';
    rememberWrap.appendChild(chk); rememberWrap.appendChild(span);
    inner.appendChild(actions); if(cfg.cookieEnabled) inner.appendChild(rememberWrap);
    panel.appendChild(inner); overlay.appendChild(panel);
    var host = document.getElementById(rootId);
    if(!host){ host = document.createElement('div'); host.id=rootId; document.body.appendChild(host); }
    host.innerHTML=''; host.appendChild(overlay);
    requestAnimationFrame(function(){ overlay.classList.add('show'); panel.classList.add('show'); });
    function approveFlow(){
      if(cfg.cookieEnabled){ var days = (document.getElementById('nexage-gate-remember')&&document.getElementById('nexage-gate-remember').checked)?cfg.cookieDays:(1/24); setCookie('nexage_gate_access','approved',days); }
      document.documentElement.classList.remove('nexage-gate-hidden');
      host.parentNode && host.parentNode.removeChild(host);
    }
    function denyFlow(){
      if(cfg.cookieEnabled){ setCookie('nexage_gate_access','denied',cfg.cookieDays); }
      window.location.href = cfg.blockedUrl || '/';
    }
  }
  function init(){
    if(!cfg.needsGate) return;
    if(approved()) { document.documentElement.classList.remove('nexage-gate-hidden'); return; }
    if(denied()) { window.location.href = cfg.blockedUrl || '/'; return; }
    build();
  }
  if(document.readyState==='complete' || document.readyState==='interactive') init(); else document.addEventListener('DOMContentLoaded', init);
})();

