{{-- 附件清單：圖片直接顯示縮圖（點一下在新分頁看原圖），影片與 PDF 顯示檔名連結。
     用法：@include('repairs._attachment-list', ['attachments' => $repairRequest->attachments]) --}}
@php
    $images = $attachments->filter(fn ($attachment) => $attachment->isImage());
    $others = $attachments->reject(fn ($attachment) => $attachment->isImage());
@endphp
@if ($images->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 {{ $others->isNotEmpty() ? 'mb-2' : '' }}">
        @foreach ($images as $attachment)
            <a href="{{ $attachment->url() }}" target="_blank" title="{{ $attachment->file_name }}">
                <img src="{{ $attachment->url() }}" alt="{{ $attachment->file_name }}" loading="lazy" class="rounded border"
                     style="width: 120px; height: 120px; object-fit: cover;">
            </a>
        @endforeach
    </div>
@endif
@if ($others->isNotEmpty())
    <ul class="list-unstyled mb-0">
        @foreach ($others as $attachment)
            <li><a href="{{ $attachment->url() }}" target="_blank" class="text-decoration-none"><i class="bi bi-file-earmark me-1"></i>{{ $attachment->file_name }}</a></li>
        @endforeach
    </ul>
@endif
