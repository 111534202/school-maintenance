{{-- 附件清單：圖片顯示縮圖、影片與 PDF 顯示檔名；點一下在目前畫面的預覽視窗打開（不換頁），視窗裡可以左右切換同一組附件。
     用法：@include('repairs._attachment-list', ['attachments' => $repairRequest->attachments])
     頁面上要引入一次預覽視窗：@include('parts._attachment-modal')。 --}}
@php
    $images = $attachments->filter(fn ($attachment) => $attachment->isImage())->values();
    $others = $attachments->reject(fn ($attachment) => $attachment->isImage())->values();
    // 預覽視窗的清單：圖片在前、其他在後（和畫面上的順序一樣），點哪一個就從哪一個開始。
    $previewItems = $images->concat($others)->map(fn ($attachment) => ['url' => $attachment->url(), 'mime' => $attachment->mime_type, 'filename' => $attachment->file_name])->values()->toJson();
@endphp
@if ($images->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 {{ $others->isNotEmpty() ? 'mb-2' : '' }}">
        @foreach ($images as $attachment)
            <a href="{{ $attachment->url() }}" title="{{ $attachment->file_name }}" data-bs-toggle="modal" data-bs-target="#attachmentModal"
               data-title="附件" data-attachments="{{ $previewItems }}" data-index="{{ $loop->index }}">
                <img src="{{ $attachment->url() }}" alt="{{ $attachment->file_name }}" loading="lazy" class="rounded border"
                     style="width: 120px; height: 120px; object-fit: cover;">
            </a>
        @endforeach
    </div>
@endif
@if ($others->isNotEmpty())
    <ul class="list-unstyled mb-0">
        @foreach ($others as $attachment)
            <li><a href="{{ $attachment->url() }}" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#attachmentModal"
                   data-title="附件" data-attachments="{{ $previewItems }}" data-index="{{ $images->count() + $loop->index }}"><i class="bi bi-file-earmark me-1"></i>{{ $attachment->file_name }}</a></li>
        @endforeach
    </ul>
@endif
