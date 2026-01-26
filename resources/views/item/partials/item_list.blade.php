@foreach($items as $item)
<div class="col-xl-3 col-lg-4 col-md-6 item-wrapper animate__animated animate__fadeIn">
    <div class="card h-100 border-0 shadow-sm item-card position-relative">
        <!-- Checkbox untuk Bulk Action -->
        <div class="position-absolute top-0 start-0 p-2 z-3">
            <input class="form-check-input item-checkbox shadow-none" type="checkbox" data-item-id="{{ $item->id }}" style="width: 1.2rem; height: 1.2rem; cursor: pointer;">
        </div>

        <a href="{{ route('item.show', $item->id) }}" class="text-decoration-none text-dark h-100 d-flex flex-column">
            <div class="card-img-top bg-light d-flex align-items-center justify-content-center overflow-hidden" style="height: 140px;">
                @if($item->image_link)
                    <img src="{{ $item->image_link }}" class="w-100 h-100 object-fit-cover" onerror="this.src='https://placehold.co/400x300?text=No+Image'">
                @else
                    <i class="fas {{ $item->is_bom ? 'fa-layer-group text-primary' : 'fa-box text-secondary' }} fa-4x opacity-20"></i>
                @endif
            </div>
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <h6 class="card-title text-truncate fw-bold mb-0 flex-grow-1 text-dark">{{ $item->nama }}</h6>
                    @if($item->is_bom)
                        <span class="badge bg-primary text-white" style="font-size: 0.55rem; letter-spacing: 1px;">BOM</span>
                    @endif
                </div>

                <!-- Tags & Note Indicator -->
                <div class="mb-2 d-flex flex-wrap gap-1 align-items-center">
                    @if(is_array($item->tags))
                        @foreach(array_slice($item->tags, 0, 2) as $tag)
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small" style="font-size: 0.6rem;">#{{ $tag }}</span>
                        @endforeach
                    @endif
                    @if($item->note)
                        <i class="fas fa-sticky-note text-warning ms-auto small shadow-sm" title="{{ $item->note }}" data-bs-toggle="tooltip"></i>
                    @endif
                </div>

                <!-- BOM MATERIALS PREVIEW (NAMA BAHAN) -->
                @if($item->is_bom && is_array($item->materials))
                    <div class="bg-light rounded p-2 mb-2 border border-light-subtle">
                        <small class="text-muted d-block fw-bold mb-1" style="font-size: 0.6rem; text-transform: uppercase;">Komponen Material:</small>
                        <div class="d-flex flex-column gap-1">
                            @foreach(array_slice($item->materials, 0, 3) as $m)
                                <div class="d-flex justify-content-between small text-truncate" style="font-size: 0.65rem; color: #555;">
                                    <span>• {{ $materialMap[$m['item_id']] ?? 'Item #'.$m['item_id'] }}</span>
                                    <span class="fw-bold text-muted">x{{ $m['qty'] }}</span>
                                </div>
                            @endforeach
                            @if(count($item->materials) > 3)
                                <small class="text-primary italic" style="font-size: 0.6rem;">+{{ count($item->materials) - 3 }} bahan lainnya...</small>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Stok & Info Harga Ganda -->
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div>
                        <span class="badge {{ $item->calculated_stock <= $item->stok_minimum ? 'bg-danger' : 'bg-success-subtle text-success' }} border shadow-sm">
                            {{ number_format($item->calculated_stock, 0) }} {{ $item->satuan }}
                        </span>
                        @if($item->stok_minimum > 0)
                            <div class="text-muted fw-bold" style="font-size: 0.65rem; margin-top: 3px;">Min: {{ number_format($item->stok_minimum, 0) }}</div>
                        @endif
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block" style="font-size: 0.55rem; line-height: 1;">Beli: Rp{{ number_format($item->harga_beli, 0) }}</small>
                        <span class="fw-bold text-dark" style="font-size: 0.85rem;">Jual: Rp{{ number_format($item->harga_jual, 0) }}</span>
                    </div>
                </div>
            </div>
        </a>
        <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-2">
            <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark shadow-sm" onclick="openMoveModal('item', {{ $item->id }}, '{{ $item->nama }}')" title="Pindahkan Item"><i class="fas fa-external-link-alt"></i></button>
            <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark shadow-sm" onclick="openQtyModal({{ $item->id }}, '{{ $item->nama }}')" title="Update Stok Cepat"><i class="fas fa-plus-minus"></i></button>
        </div>
    </div>
</div>
@endforeach

<!-- [PENTING] Penanda URL Berikutnya untuk JavaScript -->
@if($items->hasMorePages())
    <div class="next-page-url d-none" data-url="{{ $items->nextPageUrl() }}"></div>
@endif
