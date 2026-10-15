@extends('layouts.app')

@section('title', 'New Sale')

@section('content')
    <style>
        .cat-pills { display: flex; gap: .5rem; overflow-x: auto; padding-bottom: .25rem; }
        .cat-pill { white-space: nowrap; border: 1px solid #cfd8d3; background: #fff; border-radius: 999px; padding: .3rem .95rem; font-size: .875rem; cursor: pointer; }
        .cat-pill:hover { border-color: #1e5b31; }
        .cat-pill.active { background: #1e5b31; border-color: #1e5b31; color: #fff; }

        .product-card { border: 1px solid #e3e6e4; cursor: default; transition: box-shadow .15s, border-color .15s; overflow: hidden; }
        .product-card:hover { border-color: #1e5b31; box-shadow: 0 .3rem .7rem rgba(0,0,0,.10); }
        .product-card.out { opacity: .55; }
        .product-img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; display: block; }
        .placeholder-img { display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #eef2ef, #dfe7e2); font-size: 3.5rem; line-height: 1; color: #8a9a91; cursor: help; user-select: none; }
        .name-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.5em; line-height: 1.25; }
        .opt-lines { min-height: 2.6em; font-size: .75rem; line-height: 1.3; }
        .price { color: #ee4d2d; font-weight: 700; }
        .stock-badge { position: absolute; top: .4rem; left: .4rem; font-size: .7rem; }

                .grid-page { padding: .25rem; margin: -.25rem; }
        .right-col { }
        .cart-lines { }
        .cart-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: .35rem; background: #e9edeb; flex-shrink: 0; }
        .qty-btn { width: 28px; padding: 0; }

        .variant-chip { min-width: 64px; }
        .variant-chip:disabled { text-decoration: line-through; opacity: .45; }
        .qty-input { width: 60px; text-align: center; }
    </style>

    <h3 class="mb-3">New Sale</h3>

    <div class="row g-3">
        {{-- ============ MIDDLE: product grid ============ --}}
        <div class="col-lg-8">
            <div class="input-group mb-2">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" id="search" class="form-control" placeholder="Search model name, brand, size or color...">
            </div>

            <div class="cat-pills mb-3" id="cat-pills">
                <button type="button" class="cat-pill active" data-cat="">All</button>
                @foreach ($categories as $c)
                    <button type="button" class="cat-pill" data-cat="{{ $c }}">{{ $c }}</button>
                @endforeach
            </div>

            {{-- The products are paged (12 per page) instead of scrolling, so many products never make the page long. --}}
            <div class="grid-page">
                <div class="row g-2" id="product-grid"></div>
            </div>
            <div id="grid-pager"></div>
        </div>

        {{-- ============ RIGHT: cart + customer/payment + total ============ --}}
        <div class="col-lg-4">
            <form method="POST" action="{{ route('sales.store') }}" enctype="multipart/form-data" id="sale-form" class="right-col">
                @csrf

                {{-- Box 1: cart --}}
                <div class="card shadow-sm mb-3">
                    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-cart3"></i> Cart</span>
                        <span class="badge bg-success" id="cart-count">0</span>
                    </div>
                    <div class="card-body py-2">
                        <div class="cart-lines" id="cart-lines"></div>
                        <div id="cart-warning" class="alert alert-warning py-1 small d-none mt-2 mb-0"></div>
                    </div>
                </div>

                {{-- Box 2: customer + payment --}}
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="fw-semibold small text-uppercase text-muted mb-1">Sold to</div>
                        <input type="text" name="customer_name" class="form-control form-control-sm mb-1" placeholder="Customer name" value="{{ old('customer_name') }}">
                        <input type="text" name="customer_address" class="form-control form-control-sm mb-1" placeholder="Address" value="{{ old('customer_address') }}">
                        <input type="text" name="customer_contact" class="form-control form-control-sm mb-3" placeholder="Contact number" value="{{ old('customer_contact') }}">

                        <div class="fw-semibold small text-uppercase text-muted mb-1">Payment</div>
                        <select name="payment_method" id="payment-method" class="form-select form-select-sm mb-2">
                            @foreach (\App\Models\Sale::PAYMENT_METHODS as $key => $label)
                                <option value="{{ $key }}" @selected(old('payment_method', 'cash') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>

                        {{-- Amount received: needed for every payment method --}}
                        <label class="form-label small mb-0">Amount received (₱)</label>
                        <div class="input-group input-group-sm mb-1">
                            <input type="number" step="0.01" min="0" name="amount_tendered" id="tendered" class="form-control @error('amount_tendered') is-invalid @enderror" placeholder="0.00" required value="{{ old('amount_tendered') }}">
                            <button type="button" class="btn btn-outline-secondary btn-icon" id="exact-btn" title="Exact amount (fill in the total)" aria-label="Exact amount"><i class="bi bi-cash-coin"></i></button>
                        </div>
                        @error('amount_tendered')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                        <div class="small mb-1" id="amount-msg"></div>
                        <div class="small text-muted mb-2">Change: <strong id="change-display">₱0.00</strong></div>

                        {{-- GCash / Bank Transfer: reference number + photo of the payment confirmation --}}
                        <div id="digital-box" class="d-none">
                            @if ($paymentQr)
                                <div class="text-center mb-2">
                                    <img src="{{ $paymentQr }}" alt="Payment QR" style="max-width: 160px;" class="img-fluid border rounded">
                                    <div class="small text-muted">Customer scans this QR, then shows the payment confirmation.</div>
                                </div>
                            @endif
                            <label class="form-label small mb-0">Photo of payment confirmation <span class="text-danger">*</span></label>
                            <input type="file" name="payment_proof" id="payment-proof" accept="image/*" capture="environment" class="form-control form-control-sm mb-2 @error('payment_proof') is-invalid @enderror">
                            @error('payment_proof')<div class="text-danger small mb-1">{{ $message }}</div>@enderror

                            <label class="form-label small mb-0">Reference number <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm mb-1">
                                <input type="text" name="payment_reference" id="payment-reference" maxlength="100" autocomplete="off" class="form-control @error('payment_reference') is-invalid @enderror" placeholder="From the payment confirmation" value="{{ old('payment_reference') }}">
                                <button type="button" class="btn btn-outline-secondary btn-icon" id="scan-btn" disabled title="Read the reference number from the photo (choose the photo first)" aria-label="Read the reference number from the photo"><i class="bi bi-camera"></i></button>
                            </div>
                            @error('payment_reference')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                            <div class="small mb-2" id="scan-msg"></div>
                            <div class="small text-muted">Check the amount on the customer's phone before completing the sale. The photo is required.</div>
                        </div>
                    </div>
                </div>

                {{-- Box 3: total + submit --}}
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div id="cart-inputs"></div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="fs-5 fw-bold">Total <span id="total-display">₱0.00</span></div>
                            <button class="btn btn-success btn-icon" id="submit-btn" disabled title="Complete sale" aria-label="Complete sale">
                                <i class="bi bi-check2-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Variant picker: Size row + Color row --}}
    <div class="modal fade" id="variant-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex gap-3 mb-3">
                        <div id="modal-image" style="width:110px;flex-shrink:0"></div>
                        <div>
                            <div class="price fs-4" id="modal-price"></div>
                            <div class="small text-muted" id="modal-stock"></div>
                        </div>
                    </div>

                    <div id="size-row" class="mb-3">
                        <div class="small text-muted mb-1">Size</div>
                        <div class="d-flex flex-wrap gap-2" id="size-chips"></div>
                    </div>
                    <div id="color-row" class="mb-3">
                        <div class="small text-muted mb-1">Color</div>
                        <div class="d-flex flex-wrap gap-2" id="color-chips"></div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted me-2">Quantity</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm qty-btn" id="modal-minus">&minus;</button>
                        <input type="number" id="modal-qty" class="form-control form-control-sm qty-input" min="1" value="1">
                        <button type="button" class="btn btn-outline-secondary btn-sm qty-btn" id="modal-plus">+</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success btn-icon" id="modal-add" title="Add to cart" aria-label="Add to cart">
                        <i class="bi bi-cart-plus"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const PRODUCTS  = @json($products);
            const OLD_ITEMS = @json(old('items', []));

            /* Work out size + color for every variant.
               Uses v.size / v.color if the controller sends them, otherwise splits "42 / Black". */
            function parseVariant(v) {
                let size = v.size, color = v.color;
                if (size === undefined && color === undefined) {
                    const parts = String(v.label || '').split(' / ').map(s => s.trim()).filter(Boolean);
                    if (parts.length >= 2) { size = parts[0]; color = parts[1]; }
                    else if (parts.length === 1 && parts[0].toLowerCase() !== 'standard') { size = parts[0]; }
                }
                return { size: size || '', color: color || '' };
            }
            PRODUCTS.forEach(p => { p.variants = p.variants.map(v => ({ ...v, ...parseVariant(v) })); });

            const VARIANTS = {};
            PRODUCTS.forEach(p => p.variants.forEach(v => { VARIANTS[v.id] = { ...v, product: p }; }));

            const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const esc  = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const $    = id => document.getElementById(id);
            const uniq = (p, key) => [...new Set(p.variants.map(v => v[key]).filter(Boolean))];
            const inStock = v => v.stock > 0;
            const varText = v => {
                const t = [v.size && `Size: ${v.size}`, v.color && `Color: ${v.color}`].filter(Boolean).join(' · ');
                return t || v.label;
            };

            const GRID_PER_PAGE = 12;
            let gridPage = 1;
            let cart = [];            // [{ id: variantId, qty }]
            let activeCat = '';
            let modalProduct = null;
            let modalVariant = null;
            let selSize = '', selColor = '';
            const modal = new bootstrap.Modal($('variant-modal'));

            /* ---------- catalog ---------- */
            function renderCatalog(resetPage = false) {
                if (resetPage === true) gridPage = 1;
                const q = $('search').value.trim().toLowerCase();

                const list = PRODUCTS.filter(p =>
                    (!activeCat || p.category === activeCat) &&
                    (!q || (p.name + ' ' + (p.brand || '') + ' ' +
                        p.variants.map(v => v.size + ' ' + v.color).join(' ')).toLowerCase().includes(q))
                );

                // Paging: only one page of cards is drawn at a time (the filter above works on the products already loaded).
                const pages = Math.max(1, Math.ceil(list.length / GRID_PER_PAGE));
                gridPage = Math.min(gridPage, pages);
                const start = (gridPage - 1) * GRID_PER_PAGE;

                $('product-grid').innerHTML = list.length ? list.slice(start, start + GRID_PER_PAGE).map(card).join('')
                    : '<div class="col-12 text-center text-muted py-5">No products found.</div>';

                const nums = [];
                for (let i = 1; i <= pages; i++) {
                    if (i === 1 || i === pages || Math.abs(i - gridPage) <= 1) nums.push(i);
                    else if (nums[nums.length - 1] !== '…') nums.push('…');
                }
                $('grid-pager').innerHTML = list.length <= GRID_PER_PAGE ? '' : `<div class="ct-pager">
                    <div class="small text-muted">Showing ${start + 1}–${Math.min(start + GRID_PER_PAGE, list.length)} of ${list.length}</div>
                    <ul class="pagination pagination-sm">
                        <li class="page-item ${gridPage === 1 ? 'disabled' : ''}"><button type="button" class="page-link" data-gp="${gridPage - 1}" title="Previous page" aria-label="Previous page"><i class="bi bi-chevron-left"></i></button></li>
                        ${nums.map(n => n === '…' ? '<li class="page-item disabled"><span class="page-link">…</span></li>'
                            : `<li class="page-item ${n === gridPage ? 'active' : ''}"><button type="button" class="page-link" data-gp="${n}">${n}</button></li>`).join('')}
                        <li class="page-item ${gridPage === pages ? 'disabled' : ''}"><button type="button" class="page-link" data-gp="${gridPage + 1}" title="Next page" aria-label="Next page"><i class="bi bi-chevron-right"></i></button></li>
                    </ul>
                </div>`;
            }

            $('grid-pager').addEventListener('click', e => {
                const b = e.target.closest('[data-gp]');
                if (b) { gridPage = parseInt(b.dataset.gp, 10) || 1; renderCatalog(); }
            });

            function card(p) {
                const prices = p.variants.map(v => v.price);
                const min = Math.min(...prices), max = Math.max(...prices);
                const stock = p.variants.reduce((s, v) => s + v.stock, 0);
                const low = p.variants.some(v => v.stock > 0 && v.stock <= (v.low_stock_threshold ?? 5));
                const sizes = uniq(p, 'size'), colors = uniq(p, 'color');
                const img = p.image
                    ? `<img src="${esc(p.image)}" class="product-img" alt="">`
                    : `<div class="product-img placeholder-img" data-tip="${esc(p.ph_tip)}" role="img" aria-label="${esc(p.ph_tip)}"><i class="bi bi-image"></i></div>`;
                const badge = stock <= 0 ? '<span class="badge bg-secondary stock-badge">Sold out</span>'
                            : (low ? '<span class="badge bg-warning text-dark stock-badge">Low stock</span>' : '');

                return `<div class="col-6 col-md-4 col-xl-3">
                    <div class="card product-card h-100 ${stock <= 0 ? 'out' : ''}" data-id="${p.id}">
                        <div class="position-relative">${img}${badge}</div>
                        <div class="card-body p-2 d-flex flex-column">
                            <div class="name-clamp fw-semibold small mb-1">${esc(p.name)}</div>
                            <div class="small text-muted text-truncate mb-1">${esc(p.brand || p.category || '')}</div>
                            <div class="opt-lines text-muted mb-1">
                                ${sizes.length ? `<div class="text-truncate"><strong>Size:</strong> ${esc(sizes.join(', '))}</div>` : ''}
                                ${colors.length ? `<div class="text-truncate"><strong>Color:</strong> ${esc(colors.join(', '))}</div>` : ''}
                            </div>
                            <div class="price">${min === max ? peso(min) : peso(min) + ' – ' + peso(max)}</div>
                            <div class="small ${stock <= 0 ? 'text-danger' : 'text-muted'} mb-2">${stock <= 0 ? 'Out of stock' : stock + ' in stock'}</div>
                            <button type="button" class="btn btn-outline-success btn-icon add-btn mt-auto ms-auto" ${stock <= 0 ? 'disabled' : ''} title="Add to cart" aria-label="Add to cart">
                                <i class="bi bi-cart-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
            }

            // Only the Add to cart button adds a product (or opens the size / color picker). Clicking the picture or the text does nothing.
            $('product-grid').addEventListener('click', e => {
                const btn = e.target.closest('.add-btn');
                if (!btn || btn.disabled) return;
                openProduct(parseInt(btn.closest('.product-card').dataset.id, 10));
            });
            $('search').addEventListener('input', () => renderCatalog(true));
            $('cat-pills').addEventListener('click', e => {
                const pill = e.target.closest('.cat-pill');
                if (!pill) return;
                activeCat = pill.dataset.cat;
                document.querySelectorAll('.cat-pill').forEach(b => b.classList.toggle('active', b === pill));
                renderCatalog(true);
            });

            /* ---------- variant picker (size + color) ---------- */
            function resolveVariant() {
                return modalProduct.variants.find(v => v.size === selSize && v.color === selColor);
            }

            function openProduct(id) {
                const p = PRODUCTS.find(x => x.id === id);
                if (!p || p.variants.every(v => v.stock <= 0)) return;

                // Products without size/color go straight into the cart.
                if (p.variants.length === 1) { addToCart(p.variants[0].id, 1); return; }

                modalProduct = p;
                $('modal-title').textContent = p.name;
                $('modal-image').innerHTML = p.image
                    ? `<img src="${esc(p.image)}" class="img-fluid rounded" alt="">`
                    : `<div class="product-img placeholder-img rounded" data-tip="${esc(p.ph_tip)}" role="img" aria-label="${esc(p.ph_tip)}"><i class="bi bi-image"></i></div>`;

                const first = p.variants.find(inStock);
                selSize = first.size;
                selColor = first.color;
                renderOptions();
                modal.show();
            }

            function setOption(key, val) {
                if (key === 'size') selSize = val; else selColor = val;

                // If this size/color combination doesn't exist or is sold out,
                // switch the other option to one that works.
                const v = resolveVariant();
                if (!v || !inStock(v)) {
                    const alt = modalProduct.variants.find(x => x[key] === val && inStock(x));
                    if (alt) { selSize = alt.size; selColor = alt.color; }
                }
                renderOptions();
            }

            function renderOptions() {
                const p = modalProduct;
                const sizes = uniq(p, 'size'), colors = uniq(p, 'color');

                $('size-row').classList.toggle('d-none', !sizes.length);
                $('color-row').classList.toggle('d-none', !colors.length);

                const chip = (key, val, selected) => {
                    const ok = p.variants.some(v => v[key] === val && inStock(v));
                    return `<button type="button" class="btn btn-sm variant-chip ${selected === val ? 'btn-success' : 'btn-outline-secondary'}"
                        data-key="${key}" data-val="${esc(val)}" ${ok ? '' : 'disabled'}>${esc(val)}</button>`;
                };
                $('size-chips').innerHTML  = sizes.map(s => chip('size', s, selSize)).join('');
                $('color-chips').innerHTML = colors.map(c => chip('color', c, selColor)).join('');

                modalVariant = resolveVariant();
                const ok = modalVariant && inStock(modalVariant);
                $('modal-add').disabled = !ok;

                if (modalVariant) {
                    $('modal-price').textContent = peso(modalVariant.price);
                    $('modal-stock').textContent = ok ? modalVariant.stock + ' left' : 'Out of stock';
                    $('modal-qty').max = modalVariant.stock;
                } else {
                    $('modal-price').textContent = '';
                    $('modal-stock').textContent = 'This combination is not available';
                }
                $('modal-qty').value = 1;
            }

            function clampModalQty(n) {
                const max = modalVariant ? modalVariant.stock : 1;
                $('modal-qty').value = Math.max(1, Math.min(n || 1, max));
            }

            $('variant-modal').addEventListener('click', e => {
                const b = e.target.closest('.variant-chip');
                if (b && !b.disabled) setOption(b.dataset.key, b.dataset.val);
            });
            $('modal-minus').addEventListener('click', () => clampModalQty(parseInt($('modal-qty').value, 10) - 1));
            $('modal-plus').addEventListener('click',  () => clampModalQty(parseInt($('modal-qty').value, 10) + 1));
            $('modal-qty').addEventListener('change',  () => clampModalQty(parseInt($('modal-qty').value, 10)));

            $('modal-add').addEventListener('click', () => {
                if (!modalVariant || !inStock(modalVariant)) return;
                addToCart(modalVariant.id, parseInt($('modal-qty').value, 10) || 1);
                modal.hide();
            });

            /* ---------- cart ---------- */
            function addToCart(variantId, qty) {
                const v = VARIANTS[variantId];
                const line = cart.find(l => l.id === variantId);
                const wanted = (line ? line.qty : 0) + qty;
                const final = Math.min(wanted, v.stock);

                warn(wanted > v.stock ? `Only ${v.stock} of ${v.product.name} (${v.label}) in stock.` : '');
                if (final < 1) return;

                if (line) line.qty = final; else cart.push({ id: variantId, qty: final });
                renderCart();
            }

            function warn(msg) {
                const box = $('cart-warning');
                box.textContent = msg;
                box.classList.toggle('d-none', !msg);
            }

            function cartTotal() {
                return cart.reduce((s, l) => s + VARIANTS[l.id].price * l.qty, 0);
            }

            function renderCart() {
                $('cart-lines').innerHTML = cart.length ? cart.map(l => {
                    const v = VARIANTS[l.id];
                    const thumb = v.product.image
                        ? `<img src="${esc(v.product.image)}" class="cart-thumb" alt="">`
                        : `<div class="cart-thumb d-flex align-items-center justify-content-center fs-4" data-tip="${esc(v.product.ph_tip)}" role="img" aria-label="${esc(v.product.ph_tip)}"><i class="bi bi-image"></i></div>`;
                    return `<div class="d-flex gap-2 border-bottom py-2">
                        ${thumb}
                        <div class="flex-grow-1">
                            <div class="fw-semibold small lh-sm">${esc(v.product.name)}</div>
                            <div class="small text-muted">${esc(varText(v))}</div>
                            <div class="small text-muted">${peso(v.price)} each</div>
                            <div class="d-flex align-items-center justify-content-between mt-1">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary qty-btn" data-act="dec" data-id="${l.id}">&minus;</button>
                                    <span class="btn btn-light disabled px-2">${l.qty}</span>
                                    <button type="button" class="btn btn-outline-secondary qty-btn" data-act="inc" data-id="${l.id}">+</button>
                                </div>
                                <span class="price small">${peso(v.price * l.qty)}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-link text-danger p-0 align-self-start" data-act="rm" data-id="${l.id}" title="Remove"><i class="bi bi-trash"></i></button>
                    </div>`;
                }).join('') : '<div class="text-muted small text-center py-4"><i class="bi bi-cart fs-3 d-block mb-1"></i>Your cart is empty.<br>Click a product to add it.</div>';

                $('cart-count').textContent = cart.reduce((s, l) => s + l.qty, 0);
                $('total-display').textContent = peso(cartTotal());
                updateChange();
            }

            $('cart-lines').addEventListener('click', e => {
                const btn = e.target.closest('button[data-act]');
                if (!btn) return;
                const id = parseInt(btn.dataset.id, 10);
                const line = cart.find(l => l.id === id);
                if (btn.dataset.act === 'rm') cart = cart.filter(l => l.id !== id);
                if (btn.dataset.act === 'inc') addToCart(id, 1);
                if (btn.dataset.act === 'dec' && line) { line.qty -= 1; if (line.qty < 1) cart = cart.filter(l => l.id !== id); warn(''); }
                renderCart();
            });

            /* ---------- payment ---------- */
            const isCash = () => $('payment-method').value === 'cash';

            function togglePayment() {
                $('digital-box').classList.toggle('d-none', isCash());
                $('payment-proof').required = !isCash();
                $('payment-reference').required = !isCash();
                refreshSubmit();
            }

            /* Amount received is required, and it must cover the total (more is fine: change is given). */
            function amountOk() {
                const tendered = parseFloat($('tendered').value);
                return !isNaN(tendered) && tendered + 0.001 >= cartTotal();
            }

            function refreshSubmit() {
                const hasProof = isCash() || ($('payment-proof').files.length > 0 && $('payment-reference').value.trim() !== '');
                $('submit-btn').disabled = !(cart.length > 0 && amountOk() && hasProof);
            }

            function updateChange() {
                const total = cartTotal();
                const tendered = parseFloat($('tendered').value);
                const msg = $('amount-msg');

                if (isNaN(tendered) || total <= 0) {
                    msg.textContent = '';
                } else if (tendered + 0.001 < total) {
                    msg.textContent = 'Short by ' + peso(total - tendered) + ' - the sale cannot be completed yet.';
                    msg.className = 'small mb-1 text-danger';
                } else {
                    msg.textContent = '';
                }

                $('change-display').textContent = peso(isNaN(tendered) ? 0 : Math.max(0, tendered - total));
                refreshSubmit();
            }

            $('payment-method').addEventListener('change', togglePayment);
            $('tendered').addEventListener('input', updateChange);
            $('payment-proof').addEventListener('change', refreshSubmit);
            $('payment-reference').addEventListener('input', refreshSubmit);
            $('exact-btn').addEventListener('click', () => { $('tendered').value = cartTotal().toFixed(2); updateChange(); });

            /* ---------- read the reference number from the photo (a SUGGESTION - the cashier can always edit it) ---------- */
            /* The photo is read inside this browser. It is not sent to any other service. */
            /* Finds the most likely payment reference number in text read from a payment screenshot.
               It only SUGGESTS - the cashier always checks it against the photo. */
            function extractReference(text) {
                const lines = String(text || '').split(/\r?\n/).map(l => l.trim()).filter(Boolean);

                // OCR often reads 0 as O and 1 as I/l inside numbers; fix that only when the value is mostly digits.
                function tidy(v) {
                    v = v.trim().replace(/[\s\-]+$/, '');
                    const digits = (v.match(/\d/g) || []).length;
                    if (digits >= 6 && digits / v.replace(/[\s\-]/g, '').length >= 0.7) {
                        v = v.replace(/[Oo]/g, '0').replace(/[Il|]/g, '1');
                    }
                    return v;
                }

                // Inside number-like pieces, OCR reads 0 as O and 1 as I / l / | - repair those (words like "Oct" are left alone).
                function repair(s) {
                    return s.replace(/[0-9OoIl|]{2,}/g, tok => /\d/.test(tok) ? tok.replace(/[Oo]/g, '0').replace(/[Il|]/g, '1') : tok);
                }

                // digits grouped like "8012 345 678901" or "8012-345-678901"
                function digitGroups(s) {
                    s = repair(s);
                    const m = s.match(/\d{2,}(?:[ \-]\d{2,}){0,4}/g) || [];
                    return m.filter(x => x.replace(/\D/g, '').length >= 6)
                            .sort((a, b) => b.replace(/\D/g, '').length - a.replace(/\D/g, '').length)[0] || null;
                }

                // letters + digits like "A1B2C3D4E5" (some banks / Maya)
                function codeToken(s) {
                    const m = s.match(/\b[A-Za-z0-9]{8,30}\b/g) || [];
                    return m.find(x => /\d/.test(x) && /[A-Za-z]/.test(x)) || null;
                }

                const label = /(?:ref(?:erence)?\.?\s*(?:no\.?|number|num\.?|id|code|#)?|trace\s*(?:no\.?|number)?|transaction\s*(?:no\.?|number|id|code)?|txn\s*(?:no\.?|id)?|confirmation\s*(?:no\.?|number|code)?)\s*[:#.\-]?\s*/i;

                for (let i = 0; i < lines.length; i++) {
                    const m = lines[i].match(label);
                    if (!m) continue;
                    let rest = lines[i].slice(m.index + m[0].length);
                    if (!rest.trim() && lines[i + 1]) rest = lines[i + 1];          // value is on the next line
                    const found = digitGroups(rest) || codeToken(rest);
                    if (found) return tidy(found);
                }

                // No label found: take the longest run of digits (10+ digits) anywhere in the text.
                const any = digitGroups(lines.join('  '));
                if (any && any.replace(/\D/g, '').length >= 10) return tidy(any);
                return '';
            }

            const TESSERACT_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';
            let tesseractLoading = null;

            /* The reader is only downloaded the first time someone presses the button. */
            function loadTesseract() {
                if (window.Tesseract) return Promise.resolve();
                if (!tesseractLoading) {
                    tesseractLoading = new Promise((resolve, reject) => {
                        const tag = document.createElement('script');
                        tag.src = TESSERACT_URL;
                        tag.onload = resolve;
                        tag.onerror = () => { tesseractLoading = null; reject(new Error('load')); };
                        document.head.appendChild(tag);
                    });
                }
                return tesseractLoading;
            }

            /* Big phone photos are shrunk first so reading is faster. */
            function photoToCanvas(file) {
                return new Promise((resolve, reject) => {
                    const url = URL.createObjectURL(file);
                    const img = new Image();
                    img.onload = () => {
                        const k = Math.min(1, 1800 / Math.max(img.width, img.height));
                        const c = document.createElement('canvas');
                        c.width = Math.round(img.width * k);
                        c.height = Math.round(img.height * k);
                        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                        URL.revokeObjectURL(url);
                        resolve(c);
                    };
                    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('image')); };
                    img.src = url;
                });
            }

            function scanMessage(kind, text, value) {
                const box = $('scan-msg');
                box.className = 'small mb-2 ' + (kind === 'error' ? 'text-danger' : (kind === 'ok' ? 'text-success' : 'text-muted'));
                box.textContent = text;                       // textContent on purpose: text read from a photo is never treated as HTML
                if (value) {
                    const strong = document.createElement('strong');
                    strong.textContent = ' ' + value + ' ';
                    box.appendChild(strong);
                    const use = document.createElement('button');
                    use.type = 'button';
                    use.className = 'btn btn-link btn-sm p-0 align-baseline';
                    use.textContent = 'Use this';
                    use.addEventListener('click', () => {
                        $('payment-reference').value = value;
                        refreshSubmit();
                        scanMessage('ok', 'Reference number filled in. Check it against the photo before completing the sale.');
                    });
                    box.appendChild(use);
                }
            }

            let scanning = false;
            async function scanPhoto() {
                const file = $('payment-proof').files[0];
                if (!file || scanning) return;
                scanning = true;
                $('scan-btn').disabled = true;
                scanMessage('info', 'Reading the photo... the first time it can take a little while.');
                try {
                    await loadTesseract();
                    const canvas = await photoToCanvas(file);
                    const result = await window.Tesseract.recognize(canvas, 'eng');
                    const found = extractReference(result.data.text);
                    const field = $('payment-reference');
                    const typed = field.value.trim();

                    if (!found) {
                        scanMessage('error', "Couldn't find a reference number in the photo. Please type it from the confirmation screen.");
                    } else if (typed === '') {
                        field.value = found;
                        refreshSubmit();
                        scanMessage('ok', 'Reference number filled in. Check it against the photo before completing the sale.');
                    } else if (typed.replace(/[\s-]/g, '') === found.replace(/[\s-]/g, '')) {
                        scanMessage('ok', 'The number you typed matches the photo.');
                    } else {
                        scanMessage('info', 'The photo seems to say:', found);
                    }
                } catch (err) {
                    scanMessage('error', "Couldn't read the photo (the reader may not have loaded - check the internet). Please type the reference number.");
                } finally {
                    scanning = false;
                    $('scan-btn').disabled = $('payment-proof').files.length === 0;
                }
            }

            $('scan-btn').addEventListener('click', scanPhoto);
            $('payment-proof').addEventListener('change', () => {
                $('scan-btn').disabled = $('payment-proof').files.length === 0;
                $('scan-msg').textContent = '';
            });

            /* ---------- submit ---------- */
            $('sale-form').addEventListener('submit', e => {
                if (!cart.length || !amountOk()) {
                    e.preventDefault();
                    warn(cart.length ? 'The amount received is less than the total.' : '');
                    return;
                }
                if (!isCash() && $('payment-reference').value.trim() === '') {
                    e.preventDefault();
                    warn('Enter the reference number from the payment confirmation.');
                    return;
                }
                if (!isCash() && $('payment-proof').files.length === 0) {
                    e.preventDefault();
                    warn('Take or upload a photo of the payment confirmation.');
                    return;
                }

                $('cart-inputs').innerHTML = cart.map((l, i) =>
                    `<input type="hidden" name="items[${i}][variant_id]" value="${l.id}">
                     <input type="hidden" name="items[${i}][quantity]" value="${l.qty}">`
                ).join('');
            });

            /* ---------- start ---------- */
            OLD_ITEMS.forEach(i => { if (VARIANTS[i.variant_id]) cart.push({ id: parseInt(i.variant_id, 10), qty: parseInt(i.quantity, 10) || 1 }); });
            renderCatalog();
            togglePayment();
            renderCart();
        });
    </script>
@endsection