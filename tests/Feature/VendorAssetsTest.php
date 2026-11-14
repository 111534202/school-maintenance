<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * 外部資源（Bootstrap、圖示、Chart.js、掃描解碼器）都放在專案的 public/vendor。
 * 這個測試防止之後有人又把畫面改回連外部網站（校內網路擋外網時整個畫面會壞掉）。
 */
class VendorAssetsTest extends TestCase
{
    public function test_vendor_files_exist_and_are_not_empty(): void
    {
        $files = [
            'bootstrap/bootstrap.min.css',
            'bootstrap/bootstrap.bundle.min.js',
            'bootstrap-icons/bootstrap-icons.min.css',
            'bootstrap-icons/fonts/bootstrap-icons.woff2',
            'bootstrap-icons/fonts/bootstrap-icons.woff',
            'chartjs/chart.umd.min.js',
            'barcode-detector/index.js',
            'zxing/zxing_reader.wasm',
        ];

        foreach ($files as $file) {
            $path = public_path('vendor/' . $file);
            $this->assertFileExists($path, "缺少 public/vendor/{$file}");
            $this->assertGreaterThan(10000, filesize($path), "public/vendor/{$file} 檔案太小，可能下載不完整");
        }
    }

    public function test_views_do_not_load_anything_from_external_hosts(): void
    {
        $viewFiles = collect(File::allFiles(resource_path('views')));

        $offenders = [];
        foreach ($viewFiles as $file) {
            $content = File::get($file->getPathname());
            // 只檢查「載入資源」的標籤與動態 import，不檢查一般文字連結（例如說明文字裡的網址）。
            if (preg_match('/<(?:script|link|img|source|iframe)\b[^>]*\b(?:src|href)\s*=\s*["\']https?:\/\//i', $content)
                || preg_match('/import\(\s*[\'"]https?:\/\//i', $content)
                || preg_match('/@import\s+url\(\s*[\'"]?https?:\/\//i', $content)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, '這些畫面檔案連到外部網站載入資源：' . implode('、', $offenders));
    }

    public function test_theme_css_does_not_import_external_fonts(): void
    {
        $css = File::get(public_path('css/theme-b.css'));

        $this->assertDoesNotMatchRegularExpression('/https?:\/\//i', $css, 'theme-b.css 不應連到外部網站');
    }
}
