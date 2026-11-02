{{-- 風格 B「校園溫和」的字型與樣式（只有包在 <div class="tb"> 裡的頁面會套用，規則見 public/css/theme-b.css）。 --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&family=Noto+Serif+TC:wght@600;700&display=swap" rel="stylesheet">
<link href="{{ asset('css/theme-b.css') }}?v={{ filemtime(public_path('css/theme-b.css')) }}" rel="stylesheet">
