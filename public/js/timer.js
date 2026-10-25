// Таймер без перезагрузки страницы. Источник правды — сервер (started_at),
// поэтому время не теряется при закрытии вкладки, а все вкладки показывают одно и то же.
(() => {
    const side = document.querySelector('[data-side-timer]');
    if (!side) return;
    const boxes = [...document.querySelectorAll('[data-timer]')];
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const channel = 'BroadcastChannel' in window ? new BroadcastChannel('falcon-timer') : null;
    let state = JSON.parse(side.dataset.state || '{"running":false}');
    let offset = 0; // сервер минус клиент, мс

    const fmt = s => { s = Math.max(0, Math.floor(s)); return `${Math.floor(s / 3600)}:${String(Math.floor(s / 60) % 60).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`; };
    const elapsed = () => state.running ? (Date.now() + offset - Date.parse(state.started_at)) / 1000 : 0;

    function apply(next, broadcast = false) {
        // Остановленный интервал переходит в «уже залогировано», чтобы часы не откатывались назад
        if (state.running && (!next.running || next.entry_id !== state.entry_id)) {
            const box = boxes.find(b => b.dataset.assignment === String(state.assignment_id));
            if (box) box.dataset.logged = Number(box.dataset.logged || 0) + Math.floor(elapsed());
        }
        state = next;
        if (state.server_now) offset = Date.parse(state.server_now) - Date.now();
        side.classList.toggle('d-none', !state.running);
        if (state.running) { side.querySelector('[data-task]').textContent = state.task; side.querySelector('[data-task]').href = state.url || '#'; }
        boxes.forEach(box => {
            const mine = state.running && String(state.assignment_id) === box.dataset.assignment;
            if (mine) box.dataset.logged = state.logged;
            box.classList.toggle('running', mine);
            const btn = box.querySelector('[data-toggle]');
            btn.textContent = mine ? '■ Стоп' : '▶ Старт';
            btn.className = 'btn ' + (mine ? 'btn-stop' : 'btn-falcon');
        });
        tick();
        if (broadcast && channel) channel.postMessage(state);
    }

    function tick() {
        side.querySelector('[data-clock]').textContent = fmt(elapsed());
        boxes.forEach(box => {
            const mine = state.running && String(state.assignment_id) === box.dataset.assignment;
            box.querySelector('[data-clock]').textContent = fmt(Number(box.dataset.logged || 0) + (mine ? elapsed() : 0));
        });
    }

    async function call(url, method = 'POST') {
        const res = await fetch(url, { method, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        const data = await res.json().catch(() => null);
        if (!data) { alert('Не удалось связаться с сервером. Обновите страницу.'); return; }
        if (data.error) alert(data.error);
        apply(data, true);
    }

    const refresh = () => call(side.dataset.currentUrl, 'GET');

    side.querySelector('[data-stop]').onclick = () => call(side.dataset.stopUrl);
    boxes.forEach(box => box.querySelector('[data-toggle]').onclick = e => {
        const btn = e.currentTarget;
        btn.disabled = true;
        const running = state.running && String(state.assignment_id) === box.dataset.assignment;
        call(running ? side.dataset.stopUrl : box.dataset.startUrl).finally(() => btn.disabled = false);
    });
    if (channel) channel.onmessage = e => apply(e.data);
    // Сверяемся с сервером при возврате на вкладку и раз в минуту — на случай действий с другого устройства
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && refresh());
    setInterval(refresh, 60000);
    setInterval(tick, 1000);
    apply(state);
})();
