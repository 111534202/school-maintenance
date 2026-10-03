{{-- 派工通知信的內容（Markdown 郵件版面，由 App\Mail\RepairDispatchedMail 使用）。 --}}
{{-- x-mail::message / table / button 是 Laravel 內建的郵件元件；文字都來自 lang/各語言資料夾/mail.php。 --}}
{{-- 注意：Markdown 的表格不能在中間夾空行，所以這個檔案的表格區塊不要插入任何註解或空行。 --}}
<x-mail::message>
# {{ __('mail.dispatched.heading') }}

{{ __('mail.dispatched.intro', ['title' => $repairRequest->title]) }}

<x-mail::table>
| | |
|:---|:---|
| **{{ __('repair_requests.table.title') }}** | {{ $repairRequest->title }} |
| **{{ __('repair_requests.table.device_location') }}** | {{ $repairRequest->device?->device_code ?? $repairRequest->device_note ?? $repairRequest->location ?? __('repair_requests.not_filled') }} |
| **{{ __('repair_requests.show.assignee_field_label') }}** | {{ $technician->name }} |
@if ($repairRequest->scheduled_at)
| **{{ __('repair_requests.show.scheduled_field_label') }}** | {{ $repairRequest->scheduled_at->format('Y-m-d H:i') }} |
@endif
</x-mail::table>

**{{ __('mail.dispatched.description_heading') }}**
{{ $repairRequest->description }}

<x-mail::button :url="route('repairs.show', $repairRequest)">
{{ __('mail.dispatched.view_button') }}
</x-mail::button>

{{ __('mail.dispatched.footer') }}<br>
{{ config('app.name') }}
</x-mail::message>
