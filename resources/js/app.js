import './bootstrap';
const rupiah = value => 'Rp ' + Math.round(value).toLocaleString('id-ID');
document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => { if (!window.confirm(form.dataset.confirm)) event.preventDefault(); }));
document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
const checkout = document.querySelector('[data-checkout]');
if (checkout) {
    const update = () => {
        const shipping = checkout.querySelector('[name=fulfillment]:checked')?.value === 'shipping';
        const shippingFields = checkout.querySelector('.shipping-fields');
        shippingFields.hidden = !shipping;
        checkout.querySelector('.pickup-info').hidden = shipping;
        checkout.querySelector('.cash-option').hidden = shipping;
        shippingFields.querySelectorAll('input, select, textarea').forEach(input => { input.disabled = !shipping; input.required = shipping; });
        const cash = checkout.querySelector('[name=payment_method][value=cash]');
        cash.disabled = shipping;
        if (shipping && cash.checked) {
            cash.checked = false;
            const digital = checkout.querySelector('[name=payment_method][value=midtrans]');
            if (!digital.disabled) digital.checked = true;
        }
        const rate = Number(checkout.querySelector('[name=courier] option:checked')?.dataset.rate || 0);
        const multiplier = Number(checkout.querySelector('[name=shipping_zone] option:checked')?.dataset.multiplier || 1);
        const fee = shipping ? Math.ceil(rate * multiplier) * Number(checkout.dataset.weight) : 0;
        checkout.querySelector('[data-shipping]').textContent = rupiah(fee);
        checkout.querySelector('[data-total]').textContent = rupiah(Number(checkout.dataset.subtotal) + fee);
    };
    checkout.addEventListener('change', update); update();
}
// Keep the cashier basket across product searches in this browser tab.
const clearedUser = document.body.dataset.clearPos;
if (clearedUser) sessionStorage.removeItem('maya-pos-' + clearedUser);
const pos = document.querySelector('[data-pos]');
if (pos) {
    const key = 'maya-pos-' + pos.dataset.userId;
    let basket = {};
    try { basket = JSON.parse(sessionStorage.getItem(key) || '{}'); } catch { basket = {}; }
    const visible = new Set();
    pos.querySelectorAll('[data-price]').forEach(input => {
        const id = input.name.match(/\d+/)[0]; visible.add(id);
        if (basket[id]) input.value = basket[id].quantity;
    });
    const update = () => {
        pos.querySelectorAll('[data-price]').forEach(input => {
            const id = input.name.match(/\d+/)[0];
            const qty = Math.max(0, Math.min(999, Number(input.value) || 0));
            if (qty) basket[id] = {quantity: qty, price: Number(input.dataset.price), name: input.closest('label').querySelector('strong').textContent};
            else delete basket[id];
        });
        sessionStorage.setItem(key, JSON.stringify(basket));
        pos.querySelectorAll('[data-retained-item]').forEach(input => input.remove());
        let total = 0;
        const lines = [];
        Object.entries(basket).forEach(([id, item]) => {
            total += item.quantity * item.price;
            lines.push(`${item.name} × ${item.quantity}`);
            if (!visible.has(id)) {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = `items[${id}]`; input.value = item.quantity;
                input.dataset.retainedItem = 'true'; pos.append(input);
            }
        });
        pos.querySelector('[data-pos-lines]').textContent = lines.join(' · ') || 'Belum ada produk dipilih.';
        pos.querySelector('[data-pos-total]').textContent = rupiah(total);
        const cash = pos.querySelector('[data-cash]');
        const method = pos.querySelector('[name=payment_method]').value;
        cash.required = method === 'cash'; cash.disabled = method !== 'cash';
        const reference = pos.querySelector('[name=payment_reference]');
        reference.required = method === 'debit'; reference.disabled = method !== 'debit';
        pos.querySelector('[data-change]').textContent = rupiah(method === 'cash' ? Math.max(0, Number(cash.value) - total) : 0);
    };
    pos.addEventListener('input', update); pos.addEventListener('change', update); update();
    const search = document.querySelector('[data-pos-search]');
    if (search) {
        let timer, request;
        const load = async () => {
            request?.abort(); request = new AbortController();
            try {
                const response = await fetch(search.dataset.posSearch + '?q=' + encodeURIComponent(search.querySelector('[name=q]').value), {signal: request.signal, headers: {'Accept':'application/json'}});
                if (!response.ok) throw new Error('Pencarian belum tersedia. Gunakan tombol Cari untuk memuat halaman.');
                const result = await response.json();
                pos.querySelector('.pos-products').innerHTML = result.html;
                visible.clear();
                pos.querySelectorAll('[data-price]').forEach(input => {
                    const id = input.name.match(/\d+/)[0]; visible.add(id);
                    if (basket[id]) input.value = basket[id].quantity;
                });
                update();
            } catch (error) { if (error.name !== 'AbortError') search.querySelector('input').setCustomValidity(error.message); }
        };
        search.querySelector('input').addEventListener('input', () => {search.querySelector('input').setCustomValidity('');clearTimeout(timer);timer=setTimeout(load,250);});
        // Enter submits a full-page search as a fallback; typing performs instant lookup.
    }

}

// Native dialogs provide focus trapping and Escape support without another dependency.
document.querySelectorAll('[data-open-dialog]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.openDialog).showModal()));
document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
document.querySelectorAll('[data-select-address]').forEach(button => button.addEventListener('click', () => {
    const address = JSON.parse(button.dataset.selectAddress);
    const map = {recipient:'recipient',phone:'phone',address:'address',shipping_zone:'zone',postal_code:'postal_code',address_id:'id'};
    for (const [field,key] of Object.entries(map)) checkout.querySelector(`[name=${field}]`).value=address[key];
    checkout.dispatchEvent(new Event('change'));
    button.closest('dialog').close();
}));
if (checkout) ['recipient','phone','address','shipping_zone','postal_code'].forEach(name => {
    checkout.querySelector(`[name=${name}]`)?.addEventListener('input', () => checkout.querySelector('[name=address_id]').value='');
});
document.querySelectorAll('[data-snap-url]').forEach(button => button.closest('form').addEventListener('submit', async event => {
    event.preventDefault();
    const message = button.closest('form').querySelector('[data-payment-message]');
    if (!window.snap) { message.textContent='Jendela pembayaran belum tersedia. Periksa koneksi lalu coba kembali.';return; }
    button.disabled=true;
    try {
        const response=await fetch(button.dataset.snapUrl,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}});
        const data=await response.json();
        if (!response.ok || !data.token) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : 'Layanan pembayaran belum tersedia.');
        window.snap.pay(data.token,{
            onSuccess:()=>{message.textContent='Pembayaran diterima penyedia. Menunggu verifikasi server…';window.location.reload();},
            onPending:()=>{message.textContent='Pembayaran menunggu penyelesaian. Status akan diperbarui setelah diverifikasi.';button.disabled=false;},
            onError:()=>{message.textContent='Pembayaran belum berhasil. Silakan coba kembali.';button.disabled=false;},
            onClose:()=>{button.disabled=false;}
        });
    } catch(error) { message.textContent=error.message;button.disabled=false; }
}));
