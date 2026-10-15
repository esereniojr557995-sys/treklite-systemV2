@extends('layouts.app')

@section('title', 'Item Options')

@section('content')
    <style>
        .opt-tabs .nav-link { color: #1e5b31; }
        .opt-tabs .nav-link.active { background: #1e5b31; color: #fff; }
        .opt-tabs .badge { font-weight: 500; }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0">Item Options</h3>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-icon" title="Back to Products" aria-label="Back to Products"><i class="bi bi-arrow-left"></i></a>
    </div>

    <div class="alert alert-light border small py-2">
        <i class="bi bi-info-circle"></i>
        These are the choices offered when you add or edit a product. Products can only <strong>pick</strong> from these options, so a name is never mistyped or duplicated.
        <strong>Disable</strong> hides an entry from new products (old products keep it). An entry can only be <strong>deleted</strong> while nothing uses it.
    </div>

    <ul class="nav nav-pills opt-tabs mb-3 gap-1" role="tablist">
        @foreach ($types as $key => $t)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $tab === $key ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#tab-{{ $key }}" type="button" role="tab">
                    <i class="bi {{ $t['icon'] }}"></i> {{ $t['many'] }}
                    <span class="badge text-bg-light border ms-1">{{ $lists[$key]->count() }}</span>
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        @foreach ($types as $key => $t)
            <div class="tab-pane fade {{ $tab === $key ? 'show active' : '' }}" id="tab-{{ $key }}" role="tabpanel">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold">{{ $t['many'] }}</span>
                        <button type="button" class="btn btn-success btn-icon" data-opt-add="{{ $key }}" title="Add {{ strtolower($t['one']) }}" aria-label="Add {{ strtolower($t['one']) }}"><i class="bi bi-plus-lg"></i></button>
                    </div>
                    <div data-ctable data-per-page="10">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead>
                                <tr>
                                    @if ($key === 'sizes')<th style="width:90px">Order</th>@endif
                                    <th>{{ $t['one'] }}</th>
                                    <th class="text-center" style="width:150px">Used by</th>
                                    <th class="text-center" style="width:120px">Status</th>
                                    <th class="text-center" style="width:150px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lists[$key] as $row)
                                    <tr class="{{ $row->is_active ? '' : 'table-light text-muted' }}" data-row>
                                        @if ($key === 'sizes')<td>{{ $row->sort_order }}</td>@endif
                                        <td class="fw-semibold">{{ $row->name }}</td>
                                        <td class="text-center">
                                            @if ($row->used_count > 0)
                                                {{ $row->used_count }} {{ $t['uses'] === 'products' ? ($row->used_count == 1 ? 'product' : 'products') : ($row->used_count == 1 ? 'item' : 'items') }}
                                            @else
                                                <span class="text-muted">Not used</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $row->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $row->is_active ? 'Enabled' : 'Disabled' }}</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <button type="button" class="btn btn-outline-secondary btn-icon" title="Rename / edit" aria-label="Edit"
                                                        data-opt-edit="{{ $key }}" data-id="{{ $row->id }}" data-name="{{ $row->name }}" data-order="{{ $row->sort_order ?? '' }}">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" class="btn {{ $row->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} btn-icon"
                                                        title="{{ $row->is_active ? 'Disable (hide from new products)' : 'Enable again' }}" aria-label="Enable or disable"
                                                        data-opt-toggle="{{ $key }}" data-id="{{ $row->id }}">
                                                    <i class="bi {{ $row->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                                                </button>
                                                <span class="d-inline-block" title="{{ $row->used_count > 0 ? 'In use - cannot delete. Disable it instead.' : 'Delete' }}">
                                                    <button type="button" class="btn btn-outline-danger btn-icon" aria-label="Delete"
                                                            data-opt-delete="{{ $key }}" data-id="{{ $row->id }}" data-name="{{ $row->name }}" data-used="{{ $row->used_count }}"
                                                            @disabled($row->used_count > 0)>
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">No {{ strtolower($t['many']) }} yet. Press the <i class="bi bi-plus-lg"></i> icon above.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div data-pager></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ===== Add / Edit pop-up ===== --}}
    <div class="modal fade" id="opt-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="opt-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="opt-title">Add</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="opt-error"></div>
                    <label class="form-label" for="opt-name" id="opt-name-label">Name</label>
                    <input type="text" class="form-control" id="opt-name" maxlength="255" autocomplete="off" required>
                    <div id="opt-order-wrap" class="mt-3 d-none">
                        <label class="form-label" for="opt-order">Order in the list</label>
                        <input type="number" min="0" class="form-control" id="opt-order">
                        <div class="form-text">Smaller numbers appear first (e.g. S = 1, M = 2, L = 3). Leave empty to put it last.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-icon" data-bs-dismiss="modal" title="Cancel" aria-label="Cancel"><i class="bi bi-x-lg"></i></button>
                    <button type="submit" class="btn btn-success btn-icon" id="opt-save" title="Save" aria-label="Save"><i class="bi bi-check-lg"></i></button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Delete pop-up ===== --}}
    <div class="modal fade" id="opt-del-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete <span id="opt-del-type"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="opt-del-error"></div>
                    Permanently delete <strong id="opt-del-name"></strong>? This cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-icon" data-bs-dismiss="modal" title="Cancel" aria-label="Cancel"><i class="bi bi-x-lg"></i></button>
                    <button type="button" class="btn btn-danger btn-icon" id="opt-del-confirm" title="Delete" aria-label="Delete"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            const TYPES = @json($types);
            const BASE  = @json(url('/options'));
            const CSRF  = document.querySelector('meta[name="csrf-token"]')?.content ?? @json(csrf_token());

            const modal    = new bootstrap.Modal('#opt-modal');
            const delModal = new bootstrap.Modal('#opt-del-modal');
            const form = document.getElementById('opt-form');
            const nameEl = document.getElementById('opt-name'), orderEl = document.getElementById('opt-order');
            const errEl = document.getElementById('opt-error'), delErr = document.getElementById('opt-del-error');
            let current = null;   // { type, id|null }

            async function send(method, url, body) {
                const res = await fetch(url, {
                    method,
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Reload': '1' },
                    body: body ? JSON.stringify(body) : undefined,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'Something went wrong. Please try again.'));
                return json;
            }

            // Keep the same tab open after the page reloads
            const reload = type => { location.href = BASE + '?tab=' + type; };

            function openForm(type, row) {
                current = { type, id: row?.id ?? null };
                const t = TYPES[type];
                document.getElementById('opt-title').textContent = (row ? 'Edit ' : 'Add ') + t.one;
                document.getElementById('opt-name-label').textContent = t.one + ' name';
                nameEl.maxLength = t.max;
                nameEl.value = row?.name ?? '';
                nameEl.style.textTransform = type === 'brands' ? 'uppercase' : '';   // brands are typed (and saved) in capitals
                orderEl.value = row?.order ?? '';
                document.getElementById('opt-order-wrap').classList.toggle('d-none', type !== 'sizes');
                errEl.classList.add('d-none');
                modal.show();
            }

            document.getElementById('opt-modal').addEventListener('shown.bs.modal', () => nameEl.focus());

            form.addEventListener('submit', async e => {
                e.preventDefault();
                errEl.classList.add('d-none');
                const body = { name: nameEl.value };
                if (current.type === 'sizes') body.sort_order = orderEl.value;
                const save = document.getElementById('opt-save');
                save.disabled = true;
                try {
                    await send(current.id ? 'PUT' : 'POST', BASE + '/' + current.type + (current.id ? '/' + current.id : ''), body);
                    reload(current.type);
                } catch (err) {
                    errEl.textContent = err.message;
                    errEl.classList.remove('d-none');
                    save.disabled = false;
                }
            });

            document.addEventListener('click', async e => {
                const add = e.target.closest('[data-opt-add]');
                if (add) return openForm(add.dataset.optAdd, null);

                const edit = e.target.closest('[data-opt-edit]');
                if (edit) return openForm(edit.dataset.optEdit, { id: edit.dataset.id, name: edit.dataset.name, order: edit.dataset.order });

                const tog = e.target.closest('[data-opt-toggle]');
                if (tog) {
                    tog.disabled = true;
                    try { await send('POST', BASE + '/' + tog.dataset.optToggle + '/' + tog.dataset.id + '/toggle'); reload(tog.dataset.optToggle); }
                    catch (err) { alert(err.message); tog.disabled = false; }
                    return;
                }

                const del = e.target.closest('[data-opt-delete]');
                if (del && !del.disabled) {
                    current = { type: del.dataset.optDelete, id: del.dataset.id };
                    document.getElementById('opt-del-type').textContent = TYPES[current.type].one.toLowerCase();
                    document.getElementById('opt-del-name').textContent = del.dataset.name;
                    delErr.classList.add('d-none');
                    delModal.show();
                }
            });

            document.getElementById('opt-del-confirm').addEventListener('click', async () => {
                try { await send('DELETE', BASE + '/' + current.type + '/' + current.id); reload(current.type); }
                catch (err) { delErr.textContent = err.message; delErr.classList.remove('d-none'); }
            });
        });
    </script>
@endsection
